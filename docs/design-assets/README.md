# Design assets

Runtime mock product/banner images live in `mock-images/` and are served by H5 Vite as `/mock/*`.

## favicon

源文件：`docs/design-assets/favicon/`（與 `docs/設計素材/favicon/` 同步）。
應用位置：`apps/h5/public/`、`apps/admin/public/`（根路徑，配合 `site.webmanifest`）。

## Catalog icons (JPG)

一級分類圖標：`mock-images/cat-{bracelet,necklace,earrings,ring,headscarf,bag}.jpg`  
運行時路徑：`/mock/cat-*.jpg`（DB `categories.icon`）。舊 PNG 已移除。
