<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api/client'
import { cartBadgeCount, refreshCartBadge } from '../composables/useCartBadge'
import { loggedIn, syncSessionFromStorage } from '../composables/useSession'

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

/** 預設：未選線框淺底；選中實心深底白標，點亮對比清晰
 * 注意：Vant 4 無實心 `home`，選中須用 `wap-home`（有 glyph），否則只剩空圓。 */
const builtin: TabbarConfig = {
  bar_bg: '#ffffff',
  items: [
    { slot: 'home', label_zh: '首頁', label_en: 'Home', icon: 'home-o', icon_active: 'wap-home', icon_bg: '#f6f3ee', icon_bg_active: '#16162b', text_color: '#8a8378', text_color_active: '#16162b', sort: 1 },
    { slot: 'category', label_zh: '分類', label_en: 'Category', icon: 'apps-o', icon_active: 'apps-o', icon_bg: '#f6f3ee', icon_bg_active: '#16162b', text_color: '#8a8378', text_color_active: '#16162b', sort: 2 },
    { slot: 'cart', label_zh: '購物車', label_en: 'Cart', icon: 'shopping-cart-o', icon_active: 'shopping-cart', icon_bg: '#f6f3ee', icon_bg_active: '#16162b', text_color: '#8a8378', text_color_active: '#16162b', sort: 3 },
    { slot: 'account', label_zh: '我的', label_en: 'Account', icon: 'user-o', icon_active: 'user', icon_bg: '#f6f3ee', icon_bg_active: '#16162b', text_color: '#8a8378', text_color_active: '#16162b', sort: 4 },
  ],
}

/** 線框 → 實心（僅 Vant 實際存在的 glyph） */
const FILLED: Record<string, string> = {
  'home-o': 'wap-home',
  'wap-home-o': 'wap-home',
  'shopping-cart-o': 'shopping-cart',
  'user-o': 'user',
}

/** 歷史錯誤名：Vant 4 沒有 `home` 實心圖 */
const ICON_ALIAS: Record<string, string> = {
  home: 'wap-home',
}

const CACHE = 'sm_tabbar'
const { locale } = useI18n()
const route = useRoute()
const router = useRouter()

function readCache(): TabbarConfig | null {
  const raw = sessionStorage.getItem(CACHE)
  if (!raw) return null
  try {
    return JSON.parse(raw) as TabbarConfig
  } catch {
    return null
  }
}

function resolveIcon(name: string): string {
  const n = (name || '').trim()
  if (!n) return n
  if (ICON_ALIAS[n]) return ICON_ALIAS[n]
  return n
}

/** 舊配置：無效 home、icon/icon_active 相同、底色過近 → 升級為可辨識選中態 */
function normalizeConfig(raw: TabbarConfig): TabbarConfig {
  return {
    bar_bg: raw.bar_bg || builtin.bar_bg,
    items: (raw.items || []).map((item) => {
      const fallback = builtin.items.find((b) => b.slot === item.slot)
      let icon = resolveIcon(item.icon)
      let iconActive = resolveIcon(item.icon_active || item.icon)
      if (iconActive === icon && FILLED[icon]) {
        iconActive = FILLED[icon]
      }
      // 首頁選中仍指向不存在的 home 時強制 wap-home
      if (item.slot === 'home' && (iconActive === 'home' || !iconActive)) {
        iconActive = 'wap-home'
      }
      const sameBg =
        (item.icon_bg_active || '').toLowerCase() === (item.icon_bg || '').toLowerCase() ||
        (item.icon_bg_active || '').toLowerCase() === '#efeae3'
      return {
        ...item,
        icon,
        icon_active: iconActive,
        icon_bg_active: sameBg ? (fallback?.icon_bg_active ?? '#16162b') : item.icon_bg_active,
        text_color_active: item.text_color_active || fallback?.text_color_active || '#16162b',
      }
    }),
  }
}

const config = ref<TabbarConfig>(normalizeConfig(readCache() ?? builtin))
const active = computed(() => {
  const name = String(route.name || '')
  const slots: Slot[] = ['home', 'category', 'cart', 'account']
  if (slots.includes(name as Slot)) return name
  return ''
})
const items = computed(() => [...config.value.items].sort((a, b) => a.sort - b.sort))

/** 空車不顯示角標；有貨顯示件數（>99 顯示 99+） */
const cartBadge = computed(() => {
  const n = cartBadgeCount.value
  if (n <= 0) return ''
  return n > 99 ? '99+' : String(n)
})

function isUrl(icon: string) {
  return icon.startsWith('http') || icon.startsWith('/')
}

function isOn(item: TabItem) {
  return active.value === item.slot
}

function iconOf(item: TabItem) {
  return isOn(item) ? item.icon_active : item.icon
}

function labelOf(item: TabItem) {
  return locale.value === 'en' ? item.label_en : item.label_zh
}

function bubbleFg(item: TabItem) {
  if (!isOn(item)) return item.text_color
  const bg = (item.icon_bg_active || '').replace(/\s/g, '').toLowerCase()
  // 深色選中底 → 白圖標
  if (bg === '#16162b' || bg === '#000' || bg === '#000000' || bg === '#1a1a1a') return '#ffffff'
  return item.text_color_active
}

async function refresh() {
  try {
    const data = await api<TabbarConfig>('/api/v1/tabbar')
    const next = normalizeConfig({ bar_bg: data.bar_bg, items: data.items })
    sessionStorage.setItem(CACHE, JSON.stringify(next))
    config.value = next
  } catch {
    const cached = readCache()
    if (cached) config.value = normalizeConfig(cached)
  }
}

watch(
  () => route.fullPath,
  () => {
    if (route.meta.tab) {
      void refresh()
      void refreshCartBadge()
    }
  },
  { immediate: true },
)

watch(loggedIn, () => {
  syncSessionFromStorage()
  void refreshCartBadge()
})

function onChange(name: string | number) {
  router.push({ name: String(name) })
}
</script>

<template>
  <van-tabbar
    :model-value="active"
    active-color="#16162b"
    inactive-color="#8a8378"
    :style="{ background: config.bar_bg, '--van-tabbar-background': config.bar_bg }"
    @change="onChange"
  >
    <van-tabbar-item
      v-for="item in items"
      :key="item.slot"
      :name="item.slot"
      :badge="item.slot === 'cart' ? cartBadge : ''"
    >
      <span
        class="label"
        :class="{ on: isOn(item) }"
        :style="{ color: isOn(item) ? item.text_color_active : item.text_color }"
      >{{ labelOf(item) }}</span>
      <template #icon>
        <span
          class="bubble"
          :class="{ on: isOn(item) }"
          :style="{ background: isOn(item) ? item.icon_bg_active : item.icon_bg, color: bubbleFg(item) }"
        >
          <img
            v-if="isUrl(iconOf(item))"
            :src="iconOf(item)"
            alt=""
            :class="{ lit: isOn(item) }"
          />
          <van-icon v-else :name="iconOf(item)" :color="bubbleFg(item)" />
        </span>
      </template>
    </van-tabbar-item>
  </van-tabbar>
</template>

<style scoped>
.bubble {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 50%;
  display: grid;
  place-items: center;
  transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
  /* 蓋過 van-tabbar active-color，避免選中深底上圖標變深色 */
  color: inherit;
}

.bubble.on {
  transform: scale(1.06);
}

.bubble :deep(.van-icon) {
  color: inherit;
}

.bubble img {
  width: 1rem;
  height: 1rem;
  object-fit: contain;
}

/* 選中態自定義圖：提亮，避免灰暗 */
.bubble img.lit {
  filter: brightness(0) invert(1);
}

.label {
  font-size: 0.6875rem;
  line-height: 1.2;
  transition: color 0.15s ease, font-weight 0.15s ease;
}

.label.on {
  font-weight: 700;
}
</style>
