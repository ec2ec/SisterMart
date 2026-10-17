# scripts

| 脚本 | 用途 |
| --- | --- |
| `dev-api.sh` | 启动 ThinkPHP 内置服务器（默认 `:8000`） |
| `dev-h5.sh` | 启动 H5 Vite（默认 `:5173`） |
| `dev-admin.sh` | 启动 Admin Vite（默认 `:5174`） |
| `m1-verify.sh` | M1 验收：健康检查 JSON + 两端 `pnpm build` |

示例：

```bash
bash scripts/dev-api.sh
bash scripts/dev-h5.sh
bash scripts/dev-admin.sh
bash scripts/m1-verify.sh
```
