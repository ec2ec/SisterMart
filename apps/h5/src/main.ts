import { createApp } from 'vue'
import {
  BackTop,
  Badge,
  Button,
  Cell,
  CellGroup,
  Checkbox,
  CountDown,
  Field,
  Icon,
  List,
  NavBar,
  Popup,
  PullRefresh,
  Radio,
  RadioGroup,
  Search,
  Sidebar,
  SidebarItem,
  Stepper,
  Switch,
  Swipe,
  SwipeItem,
  Tab,
  Tabs,
  Tabbar,
  TabbarItem,
  Toast,
  Uploader,
} from 'vant'
import 'vant/lib/index.css'
import './styles/theme.css'
import App from './App.vue'
import { router } from './router'
import { i18n } from './i18n'
import { clearFavorites, hydrateFavorites } from './composables/useFavorites'
import { clearCartBadge } from './composables/useCartBadge'
import { clearOrderCounts } from './composables/useOrderCounts'
import { clearOrdersListCache } from './composables/useOrdersList'
import { clearUnread } from './composables/useUnread'
import { isLoggedIn } from './composables/useSession'

const app = createApp(App)

;[
  BackTop,
  Badge,
  Button,
  Cell,
  CellGroup,
  Checkbox,
  CountDown,
  Field,
  Icon,
  List,
  NavBar,
  Popup,
  PullRefresh,
  Radio,
  RadioGroup,
  Search,
  Sidebar,
  SidebarItem,
  Stepper,
  Switch,
  Swipe,
  SwipeItem,
  Tab,
  Tabs,
  Tabbar,
  TabbarItem,
  Toast,
  Uploader,
].forEach((c) => app.use(c))

app.use(router)
app.use(i18n)
document.documentElement.lang = String(i18n.global.locale.value)
if (isLoggedIn()) {
  void hydrateFavorites()
} else {
  // 訪客：不讀取／展示上一帳號的會員緩存角標
  clearFavorites()
  clearCartBadge()
  clearUnread()
  clearOrderCounts()
  clearOrdersListCache()
}
app.mount('#app')
