# 窄版發佈通道（Release Channel）

繞過不穩 FTP：本機打簽名包 → HTTPS 分片上傳 → 後台一鍵發佈（快照 + 白名單覆蓋 + 可選 SQL）→ 回退上一快照。

## VPS 一次性配置

在 `sm-backend/.env` 增加：

```ini
RELEASE_ENABLED = true
RELEASE_HMAC_SECRET = <至少16字符的隨機串，與打包機相同>
# 通常可留空：偵測為 sm-backend 的父目錄（網站根）
RELEASE_SITE_ROOT =
RELEASE_MAX_BYTES = 83886080
RELEASE_ROLLBACK_FILES_ON_SQL_FAIL = true
```

Nginx 須拒絕直鏈（示例已寫入 `scripts/deploy/nginx.sistermart.conf.example`）：

```nginx
location ^~ /_releases/ { deny all; return 404; }
```

Apache 包內已帶 `/_releases/.htaccess`（Deny from all）。

**首次**仍需用面板/FTP 把含本功能的代碼部署上去，並寫入上述 `.env`。之後可用發佈通道迭代。

## 打包

```bash
export RELEASE_HMAC_SECRET='與 VPS 相同'
export RELEASE_TITLE='修復 PDP 購買欄'
export RELEASE_DESC='可選說明'
# 可選掛 SQL（須冪等）
pnpm release pack -- --migrate deploy/vps-packs/deploy_xxx.sql
# 或已有 zip：
pnpm release pack -- --skip-build --migrate path.sql
```

產出：`releases/sistermart-release-<stamp>.tar`（含 `package.zip` + `manifest.json` + 可選 `migrate.sql`）。

## 後台

設置 → **發佈通道**（僅超管）：上傳 `.tar` → 狀態 `ready` → 發佈 → 必要時「回退上一快照」。

- 回退默認**只還原文件**；勾選「同時還原數據庫」須二次確認語 `ROLLBACK_DB`。
- SQL 失敗時默認自動還原本次文件快照（DB 不自動還原）。

## 安全要點

- 功能默認關閉；無密鑰拒絕服務。
- 僅超管；包必須 HMAC；路徑白名單 + zip-slip 檢查；永不覆蓋 `.env` / runtime / storage。
- 正式對外發佈前建議再做全站安全檢查（2FA、WAF、限流等）。
