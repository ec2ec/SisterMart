<script setup lang="ts">
import { computed, onActivated, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { dismissHomePopup, fetchHomePopups, type HomePopup } from '../api/catalog'
import { peekJustRegistered } from '../composables/useAuthUi'
import { welcomeOverlayOpen } from '../composables/useHomePopupGate'
import { isLoggedIn, loggedIn } from '../composables/useSession'
import { assetUrl } from '../utils/assetUrl'

const DISMISS_KEY = 'sm.home_popup_dismiss.v1'
const DAY_KEY = 'sm.home_popup_day.v1'
const DAILY_MAX = 2

type DismissRecord = { at: string; frequency: string }
type DismissStore = Record<string, DismissRecord>
type DayStore = { day: string; ids: number[] }

const router = useRouter()
const show = ref(false)
const current = ref<HomePopup | null>(null)
const queue = ref<HomePopup[]>([])
let loading = false
let bootTimer: number | null = null
let closing = false

const canClickImage = computed(() => {
  const row = current.value
  return !!(row && row.link_type && row.link_type !== 'none' && row.link_value)
})

const popupStyle = computed(() => {
  const scale = Number(current.value?.image_scale ?? 100) || 100
  const clamped = Math.min(150, Math.max(40, scale))
  // 基準寬度 86%，按比例縮放
  const widthPct = Math.round((86 * clamped) / 100)
  return {
    width: `${widthPct}%`,
    maxWidth: `${((22 * clamped) / 100).toFixed(2)}rem`,
    background: 'transparent',
  }
})

function today(): string {
  const d = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function readJson<T>(key: string, fallback: T): T {
  try {
    const raw = localStorage.getItem(key)
    if (!raw) return fallback
    const parsed = JSON.parse(raw)
    return parsed && typeof parsed === 'object' ? (parsed as T) : fallback
  } catch {
    return fallback
  }
}

function writeJson(key: string, value: unknown) {
  try {
    localStorage.setItem(key, JSON.stringify(value))
  } catch {
    /* ignore */
  }
}

function readDismissStore(): DismissStore {
  return readJson<DismissStore>(DISMISS_KEY, {})
}

function readDayStore(): DayStore {
  const store = readJson<DayStore>(DAY_KEY, { day: today(), ids: [] })
  if (store.day !== today()) return { day: today(), ids: [] }
  return { day: store.day, ids: Array.isArray(store.ids) ? store.ids : [] }
}

function guestShouldHide(row: HomePopup): boolean {
  const freq = row.frequency || 'once'
  if (freq === 'always') return false
  const rec = readDismissStore()[String(row.id)]
  if (!rec) return false
  if (freq === 'once') return true
  if (freq === 'daily') return (rec.at || '').slice(0, 10) === today()
  return false
}

function guestTargetOk(row: HomePopup): boolean {
  const targets = row.targets?.length ? row.targets : ['all']
  if (targets.includes('all') || targets.includes('guest')) return true
  // 未登錄無法命中會員向定向
  return false
}

function rememberGuestDismiss(row: HomePopup) {
  const freq = row.frequency || 'once'
  if (freq !== 'always') {
    const store = readDismissStore()
    store[String(row.id)] = { at: new Date().toISOString(), frequency: freq }
    writeJson(DISMISS_KEY, store)
  }
  const day = readDayStore()
  if (!day.ids.includes(row.id)) {
    day.ids.push(row.id)
    writeJson(DAY_KEY, day)
  }
}

function onLink(linkType: string, linkValue: string) {
  if (linkType === 'product' && linkValue) {
    router.push({ name: 'product-detail', params: { id: linkValue } })
  } else if (linkType === 'category' && linkValue) {
    router.push({ name: 'product-list', query: { category: linkValue } })
  } else if (linkType === 'article' && linkValue) {
    router.push({ name: 'article', params: { id: linkValue } })
  } else if (linkType === 'url' && linkValue) {
    if (linkValue.startsWith('http')) {
      window.open(linkValue, '_blank', 'noopener')
    } else {
      router.push(linkValue)
    }
  }
}

function presentNext() {
  if (welcomeOverlayOpen.value || peekJustRegistered()) {
    show.value = false
    current.value = null
    return
  }
  const next = queue.value[0]
  if (!next) {
    show.value = false
    current.value = null
    return
  }
  current.value = next
  show.value = true
}

async function persistDismiss(row: HomePopup) {
  if (isLoggedIn()) {
    try {
      await dismissHomePopup(row.id)
    } catch {
      /* ignore */
    }
  } else {
    rememberGuestDismiss(row)
  }
}

async function closeCurrent(navigate: boolean) {
  if (closing) return
  const row = current.value
  if (!row) return
  if (navigate && !(row.link_type !== 'none' && row.link_value)) {
    return
  }
  closing = true
  try {
    show.value = false
    queue.value = queue.value.filter((x) => x.id !== row.id)
    current.value = null
    await persistDismiss(row)
    if (navigate) {
      onLink(row.link_type, row.link_value)
    }
    window.setTimeout(() => presentNext(), 120)
  } finally {
    closing = false
  }
}

function onImageClick() {
  if (!canClickImage.value) return
  void closeCurrent(true)
}

async function loadQueue() {
  if (loading) return
  loading = true
  try {
    const auth = isLoggedIn() ? 'member' : 'none'
    const data = await fetchHomePopups(auth)
    let items = data.items || []
    if (!isLoggedIn()) {
      items = items.filter((x) => guestTargetOk(x) && !guestShouldHide(x))
      const day = readDayStore()
      const remaining = Math.max(0, DAILY_MAX - day.ids.length)
      items = items.slice(0, remaining)
    } else if (typeof data.daily_remaining === 'number') {
      items = items.slice(0, Math.max(0, data.daily_remaining))
    }
    queue.value = items
    presentNext()
  } catch {
    queue.value = []
  } finally {
    loading = false
  }
}

function scheduleLoad() {
  if (bootTimer != null) window.clearTimeout(bootTimer)
  bootTimer = window.setTimeout(() => {
    void loadQueue()
  }, 450)
}

onMounted(scheduleLoad)
onActivated(scheduleLoad)

watch(welcomeOverlayOpen, (open) => {
  if (!open) presentNext()
})

watch(loggedIn, () => {
  show.value = false
  current.value = null
  scheduleLoad()
})
</script>

<template>
  <van-popup
    :show="show"
    round
    closeable
    position="center"
    :close-on-click-overlay="false"
    :style="popupStyle"
    class="home-popup"
    @update:show="(v: boolean) => { if (!v && show) void closeCurrent(false) }"
  >
    <div v-if="current" class="home-popup-card" :class="{ clickable: canClickImage }">
      <button
        v-if="canClickImage"
        type="button"
        class="home-popup-hit"
        :aria-label="current.title?.['zh-HK'] || 'promo'"
        @click="onImageClick"
      >
        <img class="home-popup-img" :src="assetUrl(current.image_url)" alt="" />
      </button>
      <img v-else class="home-popup-img" :src="assetUrl(current.image_url)" alt="" />
    </div>
  </van-popup>
</template>

<style scoped>
.home-popup-card {
  width: 100%;
  line-height: 0;
}
.home-popup-hit {
  display: block;
  width: 100%;
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: pointer;
  line-height: 0;
}
.home-popup-img {
  display: block;
  width: 100%;
  height: auto;
  border-radius: 0.75rem;
  pointer-events: none;
}
.home-popup-card:not(.clickable) .home-popup-img {
  pointer-events: none;
}
</style>

<style>
.home-popup.van-popup {
  overflow: visible;
  background: transparent !important;
}
.home-popup .van-popup__close-icon {
  color: #fff;
  top: -0.15rem;
  right: -0.15rem;
  font-size: 1.35rem;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.45);
}
</style>
