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

    // Báo cho người dùng: ưu tiên toast của app (đẹp, không chặn thao tác).
    // Màn đăng nhập không nạp toast thì mới rơi về alert.
    _bao(message, type = 'info') {
        if (window.TNTT && window.TNTT.toast) window.TNTT.toast.show(message, type, 4500);
        else alert(message);
    },

    // Dịch lỗi kỹ thuật của WebAuthn sang câu thuần Việt người dùng hiểu.
    // Trả '' nghĩa là KHÔNG cần báo (người dùng tự bấm huỷ hoặc hết giờ).
    _loiThanThien(e, macDinh) {
        switch (e && e.name) {
            case 'NotAllowedError':
            case 'AbortError':
                return '';   // huỷ / hết giờ — im lặng, không doạ người dùng
            case 'InvalidStateError':
                return 'Thiết bị này đã được đăng ký trước đó rồi.';
            case 'NotSupportedError':
                return 'Thiết bị hoặc trình duyệt chưa hỗ trợ FaceID / Vân tay.';
            case 'SecurityError':
                return 'Không thiết lập được vì lý do bảo mật. Hãy chắc chắn đang mở bằng HTTPS.';
            default:
                return macDinh;
        }
    },

    // Đăng ký khoá sinh trắc cho tài khoản đang đăng nhập (gọi từ màn Hồ sơ).
    // Trả true nếu thành công. opts.silent = true: KHÔNG hiện toast nào (dùng
    // cho nút gạt kiểu iOS — trạng thái nút gạt tự nói lên kết quả).
    async register(opts) {
        const silent = !!(opts && opts.silent);
        // buoc: chỉ để ghi console cho lập trình viên, KHÔNG hiện cho người dùng.
        let buoc = 'bắt đầu';
        try {
            if (!window.PublicKeyCredential) {
                if (!silent) this._bao('Thiết bị của bạn chưa hỗ trợ FaceID / Vân tay.', 'warning');
                return false;
            }

            buoc = 'lấy tham số từ máy chủ';
            const res = await this._post('api/passkey.php?action=getRegisterArgs');
            if (!res.ok) { if (!silent) this._bao(res.error || 'Máy chủ đang bận, vui lòng thử lại sau.', 'error'); return false; }

            // Server trả { publicKey: {...} } (chuẩn WebAuthn). Gỡ vỏ nếu có.
            const args = res.args.publicKey || res.args;
            buoc = 'chuẩn bị dữ liệu';
            args.challenge = this.base64ToBuffer(args.challenge);
            args.user.id   = this.base64ToBuffer(args.user.id);
            if (args.excludeCredentials) {
                args.excludeCredentials.forEach(c => { c.id = this.base64ToBuffer(c.id); });
            }

            buoc = 'chờ xác thực khuôn mặt / vân tay';
            const cred = await navigator.credentials.create({ publicKey: args });

            buoc = 'lưu lên máy chủ';
            const verifyRes = await this._post('api/passkey.php?action=processRegister', {
                id: cred.id,
                clientDataJSON:    this.bufferToBase64(cred.response.clientDataJSON),
                attestationObject: this.bufferToBase64(cred.response.attestationObject)
            });
            if (verifyRes.ok) {
                if (!silent) this._bao('Đã đăng ký thành công! Lần sau bạn có thể đăng nhập chỉ bằng khuôn mặt hoặc vân tay.', 'success');
                return true;
            }
            if (!silent) this._bao(verifyRes.error || 'Đăng ký chưa thành công, vui lòng thử lại.', 'error');
            return false;

        } catch (e) {
            console.error('[Passkey] Đăng ký lỗi ở bước:', buoc, e);
            if (!silent) {
                const msg = this._loiThanThien(e, 'Chưa đăng ký được FaceID / Vân tay. Vui lòng thử lại.');
                if (msg) this._bao(msg, 'error');
            }
            return false;
        }
    },

    // Kiểm tra tài khoản đã bật sinh trắc chưa (cho nút gạt ở Hồ sơ).
    async status() {
        try {
            const r = await this._post('api/passkey.php?action=status');
            return !!(r && r.ok && r.hasPasskey);
        } catch (e) { console.error('[Passkey] status:', e); return false; }
    },

    // Gỡ khoá sinh trắc của tài khoản (gạt TẮT). opts.silent như register().
    async remove(opts) {
        const silent = !!(opts && opts.silent);
        try {
            const r = await this._post('api/passkey.php?action=delete');
            if (r && r.ok) { if (!silent) this._bao('Đã tắt đăng nhập sinh trắc.', 'info'); return true; }
            if (!silent) this._bao((r && r.error) || 'Không tắt được, vui lòng thử lại.', 'error');
            return false;
        } catch (e) { console.error('[Passkey] remove:', e); return false; }
    },

    // Đăng nhập bằng khoá sinh trắc (gọi từ màn Đăng nhập).
    // Trả { user, message }: user có giá trị khi thành công; message là câu
    // báo lỗi thân thiện để màn login hiện ô đỏ (null = im lặng, người dùng huỷ).
    async login() {
        let buoc = 'bắt đầu';
        try {
            if (!window.PublicKeyCredential) {
                return { user: null, message: 'Thiết bị của bạn chưa hỗ trợ FaceID / Vân tay.' };
            }

            buoc = 'lấy tham số từ máy chủ';
            const res = await this._post('api/passkey.php?action=getLoginArgs');
            if (!res.ok) return { user: null, message: res.error || 'Máy chủ đang bận, vui lòng thử lại sau.' };

            const args = res.args.publicKey || res.args;
            buoc = 'chuẩn bị dữ liệu';
            args.challenge = this.base64ToBuffer(args.challenge);
            if (args.allowCredentials) {
                args.allowCredentials.forEach(c => { c.id = this.base64ToBuffer(c.id); });
            }

            buoc = 'chờ xác thực khuôn mặt / vân tay';
            const cred = await navigator.credentials.get({ publicKey: args });

            buoc = 'xác minh trên máy chủ';
            const verifyRes = await this._post('api/passkey.php?action=processLogin', {
                id: cred.id,
                clientDataJSON:    this.bufferToBase64(cred.response.clientDataJSON),
                authenticatorData: this.bufferToBase64(cred.response.authenticatorData),
                signature:         this.bufferToBase64(cred.response.signature),
                userHandle:        cred.response.userHandle ? this.bufferToBase64(cred.response.userHandle) : null
            });
            if (verifyRes.ok) return { user: verifyRes.user, message: null };
            return { user: null, message: verifyRes.error || 'Đăng nhập bằng FaceID / Vân tay chưa thành công.' };

        } catch (e) {
            console.error('[Passkey] Đăng nhập lỗi ở bước:', buoc, e);
            // NotAllowedError / AbortError = người dùng huỷ hoặc hết giờ -> im lặng.
            const msg = this._loiThanThien(e, 'Đăng nhập bằng FaceID / Vân tay chưa thành công.');
            return { user: null, message: msg || null };
        }
    }
};
window.Passkey = Passkey;
// Cũng khai vào window.TNTT.passkey để app.js (bộ gộp module) không báo
// "thiếu module passkey" — passkey nằm trong asset_manifest để được NẠP kèm
// nhưng vốn là API toàn cục (window.Passkey), không phải mảnh của tnttApp.
window.TNTT = window.TNTT || {};
window.TNTT.passkey = Passkey;
