-- ============================================================
-- MIGRATE: Sửa quyền scope cho module Khối lớp & Danh sách
-- ============================================================
-- Mục đích : Khớp CSDL với chủ ý thiết kế:
--   - Trưởng Khối (truong_khoi) chỉ được XEM khối-lớp, không được SỬA.
--     (comment org.js:65: "Chỉ Ban Điều Hành trở lên mới sửa được.")
--   - Ban Điều Hành (bdh) được SỬA danh sách thiếu nhi.
--   - Module Nhân sự (staff) có quyền hợp lệ cho mọi vai.
--
-- Sau khi chạy, chạy: php scratch/verify-effective-permissions.php
-- để xác nhận kết quả.
-- ============================================================

-- Bước 1: Sửa quyền org — trưởng khối chỉ được xem
UPDATE permissions
   SET level = 'view'
 WHERE module_key = 'org'
   AND role_code = 'truong_khoi';

-- Bước 2: Sửa quyền students — BĐH được sửa (để phù hợp UI, GLV Chủ Nhiệm
-- và Trưởng Khối vẫn giữ quyền edit theo thiết kế gốc)
UPDATE permissions
   SET level = 'edit'
 WHERE module_key = 'students'
   AND role_code = 'bdh';

-- Bước 3: Thêm quyền staff cho các vai còn thiếu (phòng trường hợp
-- CSDL thiếu dòng và fallback mặc định trả 'none')
INSERT IGNORE INTO permissions (module_key, role_code, level)
VALUES
    ('staff', 'admin',       'edit'),
    ('staff', 'bdh',         'edit'),
    ('staff', 'truong_khoi', 'view'),
    ('staff', 'glv_chu_nhiem', 'view'),
    ('staff', 'glv',         'view'),
    ('staff', 'du_bi',       'view');

-- Bước 4: Cập nhật bản ghi nhân sự hiện có để gán block_id (nếu có trưởng khối
-- chưa được phân công vào khối nào — gán vào khối 1)
UPDATE members m
  JOIN member_assignments ma ON ma.member_id = m.id
                            AND ma.role_code = 'truong_khoi'
                            AND ma.to_date IS NULL
  LEFT JOIN blocks b ON b.name = 'Khai Tâm'
 WHERE m.block_id IS NULL
   AND ma.block_id IS NULL
   AND b.id IS NOT NULL
   AND m.role_code = 'truong_khoi'
   AND m.id NOT IN (SELECT member_id FROM member_assignments WHERE block_id IS NOT NULL AND to_date IS NULL);

-- Bước 5: Xác nhận
SELECT module_key, role_code, level FROM permissions
 WHERE module_key IN ('org', 'students', 'staff')
 ORDER BY module_key, FIELD(role_code,'admin','bdh','truong_khoi','glv_chu_nhiem','glv','du_bi');
