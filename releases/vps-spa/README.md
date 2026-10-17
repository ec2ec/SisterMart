# VPS SPA 深鏈 404 修復

## 現象

`https://sistermart.vip/` 正常，但直接打開 `/login`、`/category`、`/payment/44` 等返回 **nginx 404**。

原因：前端是 Vue History 路由，伺服器要把「不存在的路徑」回退到 `index.html`。

## 必做（遠程是 nginx）

在面板為 `sistermart.vip` 開啟偽靜態 / 自定義 Nginx，貼上同目錄 `nginx-伪静态.conf` 內容後重載。

驗證：

```bash
curl -sI https://sistermart.vip/login | head -1    # 期望 200
curl -sI https://sistermart.vip/payment/1 | head -1 # 期望 200
curl -sI https://sistermart.vip/admin/login | head -1
```

## 可選

若面板走 Apache，把倉庫根目錄 `.htaccess`（H5 build 產物）與 `admin/.htaccess` 放到網站對應目錄即可。
