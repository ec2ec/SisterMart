<script setup lang="ts">
import { computed, onMounted, type Component } from 'vue'
import AppTabbar from './components/AppTabbar.vue'
import HomeView from './views/HomeView.vue'
import CategoryView from './views/CategoryView.vue'
import CartView from './views/CartView.vue'
import AccountView from './views/AccountView.vue'
import MaintenanceView from './views/MaintenanceView.vue'
import { useMaintenance } from './composables/useMaintenance'
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
const { status, enabled: maintenanceOn, loaded: maintenanceLoaded, refresh: refreshMaintenance } =
  useMaintenance()

const backgroundComponent = computed(() => {
  const name = backgroundTabName.value
  return name ? TAB_COMPONENTS[name] : null
})

onMounted(() => {
  void refreshMaintenance()
})
</script>

<template>
  <MaintenanceView
    v-if="maintenanceLoaded && maintenanceOn && status"
    :status="status"
    @retry="refreshMaintenance"
  />

  <template v-else>
    <!-- 底層 Tab：進商詳疊層時不卸載，保留滾動位置 -->
    <keep-alive :include="KEEP_ALIVE_NAMES">
      <component
        :is="backgroundComponent"
        v-if="backgroundComponent && backgroundTabName"
        :key="backgroundTabName"
      />
    </keep-alive>

    <router-view v-slot="{ Component, route: r }">
      <!-- 商詳等疊層：蓋在 Tab 之上；按 fullPath 強制重掛，避免協議 A→B 複用實例閃舊文 -->
      <div v-if="r.meta.overlay && Component" :key="r.fullPath" class="sm-route-overlay">
        <component :is="Component" :key="r.fullPath" />
      </div>
      <!-- 其餘整頁（結算／登錄等）；Tab 已由底層渲染，勿重複掛載 -->
      <!-- orders：Tab query 變化時用穩定 key，避免整頁 remount 白屏（對齊首頁 Feed） -->
      <component
        :is="Component"
        v-else-if="Component && !routeIsTab(r) && !r.meta.overlay"
        :key="r.name === 'orders' ? 'orders' : r.fullPath"
      />
    </router-view>

    <AppTabbar v-if="showTabbar" />
  </template>
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
