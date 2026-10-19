<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import type { RouteLocationNormalizedLoaded } from 'vue-router'
import AppTabbar from './components/AppTabbar.vue'

/** 與各頁 defineOptions({ name }) 對齊；僅緩存這些，商詳等不進池 */
const KEEP_ALIVE_NAMES = ['HomeView', 'CategoryView', 'CartView', 'AccountView']

const route = useRoute()
const showTabbar = computed(() => Boolean(route.meta.tab))

function viewKey(r: RouteLocationNormalizedLoaded) {
  const name = String(r.name || '')
  if (KEEP_ALIVE_NAMES.some((n) => n === componentNameOf(name))) {
    return name
  }
  return r.fullPath
}

function componentNameOf(routeName: string) {
  const map: Record<string, string> = {
    home: 'HomeView',
    category: 'CategoryView',
    cart: 'CartView',
    account: 'AccountView',
  }
  return map[routeName] || routeName
}
</script>

<template>
  <router-view v-slot="{ Component, route: r }">
    <keep-alive :include="KEEP_ALIVE_NAMES">
      <component :is="Component" :key="viewKey(r)" />
    </keep-alive>
  </router-view>
  <AppTabbar v-if="showTabbar" />
</template>
