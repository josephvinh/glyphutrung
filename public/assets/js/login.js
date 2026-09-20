/* ==========================================================
   LOGIN SCREEN — component Alpine cho màn đăng nhập / đăng ký / đổi mật khẩu.
   Trang đăng nhập là trang riêng (không có tnttApp), nên đây là component
   độc lập, chỉ phụ thuộc window.Passkey (passkey.js) cho đăng nhập sinh trắc.
   ========================================================== */
document.addEventListener('alpine:init', () => {
    Alpine.data('loginScreen', () => ({
        step: 'login',      // login | register | done | changepw
        phone: '', password: '', showPw: false,
        oldPw: '', newPw: '', newPw2: '',
        rHoly: '', rName: '', rPhone: '', rBirth: '', rPw: '', rPw2: '', rNote: '', rCode: '',
        rDanhXung: 'glv',
        error: '', busy: false,

        // Chuẩn hoá SĐT: bỏ khoảng trắng/dấu, đổi +84/84/0084 -> 0
        chuanHoaSdt(s) {
            s = String(s || '').replace(/[\s.\-()]/g, '').trim();
            if (s.startsWith('+84'))  s = '0' + s.slice(3);
            else if (s.startsWith('0084')) s = '0' + s.slice(4);
            else if (s.startsWith('84') && s.length >= 11) s = '0' + s.slice(2);
            return s;
        },

        // Xoá sạch form đăng ký trước khi mở. Thiếu bước này thì dữ liệu của
        // lần đăng ký TRƯỚC — kể cả mật khẩu — vẫn nằm nguyên trong các ô input
        // khi người dùng bấm "Về màn đăng nhập" rồi vào lại form.
        resetRegisterForm() {
            this.rHoly = ''; this.rName = ''; this.rPhone = ''; this.rBirth = '';
            this.rPw = ''; this.rPw2 = ''; this.rNote = ''; this.rCode = '';
            this.rDanhXung = 'glv';
            this.error = '';
        },

        goRegister() { this.resetRegisterForm(); this.step = 'register'; this.$nextTick(() => lucide.createIcons()); },
        goLogin()    { this.error = ''; this.step = 'login';    this.$nextTick(() => lucide.createIcons()); },

        async submitRegister() {
            this.error = '';
            if (this.rPw !== this.rPw2) { this.error = 'Hai lần nhập mật khẩu không khớp.'; return; }
            this.busy = true;
            try {
                const r = await this.post('register', {
                    holyName: this.rHoly.trim(), fullName: this.rName.trim(),
                    phone: this.chuanHoaSdt(this.rPhone), birthDate: this.rBirth,
                    password: this.rPw, note: this.rNote.trim(),
                    danhXung: this.rDanhXung
                });
                if (!r.ok) { this.error = r.error; return; }
                this.rCode = r.code;
                // Đã gửi xong thì không giữ mật khẩu lại trong bộ nhớ nữa
                this.rPw = ''; this.rPw2 = '';
                this.step = 'done';
                this.$nextTick(() => lucide.createIcons());
            } catch (e) {
                this.error = 'Không kết nối được máy chủ. Kiểm tra lại mạng rồi thử lại.';
            } finally { this.busy = false; }
        },

        async post(action, body) {
            const res = await fetch('api/auth.php?action=' + action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });
            return res.json();
        },

        async submitLogin() {
            this.error = ''; this.busy = true;
            try {
                const r = await this.post('login', { phone: this.chuanHoaSdt(this.phone), password: this.password });
                if (!r.ok) { this.error = r.error; return; }
                // Lần đầu đăng nhập thì bắt đổi mật khẩu ngay, không cho vào thẳng
                if (r.user.mustChangePw) {
                    this.oldPw = this.password;
                    this.step = 'changepw';
                    this.error = '';
                    this.$nextTick(() => lucide.createIcons());
                    return;
                }
                location.reload();
            } catch (e) {
                this.error = 'Không kết nối được máy chủ. Kiểm tra lại mạng rồi thử lại.';
            } finally { this.busy = false; }
        },

        async loginPasskey() {
            this.error = '';
            this.busy = true;
            try {
                const res = await window.Passkey.login();
                if (res && res.user) { location.reload(); return; }
                // Người dùng huỷ -> message rỗng -> im lặng. Lỗi thật -> hiện ô đỏ.
                if (res && res.message) this.error = res.message;
            } catch (e) {
                this.error = 'Đăng nhập bằng FaceID / Vân tay chưa thành công. Vui lòng thử lại.';
            } finally {
                this.busy = false;
            }
        },

        async submitChange() {
            this.error = '';
            if (this.newPw !== this.newPw2) { this.error = 'Hai lần nhập mật khẩu mới không khớp.'; return; }
            this.busy = true;
            try {
                const r = await this.post('password', { current: this.oldPw, new: this.newPw });
                if (!r.ok) { this.error = r.error; return; }
                location.reload();
            } catch (e) {
                this.error = 'Không kết nối được máy chủ.';
            } finally { this.busy = false; }
        },

        init() { this.$nextTick(() => lucide.createIcons()); }
    }));
});
