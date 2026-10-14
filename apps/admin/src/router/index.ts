import { createRouter, createWebHistory } from 'vue-router'
import AdminLayout from '../layouts/AdminLayout.vue'
import DashboardView from '../views/DashboardView.vue'
import LoginView from '../views/LoginView.vue'
import PlaceholderView from '../views/PlaceholderView.vue'
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
        placeholder('/catalog/products'),
        placeholder('/catalog/categories'),
        placeholder('/sales/orders'),
        placeholder('/sales/aftersales'),
        placeholder('/customers/members'),
        placeholder('/cms/articles'),
        placeholder('/cms/home'),
        placeholder('/cms/messages'),
        placeholder('/marketing/coupons'),
        placeholder('/marketing/points'),
        placeholder('/marketing/seckill'),
        placeholder('/marketing/distribution'),
        { path: '/settings/tabbar', component: TabbarView, meta: { title: '底部導航' } },
        placeholder('/settings/shop'),
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
