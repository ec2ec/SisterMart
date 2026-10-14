# apps/h5

SisterMart 用户端 H5 — **Vue 3 + Vant 4**。

## 页面组织

### Tab 主壳（底栏）

| 路由 | 页面 |
| --- | --- |
| `/` | 首页（轮播顶铺 + 搜索浮层） |
| `/category` | 分类 |
| `/cart` | 购物车（暂按现实现，后续再补） |
| `/account` | 我的 |

### 二级页（可跳转 Mock）

| 路由 | 页面 |
| --- | --- |
| `/search` | 搜索 |
| `/product/:id` | 商品详情 |
| `/products` | 商品列表 |
| `/login` `/register` | 登录 / 注册 |
| `/wishlist` | 收藏 |
| `/messages` | 消息 |
| `/orders` | 订单列表 |

## 约定

- 默认语言 `zh-HK`；点 Logo 或「我的」内切换语言；**同一界面不中英混排**
- Logo 使用文字 `SisterMart`
- 数据为 Mock；接口联调后续里程碑

## 启动

```bash
pnpm install
pnpm dev
```
