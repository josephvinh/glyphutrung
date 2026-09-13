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
        const bin = window.atob(base64.replace(/-/g, '+').replace(/_/g, '/'));
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return bytes.buffer;
    },

    async register() {
        try {
            if (!window.PublicKeyCredential) {
                alert('Trình duyệt của bạn không hỗ trợ sinh trắc học / FaceID.');
                return false;
            }

            const res = await this._post('api/passkey.php?action=getRegisterArgs');
            if (!res.ok) { alert(res.error || 'Lỗi server'); return false; }

            // Server trả { publicKey: {...} } (chuẩn WebAuthn). Gỡ vỏ nếu có.
            const args = res.args.publicKey || res.args;
            args.challenge = this.base64ToBuffer(args.challenge);
            args.user.id   = this.base64ToBuffer(args.user.id);
            if (args.excludeCredentials) {
                args.excludeCredentials.forEach(c => { c.id = this.base64ToBuffer(c.id); });
            }

            const cred = await navigator.credentials.create({ publicKey: args });

            const verifyRes = await this._post('api/passkey.php?action=processRegister', {
                id: cred.id,
                clientDataJSON:    this.bufferToBase64(cred.response.clientDataJSON),
                attestationObject: this.bufferToBase64(cred.response.attestationObject)
            });
            if (verifyRes.ok) { alert('Đăng ký Vân tay / FaceID thành công!'); return true; }
            alert(verifyRes.error || 'Xác thực thất bại.');
            return false;

        } catch (e) {
            console.error(e);
            alert('Quá trình đăng ký bị hủy hoặc lỗi: ' + e.message);
            return false;
        }
    },

    async login() {
        try {
            if (!window.PublicKeyCredential) {
                alert('Trình duyệt của bạn không hỗ trợ sinh trắc học / FaceID.');
                return null;
            }

            const res = await this._post('api/passkey.php?action=getLoginArgs');
            if (!res.ok) { alert(res.error || 'Lỗi server'); return null; }

            const args = res.args.publicKey || res.args;
            args.challenge = this.base64ToBuffer(args.challenge);
            if (args.allowCredentials) {
                args.allowCredentials.forEach(c => { c.id = this.base64ToBuffer(c.id); });
            }

            const cred = await navigator.credentials.get({ publicKey: args });

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
            if (e.name !== 'NotAllowedError') alert('Lỗi đăng nhập sinh trắc học: ' + e.message);
            return null;
        }
    }
};
window.Passkey = Passkey;
