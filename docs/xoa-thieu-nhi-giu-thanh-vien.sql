-- =====================================================================
--  XÓA TOÀN BỘ THIẾU NHI — GIỮ NGUYÊN THÀNH VIÊN (GLV)
--  Gia Đình Giáo Lý Phú Trung · dùng khi danh sách thiếu nhi đã upload SAI,
--  muốn xóa trắng để nhập lại, nhưng giữ nguyên các thành viên (GLV).
--
--  ⚠️  SAO LƯU DATABASE TRƯỚC KHI CHẠY!  ⚠️
--      phpMyAdmin -> chọn DB -> tab Export -> Go -> lưu file .sql.
--      Thao tác dưới đây XÓA VĨNH VIỄN, KHÔNG hoàn tác được.
--
--  CÁCH CHẠY:
--      1) Vào phpMyAdmin.
--      2) Ở cột trái, BẤM CHỌN ĐÚNG DATABASE THẬT (KHÔNG chọn bản demo).
--      3) Tab "Import" -> chọn file này -> Go.
--         (Hoặc: tab "SQL" -> dán toàn bộ nội dung -> Go, để thấy rõ số đếm.)
--
--  XÓA : thiếu nhi + điểm danh + điểm + phiếu liên lạc + ghi danh + xin phép
--        (mọi dữ liệu gắn với thiếu nhi TỰ XÓA THEO nhờ khóa ngoại CASCADE).
--  GIỮ : thành viên (GLV) + tài khoản đăng nhập + vân tay/FaceID (passkey)
--        + khối/lớp + chương trình + thông báo + phân công + cài đặt.
--
--  MUỐN XEM THỬ (không xóa thật): đổi dòng  COMMIT;  ở dưới thành  ROLLBACK;
--  -> chạy sẽ hiện số đếm "sau khi xóa" nhưng dữ liệu KHÔNG mất. Ưng thì đổi
--  lại thành COMMIT; và chạy lần nữa.
-- =====================================================================

START TRANSACTION;

-- (1) Đếm TRƯỚC khi xóa — để đối chiếu.
SELECT 'TRUOC KHI XOA' AS trang_thai,
       (SELECT COUNT(*) FROM students)   AS thieu_nhi,
       (SELECT COUNT(*) FROM members)    AS thanh_vien,
       (SELECT COUNT(*) FROM attendances) AS diem_danh,
       (SELECT COUNT(*) FROM scores)     AS diem;

-- (2) XÓA thiếu nhi. Điểm danh / điểm / phiếu / ghi danh / xin phép của các
--     em này tự xóa theo (ON DELETE CASCADE). Bảng members KHÔNG bị đụng.
DELETE FROM students;

-- (3) Đếm SAU khi xóa — kỳ vọng: thieu_nhi = 0, thanh_vien GIỮ NGUYÊN.
SELECT 'SAU KHI XOA' AS trang_thai,
       (SELECT COUNT(*) FROM students)   AS thieu_nhi,
       (SELECT COUNT(*) FROM members)    AS thanh_vien,
       (SELECT COUNT(*) FROM attendances) AS diem_danh_con_lai,
       (SELECT COUNT(*) FROM scores)     AS diem_con_lai;

-- Đổi COMMIT thành ROLLBACK nếu chỉ muốn XEM THỬ (không xóa thật).
COMMIT;

-- (4) Tùy chọn: cho id thiếu nhi bắt đầu lại từ 1 ở lần nhập danh sách mới.
--     (Mã GDGLPT... vốn tự tính từ số lớn nhất nên đã tự về 0001; dòng này
--      chỉ để id gọn, không bắt buộc.)
ALTER TABLE students AUTO_INCREMENT = 1;
