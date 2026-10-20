<script setup lang="ts">
import { computed, type Component } from 'vue'
import AppTabbar from './components/AppTabbar.vue'
import HomeView from './views/HomeView.vue'
import CategoryView from './views/CategoryView.vue'
import CartView from './views/CartView.vue'
import AccountView from './views/AccountView.vue'
import { useTabShell, routeIsTab } from './composables/useTabShell'

/** 與各頁 defineOptions({ name }) 對齊 */
const KEEP_ALIVE_NAMES = ['HomeView', 'CategoryView', 'CartView', 'AccountView']

const TAB_COMPONENTS: Record<string, Component> = {
  home: HomeView,
  category: CategoryView,
  cart: CartView,
  account: AccountView,
}

const { backgroundTabName, showTabbar } = useTabShell()

const backgroundComponent = computed(() => {
  const name = backgroundTabName.value
  return name ? TAB_COMPONENTS[name] : null
})
</script>

<template>
  <!-- 底層 Tab：進商詳疊層時不卸載，保留滾動位置 -->
  <keep-alive :include="KEEP_ALIVE_NAMES">
    <component
      :is="backgroundComponent"
      v-if="backgroundComponent && backgroundTabName"
      :key="backgroundTabName"
    />
  </keep-alive>

  <router-view v-slot="{ Component, route: r }">
    <!-- 商詳等疊層：蓋在 Tab 之上 -->
    <div v-if="r.meta.overlay && Component" class="sm-route-overlay">
      <component :is="Component" />
    </div>
    <!-- 其餘整頁（結算／登錄等）；Tab 已由底層渲染，勿重複掛載 -->
    <component
      :is="Component"
      v-else-if="Component && !routeIsTab(r) && !r.meta.overlay"
      :key="r.fullPath"
    />
  </router-view>

  <AppTabbar v-if="showTabbar" />
</template>

<style scoped>
.sm-route-overlay {
  position: fixed;
  inset: 0;
  z-index: 200;
  background: #fff;
  overflow-x: hidden;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior: contain;
}
</style>
