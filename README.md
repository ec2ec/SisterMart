# SisterMart

Monorepo：用户端 H5、管理后台、ThinkPHP API。

仓库：`https://github.com/ec2ec/SisterMart.git`

## 目录

```text
apps/api      # ThinkPHP 8.1 + MySQL
apps/h5       # Vue3 + Vant4
apps/admin    # Vue3 + NaiveUI
docs/         # 需求 / 工程 / 接口 / 验收
refs/         # 外部参考
scripts/      # 工程脚本
```

## 快速启动（M1 默认）

前置：PHP ≥8.0（建议 8.3）、Composer、Node 22、pnpm、MySQL。

```bash
# 1) MySQL（示例）
mysql -e "CREATE DATABASE IF NOT EXISTS sistermart CHARACTER SET utf8mb4;
CREATE USER IF NOT EXISTS 'sistermart'@'localhost' IDENTIFIED BY 'sistermart';
GRANT ALL ON sistermart.* TO 'sistermart'@'localhost'; FLUSH PRIVILEGES;"

# 2) API
cd apps/api && composer install && cp .example.env .env && php think run -H 0.0.0.0 -p 8000

# 3) H5（另开终端）
cd apps/h5 && pnpm install && cp .env.example .env && pnpm dev

# 4) Admin（另开终端）
cd apps/admin && pnpm install && cp .env.example .env && pnpm dev
```

或使用脚本：`bash scripts/dev-api.sh` / `dev-h5.sh` / `dev-admin.sh`。

### 生產構建 + VPS zip

在倉庫根目錄：

```bash
pnpm build          # 只構建 H5 + Admin
pnpm build zip      # 構建並打 releases/sistermart-vps-root.zip（完整根目錄包）
pnpm zip            # 僅用現有 dist 打包
```

解壓到網站根：`unzip -o sistermart-vps-root.zip`（含 H5、`/admin`、`/mock`、`/products`、`/api`、`/sm-backend`；不含 `.env`）。

健康檢查：`curl -s http://127.0.0.1:8000/health`

验证：`bash scripts/m1-verify.sh`（需 API 已启动）。

| 端 | 默认地址 |
| --- | --- |
| API | http://127.0.0.1:8000 |
| H5 | http://127.0.0.1:5173 |
| Admin | http://127.0.0.1:5174 |

## 里程碑状态

- **M0–M8**：已通过（见 `docs/验收/`）
- **M9**：测试环境发布包已就绪 → [M9-验收记录](docs/验收/M9-验收记录.md) · [部署文档](docs/验收/M9-部署与环境变量.md) · [演示账号](docs/验收/M9-演示账号.md)

### 演示账号（测试）

| 端 | 账号 | 密码 |
| --- | --- | --- |
| H5 | `demo@sistermart.local` | `demo123456` |
| Admin 超管 | `admin@sistermart.local` | `admin123456` |
| Admin 运营 | `ops@sistermart.local` | `ops123456` |

## 真源与必读

1. [会话交接与共识索引](docs/工程規劃/00-会话交接与共识索引.md)
2. [完整 PRD v1.4](docs/需求分析/SisterMart-H5-商城產品需求文檔-PRD.md)
3. [决策冻结总表](docs/工程規劃/01-决策冻结总表.md)
4. [里程碑门禁详细版](docs/工程規劃/05-里程碑拆分与验收门禁-详细版.md)

## 冻结决策（摘要）

| 项 | 决策 |
| --- | --- |
| 后端 | ThinkPHP 8.1.x + MySQL |
| H5 | Vue 3 SPA + Vant 4 |
| Admin | Vue 3 + Naive UI |
| 登录 | 邮箱为主；WhatsApp 接口占位、不实发 |
| 支付 | 模拟支付（真渠道二期） |
| 语言 | `zh-HK` / `en` |
