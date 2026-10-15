# VPS hotfix（代碼已上傳後）

遠程庫已同步（39 表）。當前阻塞：

1. `sm-backend/.env` 為空（0 bytes）
2. 站點 PHP 為 **8.1.32**，Composer 要求 **>= 8.2**

## 請用本機 FTP 覆蓋這 3 個檔（或至少前 2 個）

| 本地檔 | 上傳到 VPS |
| --- | --- |
| `api-index.php` | `/api/index.php` |
| `dotenv` | `/sm-backend/.env` |
| `platform_check.php`（可選） | `/sm-backend/vendor/composer/platform_check.php` |

更推薦：在主機面板把網站 PHP 版本切到 **8.2 / 8.3 / 8.4**，再覆寫 `.env`。

覆蓋後訪問：

- https://sistermart.vip/api/index.php/api/v1/health
