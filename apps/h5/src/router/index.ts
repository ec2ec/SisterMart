import { createRouter, createWebHashHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import CategoryView from '../views/CategoryView.vue'
import CartView from '../views/CartView.vue'
import AccountView from '../views/AccountView.vue'
import SearchView from '../views/SearchView.vue'
import ProductDetailView from '../views/ProductDetailView.vue'
import ProductListView from '../views/ProductListView.vue'
import LoginView from '../views/LoginView.vue'
import RegisterView from '../views/RegisterView.vue'
import ForgotView from '../views/ForgotView.vue'
import ArticleView from '../views/ArticleView.vue'
import WishlistView from '../views/WishlistView.vue'
import MessagesView from '../views/MessagesView.vue'
import OrdersView from '../views/OrdersView.vue'
import OrderDetailView from '../views/OrderDetailView.vue'
import ReviewView from '../views/ReviewView.vue'
import CheckoutView from '../views/CheckoutView.vue'
import PaymentView from '../views/PaymentView.vue'
import PayResultView from '../views/PayResultView.vue'
import AddressesView from '../views/AddressesView.vue'
import LogisticsView from '../views/LogisticsView.vue'
import AftersaleApplyView from '../views/AftersaleApplyView.vue'
import AftersaleDetailView from '../views/AftersaleDetailView.vue'
import AftersalesView from '../views/AftersalesView.vue'
import CouponCenterView from '../views/CouponCenterView.vue'
import MyCouponsView from '../views/MyCouponsView.vue'
import PointLogsView from '../views/PointLogsView.vue'
import BalanceLogsView from '../views/BalanceLogsView.vue'
import FootprintView from '../views/FootprintView.vue'
import MemberLevelView from '../views/MemberLevelView.vue'
import SeckillListView from '../views/SeckillListView.vue'
import SeckillDetailView from '../views/SeckillDetailView.vue'
import PointsMallView from '../views/PointsMallView.vue'
import PointsMallDetailView from '../views/PointsMallDetailView.vue'
import CheckinView from '../views/CheckinView.vue'
import DistributionView from '../views/DistributionView.vue'
import DistDownlinesView from '../views/DistDownlinesView.vue'
import DistCommissionsView from '../views/DistCommissionsView.vue'
import DistOrdersView from '../views/DistOrdersView.vue'
import DistWithdrawView from '../views/DistWithdrawView.vue'
import DistPosterView from '../views/DistPosterView.vue'
import DistRankingView from '../views/DistRankingView.vue'
import ProfileView from '../views/ProfileView.vue'
import { isAuthRouteName } from '../composables/useAuth'
import { loggedInAuthBouncePath, rememberLastTabName } from '../composables/useNavBack'
import { captureRefFromQuery } from '../composables/useReferral'
import {
  isTabRouteName,
  rememberTabScrollBeforeOverlay,
  TAB_ROUTE_NAMES,
} from '../composables/useTabShell'

export { TAB_ROUTE_NAMES }

export const router = createRouter({
  // Hash mode: shared-host nginx has no try_files yet; deep links like /payment/:id
  // would 404 on refresh. Switch back to createWebHistory after panel 伪静态 is set
  // (see releases/vps-spa/nginx-伪静态.conf).
  history: createWebHashHistory(),
  routes: [
    { path: '/', name: 'home', component: HomeView, meta: { tab: true } },
    { path: '/category', name: 'category', component: CategoryView, meta: { tab: true } },
    { path: '/cart', name: 'cart', component: CartView, meta: { tab: true } },
    { path: '/account', name: 'account', component: AccountView, meta: { tab: true } },
    { path: '/profile', name: 'profile', component: ProfileView },
    { path: '/search', name: 'search', component: SearchView },
    { path: '/product/:id', name: 'product-detail', component: ProductDetailView, meta: { overlay: true } },
    { path: '/products', name: 'product-list', component: ProductListView },
    { path: '/login', name: 'login', component: LoginView },
    { path: '/register', name: 'register', component: RegisterView },
    { path: '/forgot', name: 'forgot', component: ForgotView },
    /** overlay：從首頁進條款後返回，保留 Tab 滾動（同商詳） */
    { path: '/article/:id', name: 'article', component: ArticleView, meta: { overlay: true } },
    { path: '/wishlist', name: 'wishlist', component: WishlistView },
    { path: '/messages', name: 'messages', component: MessagesView },
    { path: '/orders', name: 'orders', component: OrdersView },
    { path: '/orders/:id', name: 'order-detail', component: OrderDetailView },
    { path: '/orders/:id/review', name: 'order-review', component: ReviewView },
    { path: '/checkout', name: 'checkout', component: CheckoutView },
    { path: '/payment/:id', name: 'payment', component: PaymentView },
    { path: '/pay-result', name: 'pay-result', component: PayResultView },
    { path: '/addresses', name: 'addresses', component: AddressesView },
    { path: '/orders/:id/logistics', name: 'logistics', component: LogisticsView },
    { path: '/orders/:id/aftersale', name: 'aftersale-apply', component: AftersaleApplyView },
    { path: '/aftersales', name: 'aftersales', component: AftersalesView },
    { path: '/aftersales/:id', name: 'aftersale-detail', component: AftersaleDetailView },
    { path: '/coupons', name: 'coupon-center', component: CouponCenterView },
    { path: '/my-coupons', name: 'my-coupons', component: MyCouponsView },
    { path: '/point-logs', name: 'point-logs', component: PointLogsView },
    { path: '/balance-logs', name: 'balance-logs', component: BalanceLogsView },
    { path: '/footprint', name: 'footprint', component: FootprintView },
    { path: '/member-level', name: 'member-level', component: MemberLevelView },
    { path: '/seckill', name: 'seckill', component: SeckillListView },
    { path: '/seckill/:id', name: 'seckill-detail', component: SeckillDetailView },
    { path: '/points-mall', name: 'points-mall', component: PointsMallView },
    { path: '/points-mall/:id', name: 'points-mall-detail', component: PointsMallDetailView },
    { path: '/checkin', name: 'checkin', component: CheckinView },
    { path: '/distribution', name: 'distribution', component: DistributionView },
    { path: '/distribution/downlines', name: 'dist-downlines', component: DistDownlinesView },
    { path: '/distribution/commissions', name: 'dist-commissions', component: DistCommissionsView },
    { path: '/distribution/orders', name: 'dist-orders', component: DistOrdersView },
    { path: '/distribution/withdraw', name: 'dist-withdraw', component: DistWithdrawView },
    { path: '/distribution/poster', name: 'dist-poster', component: DistPosterView },
    { path: '/distribution/ranking', name: 'dist-ranking', component: DistRankingView },
  ],
  scrollBehavior(to, from, savedPosition) {
    // 瀏覽器後退/前進：優先用歷史位置
    if (savedPosition) return savedPosition
    // 進入疊層：勿動 window，底層 Tab 滾動由 useTabShell 鎖定/還原
    if (to.meta.overlay) return false
    // 從疊層回到 Tab：同樣不動，由 shell 還原 scrollY
    const cachedTabs = new Set(['home', 'category', 'cart', 'account'])
    if (from?.meta?.overlay && to.name && cachedTabs.has(String(to.name))) {
      return false
    }
    // 回到 keep-alive 的 Tab 頁：不強制置頂
    if (to.name && cachedTabs.has(String(to.name))) {
      return false
    }
    // 其餘前進導航置頂（含疊層 → 結算／登錄）
    return { top: 0 }
  },
})

router.beforeEach((to, from) => {
  captureRefFromQuery(to.query.ref)
  rememberTabScrollBeforeOverlay(Boolean(to.meta.overlay), Boolean(from.meta.overlay), from.name)
  if (isTabRouteName(from.name)) rememberLastTabName(from.name)
  if (isTabRouteName(to.name)) rememberLastTabName(to.name)

  // 已登錄不應停在登錄／註冊／找回（含業務頁返回鍵退回認證頁）
  if (isAuthRouteName(to.name) && localStorage.getItem('sm_token')) {
    return { path: loggedInAuthBouncePath(), replace: true }
  }
})
