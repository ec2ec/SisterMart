<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NLayout, NLayoutContent, NLayoutHeader, NLayoutSider, NMenu, NText } from 'naive-ui'
import { api, type AdminUser } from '../api'
import { menuOptions } from '../menus'

const route = useRoute()
const router = useRouter()
const admin = ref<AdminUser | null>(readAdmin())

/** 詳情頁仍高亮列表菜單 */
const active = computed(() => {
  const p = route.path
  if (p.startsWith('/sales/orders/')) return '/sales/orders'
  if (p.startsWith('/catalog/products/') && p !== '/catalog/products') return '/catalog/products'
  return p === '/' ? '/' : p
})
const title = computed(() => String(route.meta.title || 'SisterMart'))

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
  if (key.startsWith('/')) router.push(key)
}

async function logout() {
  try {
    await api('/api/v1/admin/sessions/current', { method: 'DELETE' }, true)
  } catch {
    /* 本地仍清除令牌 */
  }
  localStorage.removeItem('sm_admin_token')
  localStorage.removeItem('sm_admin')
  router.replace({ name: 'login' })
}
</script>

<template>
  <n-layout has-sider class="frame" position="absolute">
    <n-layout-sider
      inverted
      bordered
      collapse-mode="width"
      :native-scrollbar="false"
      :width="220"
      content-style="display:flex;flex-direction:column;height:100%"
    >
      <div class="brand">Sister<span>Mart</span></div>
      <div class="sider-menu">
        <n-menu :value="active" :options="menuOptions(admin?.role)" inverted @update:value="go" />
      </div>
    </n-layout-sider>
    <n-layout class="main">
      <n-layout-header bordered class="header">
        <strong>{{ title }}</strong>
        <div class="who">
          <n-text depth="3">{{ admin?.email }}</n-text>
          <n-button quaternary size="small" @click="logout">退出</n-button>
        </div>
      </n-layout-header>
      <n-layout-content
        class="content"
        :native-scrollbar="false"
        content-style="padding: 1.25rem; min-height: 100%"
      >
        <router-view />
      </n-layout-content>
    </n-layout>
  </n-layout>
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

.who {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.content {
  flex: 1 1 auto;
  min-height: 0;
  background: #f6f3ee;
}
</style>
