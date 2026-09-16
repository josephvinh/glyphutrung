-- =====================================================================
-- SỬA: vai "Dự Bị" bị sai mã hoá (mojibake) do migration cũ
--   (migrate_roles_du_bi.php chạy với kết nối không đúng charset).
--   - roles.label/descr bị hỏng + scope rỗng (đáng lẽ 'lớp').
--   - titles có một dòng "Dự Bị" bị hỏng, trùng với bản đúng.
-- Chạy được nhiều lần.
-- =====================================================================

UPDATE roles
   SET label = 'Dự Bị',
       descr = 'Hỗ trợ tại lớp được phân công',
       scope = 'lớp'
 WHERE code = 'du_bi';

-- Xoá chức danh "Dự Bị" bị hỏng (giữ bản đúng), nếu không thành viên nào đang dùng.
DELETE FROM titles
 WHERE role_code = 'du_bi'
   AND label <> 'Dự Bị'
   AND id NOT IN (SELECT title_id FROM members WHERE title_id IS NOT NULL);
