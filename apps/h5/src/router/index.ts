import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import CategoryView from '../views/CategoryView.vue'
import CartView from '../views/CartView.vue'
import AccountView from '../views/AccountView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: HomeView },
    { path: '/category', name: 'category', component: CategoryView },
    { path: '/cart', name: 'cart', component: CartView },
    { path: '/account', name: 'account', component: AccountView },
  ],
  scrollBehavior: () => ({ top: 0 }),
})
