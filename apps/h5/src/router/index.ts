import { createRouter, createWebHistory } from 'vue-router'
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
import CheckoutView from '../views/CheckoutView.vue'
import PaymentView from '../views/PaymentView.vue'
import PayResultView from '../views/PayResultView.vue'
import AddressesView from '../views/AddressesView.vue'
import LogisticsView from '../views/LogisticsView.vue'
import AftersaleApplyView from '../views/AftersaleApplyView.vue'
import AftersaleDetailView from '../views/AftersaleDetailView.vue'
import AftersalesView from '../views/AftersalesView.vue'

export const TAB_ROUTE_NAMES = ['home', 'category', 'cart', 'account'] as const

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: HomeView, meta: { tab: true } },
    { path: '/category', name: 'category', component: CategoryView, meta: { tab: true } },
    { path: '/cart', name: 'cart', component: CartView, meta: { tab: true } },
    { path: '/account', name: 'account', component: AccountView, meta: { tab: true } },
    { path: '/search', name: 'search', component: SearchView },
    { path: '/product/:id', name: 'product-detail', component: ProductDetailView, meta: { tab: true } },
    { path: '/products', name: 'product-list', component: ProductListView },
    { path: '/login', name: 'login', component: LoginView },
    { path: '/register', name: 'register', component: RegisterView },
    { path: '/forgot', name: 'forgot', component: ForgotView },
    { path: '/article/:id', name: 'article', component: ArticleView },
    { path: '/wishlist', name: 'wishlist', component: WishlistView },
    { path: '/messages', name: 'messages', component: MessagesView },
    { path: '/orders', name: 'orders', component: OrdersView },
    { path: '/orders/:id', name: 'order-detail', component: OrderDetailView },
    { path: '/checkout', name: 'checkout', component: CheckoutView },
    { path: '/payment/:id', name: 'payment', component: PaymentView },
    { path: '/pay-result', name: 'pay-result', component: PayResultView },
    { path: '/addresses', name: 'addresses', component: AddressesView },
    { path: '/orders/:id/logistics', name: 'logistics', component: LogisticsView },
    { path: '/orders/:id/aftersale', name: 'aftersale-apply', component: AftersaleApplyView },
    { path: '/aftersales', name: 'aftersales', component: AftersalesView },
    { path: '/aftersales/:id', name: 'aftersale-detail', component: AftersaleDetailView },
  ],
  scrollBehavior: () => ({ top: 0 }),
})
