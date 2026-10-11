-- Migration 009: Đánh dấu điểm danh offline
-- Cờ này = 1 khi bản ghi được tạo SAU giờ chốt (pastCutoff),
-- giúp BĐH rà soát các trường hợp đi trễ ghi muộn.

ALTER TABLE attendances
    ADD COLUMN offline_marked TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = ghi khi đã qua giờ chốt, có thể cần BĐH rà soát';
