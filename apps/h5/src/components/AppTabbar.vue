<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api/client'
import { initialCart } from '../mock/data'

type Slot = 'home' | 'category' | 'cart' | 'account'

type TabItem = {
  slot: Slot
  label_zh: string
  label_en: string
  icon: string
  icon_active: string
  icon_bg: string
  icon_bg_active: string
  text_color: string
  text_color_active: string
  sort: number
}

type TabbarConfig = {
  bar_bg: string
  items: TabItem[]
}

const builtin: TabbarConfig = {
  bar_bg: '#ffffff',
  items: [
    { slot: 'home', label_zh: '首頁', label_en: 'Home', icon: 'home-o', icon_active: 'home-o', icon_bg: '#f6f3ee', icon_bg_active: '#efeae3', text_color: '#8a8378', text_color_active: '#16162b', sort: 1 },
    { slot: 'category', label_zh: '分類', label_en: 'Category', icon: 'apps-o', icon_active: 'apps-o', icon_bg: '#f6f3ee', icon_bg_active: '#efeae3', text_color: '#8a8378', text_color_active: '#16162b', sort: 2 },
    { slot: 'cart', label_zh: '購物車', label_en: 'Cart', icon: 'shopping-cart-o', icon_active: 'shopping-cart-o', icon_bg: '#f6f3ee', icon_bg_active: '#efeae3', text_color: '#8a8378', text_color_active: '#16162b', sort: 3 },
    { slot: 'account', label_zh: '我的', label_en: 'Account', icon: 'user-o', icon_active: 'user-o', icon_bg: '#f6f3ee', icon_bg_active: '#efeae3', text_color: '#8a8378', text_color_active: '#16162b', sort: 4 },
  ],
}

const CACHE = 'sm_tabbar'
const { locale } = useI18n()
const route = useRoute()
const router = useRouter()
const cartCount = initialCart.length

function readCache(): TabbarConfig | null {
  const raw = sessionStorage.getItem(CACHE)
  if (!raw) return null
  try {
    return JSON.parse(raw) as TabbarConfig
  } catch {
    return null
  }
}

const config = ref<TabbarConfig>(readCache() ?? builtin)
const active = computed(() => String(route.name || 'home'))
const items = computed(() => [...config.value.items].sort((a, b) => a.sort - b.sort))

function isUrl(icon: string) {
  return icon.startsWith('http') || icon.startsWith('/')
}

function iconOf(item: TabItem) {
  return active.value === item.slot ? item.icon_active : item.icon
}

function labelOf(item: TabItem) {
  return locale.value === 'en' ? item.label_en : item.label_zh
}

async function refresh() {
  try {
    const data = await api<TabbarConfig>('/api/v1/tabbar')
    const next = { bar_bg: data.bar_bg, items: data.items }
    sessionStorage.setItem(CACHE, JSON.stringify(next))
    config.value = next
  } catch {
    const cached = readCache()
    if (cached) config.value = cached
  }
}

watch(() => route.fullPath, () => {
  if (route.meta.tab) refresh()
}, { immediate: true })

function onChange(name: string | number) {
  router.push({ name: String(name) })
}
</script>

<template>
  <van-tabbar
    :model-value="active"
    :style="{ background: config.bar_bg, '--van-tabbar-background': config.bar_bg }"
    @change="onChange"
  >
    <van-tabbar-item
      v-for="item in items"
      :key="item.slot"
      :name="item.slot"
      :badge="item.slot === 'cart' && cartCount ? cartCount : ''"
    >
      <span :style="{ color: active === item.slot ? item.text_color_active : item.text_color }">{{ labelOf(item) }}</span>
      <template #icon>
        <span
          class="bubble"
          :style="{ background: active === item.slot ? item.icon_bg_active : item.icon_bg, color: active === item.slot ? item.text_color_active : item.text_color }"
        >
          <img v-if="isUrl(iconOf(item))" :src="iconOf(item)" alt="" />
          <van-icon v-else :name="iconOf(item)" />
        </span>
      </template>
    </van-tabbar-item>
  </van-tabbar>
</template>

<style scoped>
.bubble {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  display: grid;
  place-items: center;
}

.bubble img {
  width: 1rem;
  height: 1rem;
  object-fit: contain;
}
</style>
