# Báo cáo Sprint 4 — Đợt D (Frontend JS), H (UX), I (Tài liệu)

Ngày: 2026-09-24 · Nhánh: `claude/web-review-plan-we2ika`

## Đợt D — Frontend JS

### Đánh giá: **Sạch, có kỷ luật**
- **Không còn `alert()/confirm()` lạc**: đã chuyển sang `toast.js` (toast + `confirm()` dạng
  modal trong app, đúng commit #15). Hai chỗ `alert()` còn lại là **fallback cố ý, có chú
  thích**: `app.js:57` (khi module chưa nạp được thì toast cũng chưa có → phải báo bằng
  alert), `passkey.js:50` (màn đăng nhập không nạp toast). Hợp lý.
- **Không có `console.log/debug/info`** trong module phát hành → sạch.
- **XSS phía client thấp**: hầu như không dùng `innerHTML` với dữ liệu người dùng
  (xem Đợt A); Alpine dùng `x-text`.
- **Dọn camera đúng**: `qrscan.js._qrTatCamera()` gọi `getTracks().forEach(t=>t.stop())`
  và `srcObject=null` khi đóng.

### 🟢 Ghi chú/đề xuất nhỏ
- **D1**: Xác nhận `_qrTatCamera()` được gọi trên **mọi đường thoát** (chuyển route/rời
  trang giữa lúc quét), không chỉ khi bấm đóng — tránh camera còn bật.
- **D2**: `core.js` 937 dòng — cân nhắc tách theo miền chức năng để dễ bảo trì.
- **D3**: 8 lời gọi `fetch` — rà từng chỗ đều có `.catch`/try-catch + thông báo lỗi cho
  người dùng (đa số module đã có; kiểm cho đủ 1:1).
- **D4** (hiệu năng, giao với G2): cân nhắc lazy-load 26 module JS theo route.

## Đợt H — UX / Trải nghiệm

> Chưa chạy được app trong môi trường này (thiếu DB) nên đánh giá dựa trên cấu trúc view.

- **Mobile-first**: có `layout_bottomnav.php`, các module thiết kế cho điện thoại — phù hợp
  người dùng chính (huynh trưởng dùng điện thoại khi điểm danh).
- **Phản hồi thao tác**: đã có toast/modal thống nhất; có `partial_heavy_loading.php`
  (trạng thái tải) và cơ chế polling `sync.txt`.
- **In ấn**: `print.php` + `partial_report_card.php` (phiếu liên lạc), `module_qrcard.php`
  (thẻ QR).
- **Đề xuất kiểm thủ công** (cần chạy app): luồng điểm danh QR hàng loạt trên điện thoại
  thật; thông báo lỗi mạng khi mất kết nối; khả năng thao tác một tay; tương phản màu chữ
  (accessibility).

## Đợt I — Tài liệu

- `CHANGES_SUMMARY.md` cập nhật 22/09/2026 (còn mới), phản ánh đợt refactor gần đây.
- Bộ `docs/` phong phú (spec phân quyền, thiết kế thư viện, thời khoá biểu, deploy AZDIGI…).
- **I1**: Sau khi **hợp nhất hệ migration** (Đợt E — E1), cập nhật `docs/HANDOFF.md` và
  hướng dẫn deploy để mô tả đúng quy trình chạy migration mới.
- **I2**: Nên thêm mục "Bảo mật vận hành" vào tài liệu: nơi đặt secret (`config.local.php`),
  quy trình xoay khoá VAPID/`setup_key` (liên hệ Đợt B).

## Kết luận Sprint 4
Frontend đạt chất lượng tốt, không phát hiện lỗi. UX và tài liệu ở mức khá; các việc còn
lại thiên về **kiểm thử thủ công trên thiết bị thật** (H) và **đồng bộ tài liệu sau khi
hợp nhất migration** (I).
