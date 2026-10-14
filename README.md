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

## 冻结决策（M0）

| 项 | 决策 |
| --- | --- |
| 后端 | ThinkPHP 8.1 + MySQL |
| H5 | Vue 3 + Vant 4 |
| Admin | Vue 3 + Naive UI |
| 登录 | 邮箱登录 |
| 支付 | 模拟支付 |
| 一期后端 | 2.7 / 2.8 |
| 秒杀 / 分销 | 一期 Mock |
| 语言 | `zh-HK` / `en` |
| 里程碑 | M0–M9（见 `docs/工程規劃/01-里程碑M0-M9.md`） |

**M0 已关闭**：仅模具与冻结文档；业务实现从 M1 开始。

## 文档入口

- [产品范围冻结](docs/需求分析/00-产品范围冻结.md)
- [技术栈冻结](docs/工程規劃/00-技术栈冻结.md)
- [里程碑 M0–M9](docs/工程規劃/01-里程碑M0-M9.md)
- [接口约定](docs/接口/00-接口约定.md)
- [验收基线](docs/验收/00-验收基线.md)
- [M0 关闭记录](docs/验收/M0-关闭记录.md)
