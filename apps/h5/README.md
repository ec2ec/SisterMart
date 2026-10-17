# apps/h5

SisterMart 用户端 H5 — **Vue 3 + Vant 4**。

## 本地启动

```bash
pnpm install
cp .env.example .env
pnpm dev
```

默认：`http://127.0.0.1:5173`（`VITE_API_BASE` 指向 API）。

## M1 范围

- Vite + Vue3 + TS 空壳 + Vant 示例页
- 可选拉取 API `/health`（API 未启动时仍可打开页面）
- **不含**业务路由 / i18n 全量文案（自 M2）
