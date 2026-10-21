SisterMart 本次上線 SQL（2026-10-21）— PR #55 A2.3 / A5.2 / 積分商城

════════════════════════════════════════════════════════
【推薦一次執行】deploy_a23_a52.sql
  - 一次補齊：points_mall_items、orders.points_mall_item_id、
    withdrawals.reviewed_by_admin_id
  - 可重複執行，安全
  - phpMyAdmin：選中業務庫 → 導入本檔
  - 命令列：mysql -u帳號 -p 資料庫名 < deploy_a23_a52.sql
════════════════════════════════════════════════════════

【程式全量包】releases/sistermart-vps-root.zip
  - 解壓到網站根：unzip -o sistermart-vps-root.zip
  - 含 H5 /admin /mock /products /api /sm-backend（不含 .env）
  - 解壓後請硬刷新；確保伺服器已有 sm-backend/.env

【SQL 壓縮包】releases/sistermart-sql-2026-10-21.zip
  - 內含本目錄全部 .sql + README

【分項腳本】（已含在 deploy_a23_a52.sql，一般不必單獨跑）
  - deploy_points_mall.sql …… 積分商城表（修復列表 500）
  - deploy_withdrawals_reviewer.sql … 提現審核人欄

【說明】
  - A2.3 賬單 type 篩選無 DB 變更，覆蓋 API／H5 即可
  - 積分商城種子 pm1/pm2：覆蓋 API 後訪問一次列表亦可自愈寫入

════════════════════════════════════════════════════════
【分銷 500 修復】deploy_m13_distribution.sql（2026-10-21 補）
  - 根因：VPS 缺少 commissions / withdrawals 等 M13 表
  - 影響：/api/v1/distribution/overview|orders|commissions → 500
  - 可重複執行
════════════════════════════════════════════════════════
