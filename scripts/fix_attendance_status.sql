-- =====================================================================
--  SỬA LẠI TRẠNG THÁI "đi trễ" / "có mặt" ĐÃ GHI SAI  (bản SQL cho phpMyAdmin)
-- =====================================================================
--  Tương đương scripts/fix_attendance_status.php nhưng chạy thẳng trong
--  phpMyAdmin (không cần SSH/CLI).
--
--  VÌ SAO: bản cũ của máy chủ tính giờ chốt = giờ bắt đầu + 30' và BỎ QUA
--  giờ chốt riêng (cutoff_time) của buổi, nên em điểm danh lúc 06:36 cho
--  buổi bắt đầu 06:00 / chốt 08:00 vẫn bị ghi "đi trễ". Bản vá đã sửa cho
--  lần ghi MỚI; các câu dưới sửa những bản ghi CŨ đã lỡ ghi sai.
--
--  CÁCH TÍNH (khớp public/api/_common.php::program_cutoff_ts):
--      marked_at >= (session_date + giờ chốt)  ->  'đi trễ'
--      marked_at <  (session_date + giờ chốt)  ->  'có mặt'
--      giờ chốt = cutoff_time nếu có, ngược lại coi start_time là mốc.
--
--  ‼️ LÀM THEO THỨ TỰ:
--    0) SAO LƯU DB trước (phpMyAdmin → Export → Go). Bắt buộc.
--    1) ĐẶT ĐÚNG "Giờ tính đi trễ" (cutoff_time) cho TỪNG chương trình
--       trong app TRƯỚC. Chương trình nào còn bỏ trống cutoff_time thì
--       câu dưới dùng start_time làm mốc (ai bấm từ đúng giờ bắt đầu trở
--       đi là 'đi trễ') — thường KHÔNG phải điều bạn muốn.
--    2) Chạy câu [1] và [2] (CHỈ XEM) để biết sẽ đổi bao nhiêu, có đúng không.
--    3) Chạy câu [3] (SỬA THẬT).
--    4) Chạy câu [4] (KIỂM) — phải ra 0.
--
--  Idempotent: chạy lại vô hại, lần sau không còn gì để sửa.
-- =====================================================================


-- ---------------------------------------------------------------------
-- [1] XEM TRƯỚC — danh sách bản ghi sẽ bị đổi (chưa ghi gì)
--     Có thể thu hẹp: bỏ chú thích các dòng AND bên dưới.
-- ---------------------------------------------------------------------
SELECT a.id,
       a.session_date                                         AS ngay,
       p.name                                                 AS chuong_trinh,
       TIME(a.marked_at)                                      AS gio_bam,
       COALESCE(p.cutoff_time, p.start_time)                  AS gio_chot,
       a.status                                               AS hien_tai,
       IF(a.marked_at >= TIMESTAMP(a.session_date, COALESCE(p.cutoff_time, p.start_time)),
          'đi trễ', 'có mặt')                                 AS dung_ra
  FROM attendances a
  JOIN programs p ON p.id = a.program_id
 WHERE a.status <> IF(a.marked_at >= TIMESTAMP(a.session_date, COALESCE(p.cutoff_time, p.start_time)),
                      'đi trễ', 'có mặt')
   -- AND a.session_date BETWEEN '2026-09-01' AND '2026-10-05'   -- lọc theo ngày (tuỳ chọn)
   -- AND a.program_id = 3                                        -- chỉ một chương trình (tuỳ chọn)
 ORDER BY a.session_date, a.id;


-- ---------------------------------------------------------------------
-- [2] XEM TRƯỚC — đếm tổng, chia theo chiều đổi
-- ---------------------------------------------------------------------
SELECT
    SUM(a.status = 'đi trễ'  AND dung_ra = 'có mặt') AS tre_thanh_comat,
    SUM(a.status = 'có mặt'  AND dung_ra = 'đi trễ') AS comat_thanh_tre,
    COUNT(*)                                          AS tong_sai
  FROM (
    SELECT a.status,
           IF(a.marked_at >= TIMESTAMP(a.session_date, COALESCE(p.cutoff_time, p.start_time)),
              'đi trễ', 'có mặt') AS dung_ra
      FROM attendances a
      JOIN programs p ON p.id = a.program_id
     WHERE a.status <> IF(a.marked_at >= TIMESTAMP(a.session_date, COALESCE(p.cutoff_time, p.start_time)),
                          'đi trễ', 'có mặt')
  ) a;


-- ---------------------------------------------------------------------
-- [2b] (TUỲ CHỌN) Các em thuộc buổi TÍNH MỘC bị đổi status — những em
--      này có thể được HOÀN LẠI thưởng mốc chuỗi. Chạy TRƯỚC [3] nếu
--      muốn biết ai cần tính lại Sổ Mộc. (Xem ghi chú "SỔ MỘC" cuối file.)
-- ---------------------------------------------------------------------
SELECT DISTINCT a.student_id, s.full_name
  FROM attendances a
  JOIN programs p ON p.id = a.program_id
  JOIN students s ON s.id = a.student_id
 WHERE p.count_for_emulation = 1
   AND a.status <> IF(a.marked_at >= TIMESTAMP(a.session_date, COALESCE(p.cutoff_time, p.start_time)),
                      'đi trễ', 'có mặt')
 ORDER BY s.full_name;


-- ---------------------------------------------------------------------
-- [3] SỬA THẬT  — chạy sau khi đã xem [1]/[2] và thấy đúng.
--     (Nếu phpMyAdmin báo lỗi 1175 "safe update mode", bỏ chú thích dòng SET.)
-- ---------------------------------------------------------------------
-- SET SQL_SAFE_UPDATES = 0;

UPDATE attendances a
  JOIN programs p ON p.id = a.program_id
   SET a.status = IF(a.marked_at >= TIMESTAMP(a.session_date, COALESCE(p.cutoff_time, p.start_time)),
                     'đi trễ', 'có mặt')
 WHERE a.status <> IF(a.marked_at >= TIMESTAMP(a.session_date, COALESCE(p.cutoff_time, p.start_time)),
                      'đi trễ', 'có mặt')
   -- AND a.session_date BETWEEN '2026-09-01' AND '2026-10-05'   -- phải KHỚP bộ lọc đã xem ở [1]
   -- AND a.program_id = 3
;


-- ---------------------------------------------------------------------
-- [4] KIỂM — phải ra 0. Còn > 0 nghĩa là còn bản ghi lệch (xem lại bộ lọc).
-- ---------------------------------------------------------------------
SELECT COUNT(*) AS con_sai
  FROM attendances a
  JOIN programs p ON p.id = a.program_id
 WHERE a.status <> IF(a.marked_at >= TIMESTAMP(a.session_date, COALESCE(p.cutoff_time, p.start_time)),
                      'đi trễ', 'có mặt');


-- =====================================================================
--  SỔ MỘC (điểm thưởng) — ĐỌC KỸ
-- =====================================================================
--  Đổi 'đi trễ' <-> 'có mặt' KHÔNG đổi Mộc "đi lễ" (đi trễ vẫn được Mộc).
--  Chỉ ảnh hưởng THƯỞNG MỐC CHUỖI (3->+1, 7->+3, 30->+15): mốc rơi đúng
--  ngày bị ghi nhầm "đi trễ" thì trước đây bị mất thưởng; sửa về "có mặt"
--  đáng lẽ được hoàn lại. Tức là: sau khi sửa status, chỉ có thể có em
--  ĐƯỢC CỘNG THÊM vài Mộc, KHÔNG ai bị trừ oan.
--
--  Các câu SQL trên KHÔNG tự tính lại tem (phần chuỗi quá phức tạp để làm
--  an toàn bằng SQL thuần). Cách cho đúng số Mộc ngay:
--    • Chạy script PHP: `php scripts/fix_attendance_status.php --apply`
--      (nó sửa status + TỰ tính lại Sổ Mộc). Cần chạy được PHP CLI/cron
--      trên host (cPanel → Cron Jobs/Terminal). Script idempotent nên chạy
--      sau khi đã chạy SQL cũng vô hại — nó chỉ còn việc tính lại tem.
--    • Hoặc để tự lành dần: lần tới có thao tác điểm danh (thêm/gỡ) của
--      em đó trên một buổi tính Mộc, app sẽ tự recalc_stamps cho em.
--  Danh sách em cần để ý: câu [2b] ở trên.
-- =====================================================================
