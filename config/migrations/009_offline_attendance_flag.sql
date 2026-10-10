-- Migration 009: Đánh dấu điểm danh offline
-- Để BĐH rà soát: những em được ghi "có mặt" khi offline
-- nhưng server tính phải là "đi trễ" (đã qua giờ chốt)

ALTER TABLE attendances
    ADD COLUMN offline_marked TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = ghi offline khi chưa tới giờ chốt, để BĐH rà soát';
