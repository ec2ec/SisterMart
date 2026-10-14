import { createRouter, createWebHistory } from 'vue-router'
import AdminLayout from '../layouts/AdminLayout.vue'
import ArticlesView from '../views/ArticlesView.vue'
import CategoriesView from '../views/CategoriesView.vue'
import DashboardView from '../views/DashboardView.vue'
import LoginView from '../views/LoginView.vue'
import PlaceholderView from '../views/PlaceholderView.vue'
import ProductEditView from '../views/ProductEditView.vue'
import ProductsView from '../views/ProductsView.vue'
import ShopSettingsView from '../views/ShopSettingsView.vue'
import TabbarView from '../views/TabbarView.vue'
import { findMenu } from '../menus'

const placeholder = (path: string) => {
  const item = findMenu(path)
  return {
    path,
    component: PlaceholderView,
    meta: { title: item?.label || path, milestone: item?.milestone || '後續' },
  }
}

export const router = createRouter({
  history: createWebHistory(),
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
        placeholder('/sales/orders'),
        placeholder('/sales/aftersales'),
        placeholder('/customers/members'),
        { path: '/cms/articles', component: ArticlesView, meta: { title: '文章' } },
        placeholder('/cms/home'),
        placeholder('/cms/messages'),
        placeholder('/marketing/coupons'),
        placeholder('/marketing/points'),
        placeholder('/marketing/seckill'),
        placeholder('/marketing/distribution'),
        { path: '/settings/tabbar', component: TabbarView, meta: { title: '底部導航' } },
        { path: '/settings/shop', component: ShopSettingsView, meta: { title: '店鋪參數' } },
        placeholder('/settings/admins'),
      ],
    },
  ],
})

router.beforeEach((to) => {
  if (to.meta.public) return true
  if (!localStorage.getItem('sm_admin_token')) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  return true
})
