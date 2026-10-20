SisterMart 本次上線 SQL（2026-10-19）— 已重新生成

════════════════════════════════════════════════════════
【推薦一次執行】deploy_all_gaps.sql
  - 一次補齊：簽到表、訂閱表、收藏表、members.avatar_url
  - 可重複執行，安全
  - phpMyAdmin：選中業務庫 → 導入本檔
  - 命令列：mysql -u帳號 -p 資料庫名 < deploy_all_gaps.sql
════════════════════════════════════════════════════════

【程式全量包】releases/sistermart-vps-root.zip
  - 解壓到網站根：unzip -o sistermart-vps-root.zip
  - 含 H5 /admin /mock /products /api /sm-backend（不含 .env）
  - 解壓後請硬刷新；確保伺服器已有 sm-backend/.env

【SQL 壓縮包】releases/sistermart-sql-2026-10-19.zip
  - 內含本目錄全部 .sql + README

【分項腳本】（已含在 deploy_all_gaps.sql，一般不必單獨跑）
  - m14_checkin.sql …… 簽到日曆 500
  - m27_newsletter.sql … 訂閱 me/subscribe 500
  - m28_favorites_avatar.sql … 頭像／收藏

【必須執行】deploy_catalog_attrs.sql
  - 角標主數據、商品參數（材質等）表結構 + 種子
  - 可重複執行

【必須執行】deploy_tabbar_active.sql
  - 底部 Tab 選中點亮（深色圓底 + 實心圖標）
  - 可重複執行

【若 VPS 從未跑過早期腳本，才需要（此前已給過）】
  - deploy_m28_m32.sql
  - deploy_m11_m19_fix.sql
  - deploy_vps_schema_gaps.sql
  若首頁 banners / 秒殺 / 消息廣播 / 評價已正常，可跳過。
