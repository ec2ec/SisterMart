import { createRouter, createWebHashHistory } from 'vue-router'
import AdminLayout from '../layouts/AdminLayout.vue'
import ArticlesView from '../views/ArticlesView.vue'
import AttrsView from '../views/AttrsView.vue'
import BadgesView from '../views/BadgesView.vue'
import CategoriesView from '../views/CategoriesView.vue'
import DashboardView from '../views/DashboardView.vue'
import LoginView from '../views/LoginView.vue'
import ProductEditView from '../views/ProductEditView.vue'
import ProductSpecsView from '../views/ProductSpecsView.vue'
import ProductsView from '../views/ProductsView.vue'
import ServiceTagsView from '../views/ServiceTagsView.vue'
import SpecsView from '../views/SpecsView.vue'
import ShopSettingsView from '../views/ShopSettingsView.vue'
import MaintenanceView from '../views/MaintenanceView.vue'
import OrdersView from '../views/OrdersView.vue'
import OrderDetailView from '../views/OrderDetailView.vue'
import AftersalesView from '../views/AftersalesView.vue'
import TabbarView from '../views/TabbarView.vue'
import CouponsView from '../views/CouponsView.vue'
import PointsView from '../views/PointsView.vue'
import MembersView from '../views/MembersView.vue'
import LevelsView from '../views/LevelsView.vue'
import AdminsView from '../views/AdminsView.vue'
import MessagesView from '../views/MessagesView.vue'
import NewsletterView from '../views/NewsletterView.vue'
import DistributionView from '../views/DistributionView.vue'
import SeckillView from '../views/SeckillView.vue'
import ReviewsView from '../views/ReviewsView.vue'
import HomeBannersView from '../views/HomeBannersView.vue'

export const router = createRouter({
  // Hash until shared-host nginx try_files covers /admin/* (releases/vps-spa).
  history: createWebHashHistory(),
  routes: [
    { path: '/login', name: 'login', component: LoginView, meta: { public: true } },
    {
      path: '/',
      component: AdminLayout,
      children: [
        { path: '', component: DashboardView, meta: { title: '概覽' } },
        { path: '/catalog/products', component: ProductsView, meta: { title: '商品' } },
        { path: '/catalog/products/:id', component: ProductEditView, meta: { title: '商品編輯' } },
        { path: '/catalog/categories', component: CategoriesView, meta: { title: '分類' } },
        { path: '/catalog/badges', component: BadgesView, meta: { title: '角標' } },
        { path: '/catalog/service-tags', component: ServiceTagsView, meta: { title: '服務標籤' } },
        { path: '/catalog/attrs', component: AttrsView, meta: { title: '商品參數' } },
        { path: '/catalog/specs', component: SpecsView, meta: { title: '規格管理' } },
        { path: '/catalog/specs/:id', component: ProductSpecsView, meta: { title: '規格編輯' } },
        { path: '/sales/orders', component: OrdersView, meta: { title: '訂單' } },
        { path: '/sales/orders/:id', component: OrderDetailView, meta: { title: '訂單詳情' } },
        { path: '/sales/aftersales', component: AftersalesView, meta: { title: '售後' } },
        { path: '/sales/reviews', component: ReviewsView, meta: { title: '評價' } },
        { path: '/customers/members', component: MembersView, meta: { title: '會員' } },
        { path: '/customers/levels', component: LevelsView, meta: { title: '會員等級' } },
        { path: '/cms/articles', component: ArticlesView, meta: { title: '文章' } },
        { path: '/cms/home', component: HomeBannersView, meta: { title: '首頁運營' } },
        { path: '/cms/messages', component: MessagesView, meta: { title: '系統消息' } },
        { path: '/cms/newsletter', component: NewsletterView, meta: { title: '訂閱名單' } },
        { path: '/marketing/coupons', component: CouponsView, meta: { title: '優惠券' } },
        { path: '/marketing/points', component: PointsView, meta: { title: '積分' } },
        { path: '/marketing/seckill', component: SeckillView, meta: { title: '秒殺活動' } },
        { path: '/marketing/distribution', component: DistributionView, meta: { title: '分銷' } },
        { path: '/settings/tabbar', component: TabbarView, meta: { title: '底部導航' } },
        { path: '/settings/maintenance', component: MaintenanceView, meta: { title: '店鋪維護' } },
        { path: '/settings/shop', component: ShopSettingsView, meta: { title: '店鋪參數' } },
        { path: '/settings/admins', component: AdminsView, meta: { title: '管理員與角色', roles: ['super'] } },
      ],
    },
  ],
})

router.beforeEach((to) => {
  if (to.meta.public) return true
  if (!localStorage.getItem('sm_admin_token')) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  const roles = to.meta.roles as string[] | undefined
  if (roles?.length) {
    try {
      const admin = JSON.parse(localStorage.getItem('sm_admin') || '{}') as { role?: string }
      if (!admin.role || !roles.includes(admin.role)) {
        return { path: '/' }
      }
    } catch {
      return { path: '/' }
    }
  }
  return true
})
