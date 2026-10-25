<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  NButton,
  NDrawer,
  NDrawerContent,
  NLayout,
  NLayoutContent,
  NLayoutHeader,
  NLayoutSider,
  NMenu,
  NText,
} from 'naive-ui'
import { api, type AdminUser } from '../api'
import { menuOptions } from '../menus'

const NARROW_MQ = '(max-width: 880px)'

const route = useRoute()
const router = useRouter()
const admin = ref<AdminUser | null>(readAdmin())
const isNarrow = ref(false)
const menuOpen = ref(false)

/** 詳情頁仍高亮列表菜單 */
const active = computed(() => {
  const p = route.path
  if (p.startsWith('/sales/orders/')) return '/sales/orders'
  if (p.startsWith('/catalog/products/') && p !== '/catalog/products') return '/catalog/products'
  if (p.startsWith('/catalog/specs/') && p !== '/catalog/specs') return '/catalog/specs'
  return p === '/' ? '/' : p
})
const title = computed(() => String(route.meta.title || 'SisterMart'))
const menus = computed(() => menuOptions(admin?.value?.role))
const contentStyle = computed(() =>
  isNarrow.value
    ? 'padding: 0; min-height: 100%'
    : 'padding: 1.25rem; min-height: 100%',
)

function readAdmin(): AdminUser | null {
  const raw = localStorage.getItem('sm_admin')
  if (!raw) return null
  try {
    return JSON.parse(raw) as AdminUser
  } catch {
    return null
  }
}

function go(key: string) {
  if (key.startsWith('/')) {
    router.push(key)
    menuOpen.value = false
  }
}

async function logout() {
  try {
    await api('/api/v1/admin/sessions/current', { method: 'DELETE' }, true)
  } catch {
    /* 本地仍清除令牌 */
  }
  localStorage.removeItem('sm_admin_token')
  localStorage.removeItem('sm_admin')
  menuOpen.value = false
  router.replace({ name: 'login' })
}

let narrowMq: MediaQueryList | null = null

function syncNarrow() {
  if (!narrowMq) return
  isNarrow.value = narrowMq.matches
  if (!narrowMq.matches) menuOpen.value = false
}

onMounted(() => {
  narrowMq = window.matchMedia(NARROW_MQ)
  syncNarrow()
  narrowMq.addEventListener('change', syncNarrow)
})

onUnmounted(() => {
  narrowMq?.removeEventListener('change', syncNarrow)
  narrowMq = null
})
</script>

<template>
  <n-layout :has-sider="!isNarrow" class="frame" position="absolute">
    <n-layout-sider
      v-if="!isNarrow"
      inverted
      bordered
      collapse-mode="width"
      :native-scrollbar="false"
      :width="220"
      content-style="display:flex;flex-direction:column;height:100%"
    >
      <div class="brand">Sister<span>Mart</span></div>
      <div class="sider-menu">
        <n-menu :value="active" :options="menus" inverted @update:value="go" />
      </div>
    </n-layout-sider>

    <n-layout class="main">
      <n-layout-header bordered class="header" :class="{ narrow: isNarrow }">
        <template v-if="isNarrow">
          <div class="brand brand-bar">Sister<span>Mart</span></div>
          <n-button quaternary class="menu-btn" aria-label="打開菜單" @click="menuOpen = true">
            <span class="burger" aria-hidden="true">
              <i /><i /><i />
            </span>
          </n-button>
        </template>
        <template v-else>
          <strong>{{ title }}</strong>
          <div class="who">
            <n-text depth="3">{{ admin?.email }}</n-text>
            <n-button quaternary size="small" @click="logout">退出</n-button>
          </div>
        </template>
      </n-layout-header>
      <n-layout-content
        class="content"
        :class="{ narrow: isNarrow }"
        :native-scrollbar="false"
        :content-style="contentStyle"
      >
        <div class="content-inner" :class="{ narrow: isNarrow }">
          <router-view />
        </div>
      </n-layout-content>
    </n-layout>
  </n-layout>

  <n-drawer
    v-model:show="menuOpen"
    placement="left"
    :width="280"
    :trap-focus="false"
    display-directive="show"
  >
    <n-drawer-content :native-scrollbar="false" body-content-style="padding: 0" class="drawer-body">
      <div class="drawer-panel">
        <div class="brand drawer-brand">Sister<span>Mart</span></div>
        <div class="drawer-meta">
          <n-text depth="3">{{ admin?.email }}</n-text>
        </div>
        <div class="sider-menu drawer-menu">
          <n-menu :value="active" :options="menus" @update:value="go" />
        </div>
        <div class="drawer-foot">
          <n-button block secondary @click="logout">退出登錄</n-button>
        </div>
      </div>
    </n-drawer-content>
  </n-drawer>
</template>

<style scoped>
.frame {
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  height: 100vh;
}

.brand {
  flex: 0 0 auto;
  padding: 1.25rem 1.25rem 0.75rem;
  color: #fff;
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.brand span {
  color: #c45c4a;
}

.brand-bar {
  padding: 0;
  color: #1c1c1c;
  font-size: 1.125rem;
}

.sider-menu {
  flex: 1 1 auto;
  overflow: auto;
  min-height: 0;
}

.main {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.header {
  flex: 0 0 auto;
  height: 3.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.25rem;
  background: #fff;
}

.header.narrow {
  padding: 0 0.75rem 0 1rem;
}

.who {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.menu-btn {
  width: 2.5rem;
  height: 2.5rem;
  padding: 0;
}

.burger {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.28rem;
  width: 1.125rem;
}

.burger i {
  display: block;
  height: 2px;
  border-radius: 1px;
  background: #1c1c1c;
}

.content {
  flex: 1 1 auto;
  min-height: 0;
  background: #f6f3ee;
}

.content-inner.narrow {
  /* 窄屏去邊距；底部留白避免最後一塊被裁切 */
  margin-bottom: 4.5rem;
}

.drawer-panel {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 100%;
  background: #fff;
}

.drawer-brand {
  color: #1c1c1c;
  padding-bottom: 0.35rem;
}

.drawer-meta {
  padding: 0 1.25rem 0.75rem;
  font-size: 0.8125rem;
}

.drawer-menu {
  border-top: 1px solid #f0ebe4;
  padding: 0.5rem 0;
}

.drawer-foot {
  flex: 0 0 auto;
  margin-top: auto;
  padding: 1rem 1.25rem 1.25rem;
  border-top: 1px solid #f0ebe4;
}
</style>
