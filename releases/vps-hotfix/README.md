# VPS hotfix（代碼已上傳後）

遠程庫已同步（39 表）。Cloud Agent 側 **FTP 數據通道 STOR 會超時**（出口 IP 輪換），請用**本機 FTP**覆蓋。

## 當前遠程 SIZE（控制通道，2026-10-15 再探）

| 路徑 | SIZE | 狀態 |
| --- | --- | --- |
| `sm-backend/.env` | **187** | 已就緒（與本目錄 `dotenv` 一致） |
| `sm-backend/vendor/composer/platform_check.php` | **0** | 空檔≈停用檢查，可暫不覆寫 |
| `api/index.php` | **0** | **必須覆寫**（否則 API 全空 200） |

## 請本機 FTP 覆蓋（至少第 1 個）

| 本地檔 | 上傳到 VPS |
| --- | --- |
| `api-index.php` | `/api/index.php`（823 bytes） |
| `dotenv` | `/sm-backend/.env`（若 SIZE≠187 再覆寫） |
| `platform_check.php`（可選） | `/sm-backend/vendor/composer/platform_check.php` |

面板 PHP 請保持 **≥8.2（你已切 8.4）**。

覆蓋後訪問：

- https://sistermart.vip/api/index.php/api/v1/health  
  期望 JSON：`code=0` 且 db connected
