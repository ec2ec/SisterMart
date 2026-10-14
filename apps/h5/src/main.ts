import { createApp } from 'vue'
import {
  Badge,
  Button,
  Cell,
  CellGroup,
  Checkbox,
  CountDown,
  Field,
  Icon,
  NavBar,
  Search,
  Sidebar,
  SidebarItem,
  Stepper,
  Swipe,
  SwipeItem,
  Tab,
  Tabs,
  Tabbar,
  TabbarItem,
  Toast,
} from 'vant'
import 'vant/lib/index.css'
import './styles/theme.css'
import App from './App.vue'
import { router } from './router'
import { i18n } from './i18n'

const app = createApp(App)

;[
  Badge,
  Button,
  Cell,
  CellGroup,
  Checkbox,
  CountDown,
  Field,
  Icon,
  NavBar,
  Search,
  Sidebar,
  SidebarItem,
  Stepper,
  Swipe,
  SwipeItem,
  Tab,
  Tabs,
  Tabbar,
  TabbarItem,
  Toast,
].forEach((c) => app.use(c))

app.use(router)
app.use(i18n)
document.documentElement.lang = String(i18n.global.locale.value)
app.mount('#app')
