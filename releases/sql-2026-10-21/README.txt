SisterMart 本次上線 SQL（2026-10-21）— PR #55 A2.3 / A5.2 / 積分商城 / M13 分銷

════════════════════════════════════════════════════════
【推薦一次執行 — 分銷 500 修復】deploy_m13_distribution.sql
  - 補齊：commissions、withdrawals、commission_clawbacks
  - 及 members.commission_available、products.commission_* 等
  - 可重複執行，安全
  - phpMyAdmin：選中業務庫 → 導入本檔
  - 命令列：mysql -u帳號 -p 資料庫名 < deploy_m13_distribution.sql
════════════════════════════════════════════════════════

【積分商城 + 提現審核人】deploy_a23_a52.sql
  - points_mall_items、orders.points_mall_item_id
  - withdrawals.reviewed_by_admin_id

【程式全量包】releases/sistermart-vps-root.zip
  - 解壓到網站根：unzip -o sistermart-vps-root.zip

【SQL 壓縮包】releases/sistermart-sql-2026-10-21.zip
  - 內含本目錄全部 .sql + README

【分項腳本】
  - deploy_points_mall.sql …… 積分商城表
  - deploy_withdrawals_reviewer.sql … 提現審核人欄
  - deploy_m13_distribution.sql … 分銷全量 schema（優先）
