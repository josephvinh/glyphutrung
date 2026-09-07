# Bảng Thi Đua (công khai) — Thiết kế

**Mục tiêu:** Trang xếp hạng thi đua **tự động, chỉ xem**, tính từ dữ liệu thật
(điểm danh + điểm số) để tạo hứng thú, vinh dự, tự hào cho các em. Không ai chấm
tay → luôn khớp dữ liệu.

**Quyết định của chủ đoàn (đã chốt):**
- Công khai: `public/bxh.php`, **không cần đăng nhập**, ai có link đều xem.
- Hiện **họ tên đầy đủ** + lớp + điểm. KHÔNG hiện ngày sinh/điện thoại/mã.
- Phạm vi **toàn đoàn** + bộ lọc theo khối/lớp ngay trên trang.
- An toàn thêm (không cản người có link): `noindex` để Google không lập chỉ mục.

## Dữ liệu nguồn (không thêm bảng mới)
- `attendances(year_id, session_date, student_id, status)` — status:
  `có mặt`, `đi trễ`, `vắng có phép`, `vắng không phép`.
- `scores(term_id, student_id, type_code, value)` + `score_types(code, weight)`
  — điểm thang 10, trung bình **có trọng số** theo `weight`.
- `enrollments(year_id, student_id, class_id, status='đang sinh hoạt')`,
  `students(holy_name, full_name)`, `classes(name, block_id)`, `blocks(name)`.
- Niên khoá hiện tại: `school_years WHERE is_current=1`. Học kỳ hiện tại: `terms`
  chứa ngày hôm nay (else kỳ mới nhất của niên khoá).

## Công thức (in minh bạch trên trang)
- **Tuần này** (chuyên cần các buổi trong tuần ISO hiện tại):
  `Có mặt +10 · Đi trễ +6 · Vắng có phép +3 · Vắng không phép 0`.
- **Học kỳ** (thang 100): `0.6 × TyLeCoMat(%) + 0.4 × HocTap100`
  - `TyLeCoMat` = (số buổi có mặt hoặc đi trễ) / (số buổi đã điểm danh) × 100.
  - `HocTap100` = trung bình có trọng số điểm trong kỳ (thang 10) × 10, kẹp 0..100.
  - Em chưa có điểm học tập → phần học tập = 0 (khuyến khích lấy điểm).
- **Xếp hạng lớp** = **trung bình** điểm các em trong lớp (công bằng lớp đông/ít).

## Kiến trúc
- `config/thi_dua.php` — **hàm thuần, test được** (không đụng DB):
  - `td_diem_tuan(int $coMat, int $diTre, int $coPhep): int`
  - `td_hoc_tap_100(array $scores): float` — `$scores` = list `['value'=>float,'weight'=>int]`
  - `td_diem_ky(float $tyLeCoMat100, float $hocTap100): float`
  - `td_xep_hang(array $rows, string $key): array` — sắp giảm dần, gán `rank`
    (đồng hạng chuẩn thi đấu) + `medal` (1→vàng,2→bạc,3→đồng).
- `public/bxh.php` — trang công khai:
  - Không auth. `require config/db.php` + `config/thi_dua.php`.
  - Đọc lọc từ `$_GET`: `period` (`tuan`|`ky`), `type` (`ca_nhan`|`lop`),
    `khoi` (id), `lop` (id) — **ép kiểu số**, truy vấn **prepared**.
  - Truy vấn active students trong phạm vi + điểm danh (tuần/kỳ) + điểm (kỳ) →
    gọi hàm thuần → render.
  - Header `X-Robots-Tag: noindex`, meta robots noindex. Read-only tuyệt đối.
- **Lối vào trong app:** một nút/link "🏆 Bảng thi đua" (mở `bxh.php` tab mới).
  Không làm module phức tạp (YAGNI).

## Thiết kế hiển thị (vinh dự / tự hào)
- **Bục vinh danh** top 3: hạng nhất giữa, cao nhất, 👑 + huy chương 🥇🥈🥉,
  vòng tròn chữ cái tên, điểm to.
- **Bảng xếp hạng đầy đủ** bên dưới: hạng, họ tên, lớp, điểm + thanh tiến độ,
  huy chương top 3.
- Điểm nhấn: **Em của tuần / Lớp xuất sắc**; màu vàng-kim lễ hội; hiệu ứng nhẹ
  (confetti cho hạng nhất); hộp giải thích công thức.
- Bộ lọc: Kỳ (Tuần/Học kỳ) · Loại (Cá nhân/Lớp) · Khối/Lớp. Mobile-first,
  CSS/JS nội tuyến (trang tự chứa, không cần tài sản đăng nhập).

## Bảo mật / riêng tư
- Không auth nhưng: prepared statements, ép kiểu tham số lọc, chỉ đọc, chỉ lộ
  tên+lớp+điểm (không PII khác), `noindex`. Không nhận bất kỳ input ghi nào.

## Kiểm thử
- Unit (PHPUnit) cho `thi_dua.php`: điểm tuần, học tập có trọng số, điểm kỳ,
  xếp hạng + đồng hạng + huy chương.
- E2E: mở `bxh.php`, thấy podium + bảng + lọc chạy; đổi lọc ra kết quả đúng.

## Ngoài phạm vi (làm sau)
- Cổng phụ huynh, "Em của tuần" gửi thông báo, tuỳ chỉnh trọng số trong Cài đặt.
- Mục 6 (Lịch trực) là feature riêng, làm sau khi Thi đua xong.
