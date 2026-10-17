import { createApp } from 'vue'
import {
  Button,
  Cell,
  CellGroup,
} from 'vant'
import 'vant/lib/index.css'
import './style.css'
import App from './App.vue'

const app = createApp(App)
app.use(Button)
app.use(Cell)
app.use(CellGroup)
app.mount('#app')
