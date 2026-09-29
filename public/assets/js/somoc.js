/* ==========================================================
   SỔ MỘC (public/somoc.php) — component Alpine cho:
     - soMocPending : khu vực "đơn đang chờ lấy" ở tab Sổ Mộc + hủy đơn.
     - doiQuaApp    : lưới quà + giỏ nhiều món + đặt đơn ở tab Đổi quà.

   Trang somoc.php KHÔNG đăng nhập/không session, nên các hàm dưới đây chỉ
   gọi ĐÚNG 2 action GHI của api/somoc_order.php (place/cancel) — không có
   API nào khác được dùng ở đây. Lỗi nghiệp vụ (mã sai, thiếu Mộc, hết
   tồn...) đều được server GỘP thành một thông điệp chung (r.error) — phía
   JS chỉ hiển thị nguyên văn, không tự suy đoán/diễn giải thêm.

   ĐƠN 'CHỜ LẤY' HIỆN CÓ (nếu có) do PHP (somoc.php) đọc sẵn và truyền vào
   qua tham số khởi tạo — component KHÔNG tự gọi action=pending lúc mount
   nữa (fix round 1: mỗi action, kể cả 'pending' chỉ đọc, đều tính vào
   rate-limit theo IP ở somoc_order.php; 2 component cùng tự fetch khi
   trang vừa tải khiến 1 lượt xem trang tốn 2-3 lượt throttle, dễ khoá oan
   cả nhóm dùng chung IP/wifi giáo xứ). Sau khi NGƯỜI DÙNG chủ động đặt
   thành công thì vẫn dùng lại kết quả trả về từ chính lượt gọi đó (không
   fetch thêm); sau khi hủy thành công thì tải lại cả trang — PHP sẽ tự
   tính lại đúng 1 lần trong lượt tải đó.
   ========================================================== */

/** Gọi api/somoc_order.php?action=... bằng POST JSON (place/cancel). */
async function somocGoiApi(action, body) {
    const res = await fetch('api/somoc_order.php?action=' + encodeURIComponent(action), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    return res.json();
}

/** Định dạng "H:i · d/m/Y" từ chuỗi datetime MySQL — dùng cho hạn lấy. */
function somocDinhDangNgay(s) {
    if (!s) return '';
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    const p = (n) => String(n).padStart(2, '0');
    return p(d.getHours()) + ':' + p(d.getMinutes()) + ' · ' + p(d.getDate()) + '/' + p(d.getMonth() + 1) + '/' + d.getFullYear();
}

/** Phòng thủ chiều sâu: chỉ nhận ảnh http(s) hợp lệ để bind vào :src — quà
 *  không có ảnh (hoặc URL không đúng dạng) thì hiện icon 🎁 thay vào đó. */
function somocUrlAnhOk(u) {
    return typeof u === 'string' && /^https?:\/\//i.test(u);
}

document.addEventListener('alpine:init', () => {

    /* ---------- TAB SỔ MỘC: đơn đang chờ lấy + hủy đơn ---------- */
    Alpine.data('soMocPending', (ma, pendingInit) => ({
        ma: ma,
        pending: pendingInit || null,
        showCancel: false,
        password: '',
        error: '',
        busy: false,

        dinhDangNgay: somocDinhDangNgay,

        openCancel() { this.password = ''; this.error = ''; this.showCancel = true; },
        closeCancel() { this.showCancel = false; },

        async submitCancel() {
            this.error = '';
            if (!this.password) { this.error = 'Vui lòng nhập mật mã đổi quà.'; return; }
            this.busy = true;
            try {
                const r = await somocGoiApi('cancel', { code: this.ma, password: this.password });
                if (!r.ok) { this.error = r.error; return; }
                // Hủy xong: làm mới cả trang — PHP tự tính lại pending đúng 1 lần
                // trong lượt tải mới, khỏi phải tự fetch thêm ở đây.
                location.reload();
            } catch (e) {
                this.error = 'Không kết nối được máy chủ. Kiểm tra lại mạng rồi thử lại.';
            } finally {
                this.busy = false;
            }
        },
    }));

    /* ---------- TAB ĐỔI QUÀ: lưới quà + giỏ + đặt đơn ---------- */
    Alpine.data('doiQuaApp', (ma, currentBalance, gifts, pendingInit) => ({
        ma: ma,
        currentBalance: currentBalance,
        gifts: gifts,
        cart: {},          // giftId -> số lượng đã chọn
        pending: pendingInit || null,   // đơn 'chờ lấy' hiện có (nếu có thì khoá không cho đặt thêm)
        available: Math.max(0, currentBalance - (pendingInit ? pendingInit.total : 0)),
        showPlace: false,
        pw1: '',
        pw2: '',
        error: '',
        busy: false,
        success: null,      // { orderId, total, expiresAt } sau khi đặt thành công

        dinhDangNgay: somocDinhDangNgay,
        urlAnhOk: somocUrlAnhOk,

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
                const r = await somocGoiApi('place', { code: this.ma, items: items, password: this.pw1 });
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
