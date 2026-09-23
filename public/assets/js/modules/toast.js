/* ==========================================================
   TOAST + HỘP THOẠI XÁC NHẬN
   Thay cho alert()/confirm() gốc của trình duyệt: khi app cài ra
   màn hình chính (PWA) thì hộp thoại gốc hiện khung xấu "trang này
   cho biết…", chặn cả màn, không theo phong cách app và không đọc
   được bằng trình đọc màn hình. Ở đây tự dựng lại cho đồng bộ.
   ========================================================== */
window.TNTT = window.TNTT || {};

/* SVG icon nhỏ cho từng loại toast (nội tuyến để không phụ thuộc lucide
   đã kịp khởi tạo hay chưa). Nét khớp bộ icon còn lại của app. */
const TOAST_ICONS = {
    success: '<path d="M20 6 9 17l-5-5"/>',
    error:   '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
    warning: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    info:    '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>'
};

function toastSvg(type) {
    const path = TOAST_ICONS[type] || TOAST_ICONS.info;
    return '<svg class="toast-ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" '
         + 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
         + 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + path + '</svg>';
}

window.TNTT.toast = {
    container: null,

    /**
     * Tạo vùng chứa toast. role="status" + aria-live="polite" để trình
     * đọc màn hình đọc lên nội dung mỗi khi có toast mới.
     */
    init() {
        if (this.container) return;
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        this.container.setAttribute('role', 'status');
        this.container.setAttribute('aria-live', 'polite');
        this.container.setAttribute('aria-atomic', 'false');
        document.body.appendChild(this.container);
    },

    /**
     * Hiện một toast.
     * @param {string} message  Nội dung (giữ xuống dòng \n).
     * @param {string} type     'success' | 'error' | 'warning' | 'info'
     * @param {number} duration Thời gian tự ẩn (ms). 0 = không tự ẩn.
     * @returns {HTMLElement}
     */
    show(message, type = 'info', duration = 4000) {
        if (!this.container) this.init();

        // Lỗi thường cần đọc kỹ hơn -> để lâu hơn một chút.
        if (duration === 4000 && type === 'error') duration = 6000;

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        toast.innerHTML = toastSvg(type);
        const text = document.createElement('span');
        text.className = 'toast-text';
        text.textContent = message;            // textContent -> an toàn XSS
        toast.appendChild(text);

        // Chạm vào toast là đóng luôn (không phải đợi hết giờ).
        toast.addEventListener('click', () => this._dismiss(toast));
        toast.setAttribute('title', 'Chạm để đóng');

        this.container.appendChild(toast);

        if (duration > 0) {
            setTimeout(() => this._dismiss(toast), duration);
        }
        return toast;
    },

    _dismiss(toast) {
        if (!toast || !toast.parentNode || toast.classList.contains('fade-out')) return;
        toast.classList.add('fade-out');
        setTimeout(() => { if (toast.parentNode) toast.remove(); }, 300);
    },

    success(message, duration) { return this.show(message, 'success', duration); },
    error(message, duration)   { return this.show(message, 'error', duration); },
    warning(message, duration) { return this.show(message, 'warning', duration); },
    info(message, duration)    { return this.show(message, 'info', duration); },

    /**
     * Hộp thoại xác nhận trong app (thay confirm() gốc).
     * @param {string} message
     * @param {Object} [opts]
     * @param {string} [opts.title]        Tiêu đề (mặc định "Xác nhận").
     * @param {string} [opts.confirmText]  Chữ nút đồng ý (mặc định "Đồng ý").
     * @param {string} [opts.cancelText]   Chữ nút huỷ (mặc định "Huỷ").
     * @param {boolean}[opts.danger]       true -> nút đồng ý màu đỏ (hành động xoá).
     * @returns {Promise<boolean>}
     */
    confirm(message, opts = {}) {
        const {
            title = 'Xác nhận',
            confirmText = 'Đồng ý',
            cancelText = 'Huỷ',
            danger = false
        } = opts;

        return new Promise((resolve) => {
            const prevFocus = document.activeElement;

            const backdrop = document.createElement('div');
            backdrop.className = 'tntt-dialog-backdrop';

            // id duy nhất cho mỗi lần mở: nếu hai hộp thoại chồng nhau (một cái
            // đang mờ dần khi cái kia mở ra) thì không bị trùng id, aria-labelledby
            // vẫn trỏ đúng tiêu đề của chính hộp thoại đó.
            const titleId = 'tntt-dialog-title-' + (this._dialogSeq = (this._dialogSeq || 0) + 1);

            const dialog = document.createElement('div');
            dialog.className = 'tntt-dialog';
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('aria-labelledby', titleId);

            const h = document.createElement('h2');
            h.className = 'tntt-dialog-title';
            h.id = titleId;
            h.textContent = title;

            const p = document.createElement('p');
            p.className = 'tntt-dialog-msg';
            p.textContent = message;

            const actions = document.createElement('div');
            actions.className = 'tntt-dialog-actions';

            const btnCancel = document.createElement('button');
            btnCancel.type = 'button';
            btnCancel.className = 'tntt-dialog-btn tntt-dialog-cancel';
            btnCancel.textContent = cancelText;

            const btnOk = document.createElement('button');
            btnOk.type = 'button';
            btnOk.className = 'tntt-dialog-btn ' + (danger ? 'tntt-dialog-danger' : 'tntt-dialog-ok');
            btnOk.textContent = confirmText;

            actions.appendChild(btnCancel);
            actions.appendChild(btnOk);
            dialog.appendChild(h);
            dialog.appendChild(p);
            dialog.appendChild(actions);
            backdrop.appendChild(dialog);
            document.body.appendChild(backdrop);

            // Khoá cuộn nền khi hộp thoại mở.
            const prevOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';

            let done = false;
            const close = (result) => {
                if (done) return;
                done = true;
                document.removeEventListener('keydown', onKey, true);
                document.body.style.overflow = prevOverflow;
                backdrop.classList.add('fade-out');
                setTimeout(() => {
                    if (backdrop.parentNode) backdrop.remove();
                    // Trả tiêu điểm về chỗ cũ cho người dùng bàn phím.
                    if (prevFocus && typeof prevFocus.focus === 'function') {
                        try { prevFocus.focus(); } catch (e) {}
                    }
                }, 180);
                resolve(result);
            };

            const onKey = (e) => {
                if (e.key === 'Escape') { e.preventDefault(); close(false); }
                else if (e.key === 'Tab') {
                    // Bẫy tiêu điểm trong hộp thoại (chỉ có 2 nút).
                    e.preventDefault();
                    (document.activeElement === btnOk ? btnCancel : btnOk).focus();
                }
            };

            btnCancel.addEventListener('click', () => close(false));
            btnOk.addEventListener('click', () => close(true));
            backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(false); });
            document.addEventListener('keydown', onKey, true);

            // Tiêu điểm ban đầu vào nút đồng ý (nút huỷ cho hành động nguy hiểm).
            requestAnimationFrame(() => (danger ? btnCancel : btnOk).focus());
        });
    }
};

// Tự khởi tạo khi DOM sẵn sàng
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.TNTT.toast.init());
} else {
    window.TNTT.toast.init();
}
