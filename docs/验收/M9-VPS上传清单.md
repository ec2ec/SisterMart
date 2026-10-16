# M9 VPS 上傳清單（素材英文化 + catalog 圖標 + H5 重編）

> 遠程 FTP 根目錄 = 網站根目錄（`https://sistermart.vip/`）  
> 本機來源：倉庫分支 `cursor/h5-ui-from-design-fc0f`

## 必須上傳（修復頂部輪播 + 分類圖標 + 商品圖）

### 1) 整包覆蓋 H5 靜態（推薦）

從本機 `apps/h5/dist/` 上傳到 VPS 根目錄：

| 本機 | 遠程 |
| --- | --- |
| `apps/h5/dist/index.html` | `/index.html` |
| `apps/h5/dist/assets/*`（含新 JS，如 `index-*.js`） | `/assets/` |
| `apps/h5/dist/mock/*`（全部英文檔名） | `/mock/` |
| `apps/h5/dist/favicon.svg` 等 | `/`（可選覆蓋） |

或直接解壓最新 `releases/sistermart-vps.tgz` 後上傳其中的 `index.html`、`assets/`、`mock/`。

### 2) `/mock/` 必須包含這些檔

**輪播**
- `home-banner-1.png`
- `home-banner-2.png`

**分類圖標（catalog）**
- `cat-necklace.png`
- `cat-bracelet.png`
- `cat-earrings.png`
- `cat-ring.png`
- `cat-headscarf.png`
- `cat-bag.png`

**商品圖**
- `product-1.jpg` … `product-4.jpg` 及對應 `product-*-detail*.jpg`

本機完整目錄：`docs/design-assets/mock-images/`（與 `apps/h5/dist/mock/` 內容一致）。

## 已在遠程完成（無需你再操作）

- MySQL `categories.icon` 一級分類已改為 `/mock/cat-*.png`（本地 + 遠程）
- API / 登錄 / health 已可用

## 可選

| 本機 | 遠程 | 說明 |
| --- | --- | --- |
| `apps/h5/public/products/cat-*.png` | `/products/` | 僅當仍有舊路徑引用時 |
| `releases/vps-hotfix/api-index.php` | `/api/index.php` | 若 API 入口又變空檔 |

## 上傳後自檢

1. https://sistermart.vip/ — 頂部輪播應請求 `/mock/home-banner-1.png`（非中文檔名）
2. https://sistermart.vip/mock/home-banner-1.png → 200
3. https://sistermart.vip/mock/cat-necklace.png → 200
4. 分類頁左側圖標正常
5. 瀏覽器硬刷新（Ctrl+F5）避免舊 JS 緩存
