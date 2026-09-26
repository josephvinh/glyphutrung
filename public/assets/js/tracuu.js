/* ==========================================================
   TRA CỨU SỔ MỘC (public/tracuu.php) — component Alpine cho:
     - soMocPending : khu vực "đơn đang chờ lấy" ở tab Sổ Mộc + hủy đơn.
     - doiQuaApp    : lưới quà + giỏ nhiều món + đặt đơn ở tab Đổi quà.

   Trang tracuu.php KHÔNG đăng nhập/không session, nên các hàm dưới đây chỉ
   gọi ĐÚNG 3 action công khai của api/tracuu_order.php (place/cancel/pending)
   — không có API nào khác được dùng ở đây. Lỗi nghiệp vụ (mã sai, thiếu Mộc,
   hết tồn...) đều được server GỘP thành một thông điệp chung (r.error) —
   phía JS chỉ hiển thị nguyên văn, không tự suy đoán/diễn giải thêm.
   ========================================================== */

/** Gọi api/tracuu_order.php?action=... — POST gửi JSON, GET gắn code lên query. */
async function tracuuGoiApi(action, body, method) {
    method = method || 'POST';
    let url = 'api/tracuu_order.php?action=' + encodeURIComponent(action);
    const opt = { method };
    if (method === 'GET') {
        url += '&code=' + encodeURIComponent(body.code || '');
    } else {
        opt.headers = { 'Content-Type': 'application/json' };
        opt.body = JSON.stringify(body);
    }
    const res = await fetch(url, opt);
    return res.json();
}

/** Định dạng "H:i · d/m/Y" từ chuỗi datetime MySQL — dùng cho hạn lấy. */
function tracuuDinhDangNgay(s) {
    if (!s) return '';
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    const p = (n) => String(n).padStart(2, '0');
    return p(d.getHours()) + ':' + p(d.getMinutes()) + ' · ' + p(d.getDate()) + '/' + p(d.getMonth() + 1) + '/' + d.getFullYear();
}

document.addEventListener('alpine:init', () => {

    /* ---------- TAB SỔ MỘC: đơn đang chờ lấy + hủy đơn ---------- */
    Alpine.data('soMocPending', (ma) => ({
        ma: ma,
        pending: null,
        showCancel: false,
        password: '',
        error: '',
        busy: false,

        async init() {
            if (!this.ma) return;
            try {
                const r = await tracuuGoiApi('pending', { code: this.ma }, 'GET');
                if (r.ok) this.pending = r.pending;
            } catch (e) {
                // Khu vực này chỉ là tiện ích thêm — lỗi mạng thì im lặng bỏ qua,
                // trang Sổ Mộc vẫn dùng được bình thường.
            }
        },

        dinhDangNgay: tracuuDinhDangNgay,

        openCancel() { this.password = ''; this.error = ''; this.showCancel = true; },
        closeCancel() { this.showCancel = false; },

        async submitCancel() {
            this.error = '';
            if (!this.password) { this.error = 'Vui lòng nhập mật mã đổi quà.'; return; }
            this.busy = true;
            try {
                const r = await tracuuGoiApi('cancel', { code: this.ma, password: this.password });
                if (!r.ok) { this.error = r.error; return; }
                // Hủy xong: làm mới cả trang để đồng bộ lại Ví Mộc + khu vực này.
                location.reload();
            } catch (e) {
                this.error = 'Không kết nối được máy chủ. Kiểm tra lại mạng rồi thử lại.';
            } finally {
                this.busy = false;
            }
        },
    }));

    /* ---------- TAB ĐỔI QUÀ: lưới quà + giỏ + đặt đơn ---------- */
    Alpine.data('doiQuaApp', (ma, currentBalance, gifts) => ({
        ma: ma,
        currentBalance: currentBalance,
        gifts: gifts,
        cart: {},          // giftId -> số lượng đã chọn
        pending: null,      // đơn 'chờ lấy' hiện có (nếu có thì khoá không cho đặt thêm)
        available: currentBalance,
        showPlace: false,
        pw1: '',
        pw2: '',
        error: '',
        busy: false,
        success: null,      // { orderId, total, expiresAt } sau khi đặt thành công

        async init() {
            if (!this.ma) return;
            try {
                const r = await tracuuGoiApi('pending', { code: this.ma }, 'GET');
                if (r.ok) {
                    this.pending = r.pending;
                    const daGiu = this.pending ? this.pending.total : 0;
                    this.available = Math.max(0, this.currentBalance - daGiu);
                }
            } catch (e) {
                // Không lấy được số đã giữ thì tạm coi cả Ví là khả dụng — server
                // vẫn là nơi quyết định thật (rewards_place_order tự kiểm lại).
            }
        },

        dinhDangNgay: tracuuDinhDangNgay,

        qty(id) { return this.cart[id] || 0; },

        tang(g) {
            const cur = this.qty(g.id);
            if (g.stock <= 0 || cur >= g.stock) return;
            this.cart[g.id] = cur + 1;
        },

        giam(g) {
            const cur = this.qty(g.id);
            if (cur <= 0) return;
            const n = cur - 1;
            if (n === 0) delete this.cart[g.id];
            else this.cart[g.id] = n;
        },

        get soMon() {
            return Object.values(this.cart).reduce((a, b) => a + b, 0);
        },

        get tongMoc() {
            let t = 0;
            for (const g of this.gifts) {
                if (this.cart[g.id]) t += g.stampCost * this.cart[g.id];
            }
            return t;
        },

        get vuotQua() {
            return this.tongMoc > this.available;
        },

        get coTheDat() {
            return !this.pending && this.soMon > 0 && !this.vuotQua && !this.busy;
        },

        openPlace() {
            if (!this.coTheDat) return;
            this.pw1 = ''; this.pw2 = ''; this.error = '';
            this.showPlace = true;
        },
        closePlace() { this.showPlace = false; },

        async submitPlace() {
            this.error = '';
            if (!this.pw1 || !this.pw2) { this.error = 'Vui lòng nhập mật mã đổi quà (2 lần).'; return; }
            if (this.pw1 !== this.pw2) { this.error = 'Hai lần nhập mật mã không khớp.'; return; }
            const items = Object.keys(this.cart).map((id) => ({ giftId: parseInt(id, 10), qty: this.cart[id] }));
            if (!items.length) { this.error = 'Giỏ quà đang trống.'; return; }

            this.busy = true;
            try {
                const r = await tracuuGoiApi('place', { code: this.ma, items: items, password: this.pw1 });
                if (!r.ok) { this.error = r.error; return; }
                this.success = { orderId: r.orderId, total: r.total, expiresAt: r.expiresAt };
                this.pending = this.success;
                this.cart = {};
                this.showPlace = false;
                this.available = Math.max(0, this.currentBalance - r.total);
            } catch (e) {
                this.error = 'Không kết nối được máy chủ. Kiểm tra lại mạng rồi thử lại.';
            } finally {
                this.busy = false;
            }
        },
    }));

});
