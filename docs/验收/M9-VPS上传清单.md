# M9 VPS 上傳清單（2026-10-16 最終包）

> 遠程 FTP 根目錄 = 網站根目錄（`https://sistermart.vip/`）  
> 本機來源：倉庫分支 `cursor/h5-ui-from-design-fc0f`  
> 本包 H5 含：支付選中 ✅+加粗、首頁資料緩衝（回退不閃白）、catalog 圓形圖標。

## 推薦：整包 zip（根目錄解壓覆蓋）

| 檔案 | 說明 |
| --- | --- |
| **`releases/sistermart-vps-root.zip`**（約 11MB） | 網站根結構；解壓即覆蓋（H5 + admin + mock + API + sm-backend） |
| **`releases/sistermart-vps-h5-delta.zip`**（約 1.3MB） | 只含本次必改：`index.html`、`/assets/index-ByFU3q6K.js`、`/assets/index-CgZMgpha.css`、`/mock/cat-*.png`。FTP 不穩時優先傳這個 |

```bash
# 下載後在 FTP 根目錄解壓（覆蓋）
unzip -o sistermart-vps-root.zip
```

包內含：`/` H5、`/admin/`、`/mock/`（輪播+分類+商品圖）、`/products/`、`/api/index.php`、`/sm-backend/`（含本機測試用 `.env`）。

## 備選：只傳 H5 靜態

從本機 `apps/h5/dist/` 上傳到 VPS 根目錄：

| 本機 | 遠程 |
| --- | --- |
| `apps/h5/dist/index.html` | `/index.html` |
| `apps/h5/dist/assets/*`（含新 JS，如 `index-*.js`） | `/assets/` |
| `apps/h5/dist/mock/*`（全部英文檔名） | `/mock/` |
| `apps/h5/dist/favicon.svg` 等 | `/`（可選覆蓋） |

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

1. https://sistermart.vip/ — 硬刷新（Ctrl+F5），頁面應載入 `/assets/index-ByFU3q6K.js`
2. 首頁 catalog 六個圖標為圓形；手鏈、項鏈有圖
3. https://sistermart.vip/mock/cat-bracelet.png 與 `/mock/cat-necklace.png` → 200
4. 支付頁選中渠道左側有勾、文字加粗
5. 從其他頁返回首頁，商品區不應先空白再出現
