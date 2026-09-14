/* ==========================================================
   PASSKEY — đăng nhập sinh trắc học (WebAuthn: vân tay / FaceID)
   Global window.Passkey. Dùng ở màn đăng nhập (login) và Hồ sơ (register).
   Các endpoint ở api/passkey.php KHÔNG cần CSRF (login chưa có token).
   ========================================================== */
const Passkey = {
    // POST JSON tới api/passkey.php, trả JSON. Tự thân, không phụ thuộc
    // component Alpine (vì màn đăng nhập không có tnttApp).
    async _post(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body || {})
        });
        return res.json();
    },

    bufferToBase64(buffer) {
        let binary = '';
        const bytes = new Uint8Array(buffer);
        for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
        return window.btoa(binary);
    },

    // Thư viện lbuchs bọc field nhị phân dạng "=?BINARY?B?<base64>?=" để máy
    // khách nhận ra đây là dữ liệu nhị phân — phải gỡ vỏ đó trước khi giải mã.
    base64ToBuffer(base64) {
        const m = /^=\?BINARY\?B\?(.*)\?=$/.exec(base64);
        if (m) base64 = m[1];
        let str = base64.replace(/-/g, '+').replace(/_/g, '/');
        str = str.replace(/[^A-Za-z0-9\+\/]/g, '');
        while (str.length % 4 !== 0) str += '=';
        const bin = window.atob(str);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return bytes.buffer;
    },

    async register() {
        // Ghi rõ đang ở bước nào — nếu lỗi, alert nói đúng chỗ hỏng
        // (đặc biệt hữu ích trên iPhone không mở được F12 để xem console).
        let buoc = 'bắt đầu';
        try {
            if (!window.PublicKeyCredential) {
                alert('Trình duyệt của bạn không hỗ trợ sinh trắc học / FaceID.');
                return false;
            }

            buoc = 'gọi server lấy tham số (getRegisterArgs)';
            const res = await this._post('api/passkey.php?action=getRegisterArgs');
            if (!res.ok) { alert(res.error || 'Lỗi server'); return false; }

            // Server trả { publicKey: {...} } (chuẩn WebAuthn). Gỡ vỏ nếu có.
            const args = res.args.publicKey || res.args;

            buoc = 'giải mã challenge';
            args.challenge = this.base64ToBuffer(args.challenge);
            buoc = 'giải mã user.id';
            args.user.id   = this.base64ToBuffer(args.user.id);
            buoc = 'giải mã excludeCredentials';
            if (args.excludeCredentials) {
                args.excludeCredentials.forEach(c => { c.id = this.base64ToBuffer(c.id); });
            }

            buoc = 'tạo khoá (navigator.credentials.create)';
            const cred = await navigator.credentials.create({ publicKey: args });

            buoc = 'mã hoá kết quả trả về';
            const payload = {
                id: cred.id,
                clientDataJSON:    this.bufferToBase64(cred.response.clientDataJSON),
                attestationObject: this.bufferToBase64(cred.response.attestationObject)
            };

            buoc = 'lưu lên server (processRegister)';
            const verifyRes = await this._post('api/passkey.php?action=processRegister', payload);
            if (verifyRes.ok) { alert('Đăng ký Vân tay / FaceID thành công!'); return true; }
            alert(verifyRes.error || 'Xác thực thất bại.');
            return false;

        } catch (e) {
            console.error(e);
            alert('Lỗi ở bước: ' + buoc + '\n[' + (e.name || 'Error') + '] ' + e.message);
            return false;
        }
    },

    async login() {
        let buoc = 'bắt đầu';
        try {
            if (!window.PublicKeyCredential) {
                alert('Trình duyệt của bạn không hỗ trợ sinh trắc học / FaceID.');
                return null;
            }

            buoc = 'gọi server lấy tham số (getLoginArgs)';
            const res = await this._post('api/passkey.php?action=getLoginArgs');
            if (!res.ok) { alert(res.error || 'Lỗi server'); return null; }

            const args = res.args.publicKey || res.args;
            buoc = 'giải mã challenge';
            args.challenge = this.base64ToBuffer(args.challenge);
            buoc = 'giải mã allowCredentials';
            if (args.allowCredentials) {
                args.allowCredentials.forEach(c => { c.id = this.base64ToBuffer(c.id); });
            }

            buoc = 'lấy khoá (navigator.credentials.get)';
            const cred = await navigator.credentials.get({ publicKey: args });

            buoc = 'lưu lên server (processLogin)';
            const verifyRes = await this._post('api/passkey.php?action=processLogin', {
                id: cred.id,
                clientDataJSON:    this.bufferToBase64(cred.response.clientDataJSON),
                authenticatorData: this.bufferToBase64(cred.response.authenticatorData),
                signature:         this.bufferToBase64(cred.response.signature),
                userHandle:        cred.response.userHandle ? this.bufferToBase64(cred.response.userHandle) : null
            });
            if (verifyRes.ok) return verifyRes.user;
            alert(verifyRes.error || 'Đăng nhập sinh trắc học thất bại.');
            return null;

        } catch (e) {
            console.error(e);
            // NotAllowedError = người dùng bấm huỷ / hết giờ, không cần báo lỗi.
            if (e.name !== 'NotAllowedError') alert('Lỗi ở bước: ' + buoc + '\n[' + (e.name || 'Error') + '] ' + e.message);
            return null;
        }
    }
};
window.Passkey = Passkey;
// Cũng khai vào window.TNTT.passkey để app.js (bộ gộp module) không báo
// "thiếu module passkey" — passkey nằm trong asset_manifest để được NẠP kèm
// nhưng vốn là API toàn cục (window.Passkey), không phải mảnh của tnttApp.
window.TNTT = window.TNTT || {};
window.TNTT.passkey = Passkey;
