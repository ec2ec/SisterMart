<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NLayout, NLayoutContent, NLayoutHeader, NLayoutSider, NMenu, NText } from 'naive-ui'
import { api, type AdminUser } from '../api'
import { menuOptions } from '../menus'

const route = useRoute()
const router = useRouter()
const admin = ref<AdminUser | null>(readAdmin())

const active = computed(() => (route.path === '/' ? '/' : route.path))
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
  <n-layout has-sider class="frame">
    <n-layout-sider inverted bordered :native-scrollbar="false" :width="220">
      <div class="brand">Sister<span>Mart</span></div>
      <n-menu :value="active" :options="menuOptions(admin?.role)" inverted @update:value="go" />
    </n-layout-sider>
    <n-layout>
      <n-layout-header bordered class="header">
        <strong>{{ title }}</strong>
        <div class="who">
          <n-text depth="3">{{ admin?.email }}</n-text>
          <n-button quaternary size="small" @click="logout">退出</n-button>
        </div>
      </n-layout-header>
      <n-layout-content class="content">
        <router-view />
      </n-layout-content>
    </n-layout>
  </n-layout>
</template>

<style scoped>
.frame {
  min-height: 100vh;
}

.brand {
  padding: 1.25rem 1.25rem 0.75rem;
  color: #fff;
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.brand span {
  color: #c45c4a;
}

.header {
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
  padding: 1.25rem;
  background: #f6f3ee;
  min-height: calc(100vh - 3.5rem);
}
</style>
