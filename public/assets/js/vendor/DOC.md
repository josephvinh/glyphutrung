# Thư viện ngoài, tự lưu trên máy chủ

Không nạp từ CDN — cùng lý do đã bỏ Tailwind CDN: mất mạng ngoài là
mất chức năng, và không kiểm soát được phiên bản.

Đo thực tế từ máy chủ thật, trên đường truyền tốt:

| Lấy từ đâu | Thời gian |
|---|---|
| unpkg.com | 356 ms |
| cdn.jsdelivr.net | 94 ms |
| máy chủ mình | 9 ms |

Trên điện thoại 4G sóng yếu khoảng cách đó giãn ra thành mấy giây, vì
mỗi tên miền lạ bắt máy tra DNS rồi bắt tay TLS lại từ đầu.

| Tệp | Phiên bản | Giấy phép | Dùng để |
|---|---|---|---|
| `alpine.js` | 3.15.0 | MIT | Khung phản ứng của toàn app. Nạp `defer`. |
| `alpine-collapse.js` | 3.15.0 | MIT | Hiệu ứng mở/gập. Nạp `defer`. |
| `lucide-icons.js` | 1.34.0 (rút gọn) | ISC | Bộ icon. **Sinh tự động**, xem dưới. |
| `jsQR.min.js` | 1.4.0 | MIT | Giải mã QR từ khung hình camera. **Chỉ nạp khi cần** — trình duyệt nào có sẵn `BarcodeDetector` (Chrome, Android) thì không tải tệp này. |
| `qrcode.min.js` | 1.4.4 | MIT | Sinh mã QR để in thẻ cho thiếu nhi. Chỉ nạp khi bấm In thẻ. |

## lucide-icons.js — ĐỪNG SỬA TAY

Bản lucide đầy đủ nặng **410 KB** và chứa 2.031 icon, trong khi app chỉ
dùng **76** cái. Tệp ở đây là bản rút gọn còn **16 KB**, sinh ra bằng:

```bash
node build/tao_lucide.cjs
```

Kịch bản đó quét `views/` và `public/` tìm mọi `data-lucide="..."`,
`:data-lucide="... ? 'a' : 'b'"` và `icon: '...'`, rồi chỉ giữ đúng
những icon tìm được.

**Thêm icon mới vào giao diện thì phải chạy lại lệnh trên.** Quên chạy
thì chỗ đó trống trơn, và console in cảnh báo kèm đúng tên icon còn thiếu.

Cách gọi không đổi so với thư viện gốc: vẫn `data-lucide="tên"` và
`lucide.createIcons()`. Đã đối chiếu từng byte `outerHTML` của cả 76
icon với thư viện gốc — đường vẽ giống hệt. Khác đúng một điểm: bản gốc
để lại thuộc tính `data-lucide` trên thẻ `svg`, bản này bỏ đi, nên
những lần vẽ lại sau không phải dựng lại icon đã có.

## Phông chữ

Nằm ở `public/assets/font/`, sinh bằng `node build/tao_font.cjs`.
Be Vietnam Pro, giấy phép SIL OFL 1.1 — được phép đặt trên máy chủ riêng.
Chỉ giữ bộ ký tự `vietnamese` và `latin`; bỏ `latin-ext`.

## Cập nhật phiên bản

Tải lại từ `cdn.jsdelivr.net` đúng tên gói và phiên bản ở bảng trên,
rồi chạy lại hai kịch bản `build/tao_lucide.cjs` và `build/tao_font.cjs`.
