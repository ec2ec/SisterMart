<script setup lang="ts">
import { onActivated, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { dismissHomePopup, fetchHomePopups, type HomePopup } from '../api/catalog'
import { peekJustRegistered } from '../composables/useAuthUi'
import { welcomeOverlayOpen } from '../composables/useHomePopupGate'
import { isLoggedIn, loggedIn } from '../composables/useSession'
import { assetUrl } from '../utils/assetUrl'

const STORAGE_KEY = 'sm.home_popup_dismiss.v1'

type DismissRecord = { at: string; frequency: string }
type DismissStore = Record<string, DismissRecord>

const router = useRouter()
const show = ref(false)
const current = ref<HomePopup | null>(null)
const queue = ref<HomePopup[]>([])
let loading = false
let bootTimer: number | null = null

function readStore(): DismissStore {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    return parsed && typeof parsed === 'object' ? (parsed as DismissStore) : {}
  } catch {
    return {}
  }
}

function writeStore(store: DismissStore) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(store))
  } catch {
    /* ignore quota */
  }
}

function today(): string {
  const d = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function guestShouldHide(row: HomePopup): boolean {
  const freq = row.frequency || 'once'
  if (freq === 'always') return false
  const rec = readStore()[String(row.id)]
  if (!rec) return false
  if (freq === 'once') return true
  if (freq === 'daily') return (rec.at || '').slice(0, 10) === today()
  return false
}

function rememberGuestDismiss(row: HomePopup) {
  const freq = row.frequency || 'once'
  if (freq === 'always') return
  const store = readStore()
  store[String(row.id)] = { at: new Date().toISOString(), frequency: freq }
  writeStore(store)
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
      /* 關閉仍繼續隊列 */
    }
  } else {
    rememberGuestDismiss(row)
  }
}

async function closeCurrent(navigate = false) {
  const row = current.value
  if (!row) return
  show.value = false
  queue.value = queue.value.filter((x) => x.id !== row.id)
  current.value = null
  await persistDismiss(row)
  if (navigate && row.link_type !== 'none' && row.link_value) {
    onLink(row.link_type, row.link_value)
  }
  // 下一張稍延遲，避免閃爍
  window.setTimeout(() => presentNext(), 120)
}

async function loadQueue() {
  if (loading) return
  loading = true
  try {
    const auth = isLoggedIn() ? 'member' : 'none'
    let items = await fetchHomePopups(auth)
    if (!isLoggedIn()) {
      items = items.filter((x) => !guestShouldHide(x))
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
  // 等歡迎彈窗有機會先搶閘
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
    :style="{ width: '86%', maxWidth: '22rem', background: 'transparent' }"
    class="home-popup"
    @update:show="(v: boolean) => { if (!v && show) void closeCurrent(false) }"
  >
    <button
      v-if="current"
      type="button"
      class="home-popup-card"
      :aria-label="current.title?.['zh-HK'] || 'promo'"
      @click="closeCurrent(true)"
    >
      <img class="home-popup-img" :src="assetUrl(current.image_url)" alt="" />
    </button>
  </van-popup>
</template>

<style scoped>
.home-popup-card {
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
