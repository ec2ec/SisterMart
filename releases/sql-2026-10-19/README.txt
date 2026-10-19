SisterMart 本次上線 SQL（2026-10-19）

【程式全量包】releases/sistermart-vps-root.zip
  - 解壓到網站根：unzip -o sistermart-vps-root.zip
  - 含 H5 /admin /mock /products /api /sm-backend（不含 .env）
  - 解壓後請硬刷新；確保伺服器已有 sm-backend/.env

【若訂閱／newsletter/me 500】m27_newsletter.sql
  - 用途：建立 newsletter_subscribers 表（可重複）
  - 新版 API 會嘗試自動建表；無權限時請手動執行

【若簽到日曆 500】m14_checkin.sql
  - 用途：建立 member_checkins 表（可重複）
  - 新版 API 會嘗試自動建表；無權限時請手動執行本腳本

【若頭像上傳 500／缺少 avatar_url】m28_favorites_avatar.sql
  - 用途：member_favorites 表 + members.avatar_url 欄位（可重複）
  - 頭像功能依賴此腳本；新版 API 也會嘗試自動 ALTER，並用原生 UPDATE 繞過 ThinkPHP 欄位緩存
  - 若仍見「服務暫時不可用」：先執行本 SQL，再覆蓋 sm-backend（Uploads.php）並硬刷新 H5

【必須執行】deploy_catalog_attrs.sql
  - 用途：角標主數據、商品參數（材質等）表結構 + 種子（new/hot/editor/flash、材質示例）
  - 可重複執行，安全
  - 執行範例：
    mysql -u帳號 -p 資料庫名 < deploy_catalog_attrs.sql
    或在 phpMyAdmin 導入此檔

【必須執行】deploy_tabbar_active.sql
  - 用途：底部 Tab 選中點亮（深色圓底 + 實心圖標）
  - 可重複執行，安全
  - 執行範例：
    mysql -u帳號 -p 資料庫名 < deploy_tabbar_active.sql
    或在 phpMyAdmin 導入此檔

【若 VPS 從未跑過早期腳本，才需要（此前已給過）】
  - deploy_m28_m32.sql
  - deploy_m11_m19_fix.sql
  - deploy_vps_schema_gaps.sql
  若首頁 banners / 秒殺 / 消息廣播 / 評價已正常，可跳過。
