-- A5.2：提現審核人審計欄（可單獨在 VPS 執行；API 亦會冪等 ensure）
ALTER TABLE withdrawals
  ADD COLUMN reviewed_by_admin_id BIGINT UNSIGNED NULL AFTER reviewed_at;
