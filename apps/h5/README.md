# apps/h5

SisterMart 用户端 H5 — **Vue 3 + Vant 4**，按 `refs/原型/UI參考圖` 设计还原主壳页面。

## 页面

| 路由 | 页面 |
| --- | --- |
| `/` | 首页（轮播 / 促销条 / 秒杀 / 分类圆标 / 系列 / Tab 商品 / 订阅 / 页脚） |
| `/category` | 分类（左侧边栏 + 右侧子类网格） |
| `/cart` | 购物车（信任条 / 管理 / 结算栏） |
| `/account` | 我的（资产 / 订单入口 / 签到 / 服务宫格） |

点击顶栏 **SisterMart** Logo 或「我的」页设置图标可切换 `zh-HK` / `en`。

## 启动

```bash
pnpm install
cp .env.example .env
pnpm dev
```

默认：`http://127.0.0.1:5173`

## 说明

- 商品与账号数据为 **Mock**（对齐 UI 效果图，接口联调后续里程碑）
- UI 参考图：`refs/原型/UI參考圖/`
