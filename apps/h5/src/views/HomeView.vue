<script setup lang="ts">
import { computed, onActivated, onDeactivated, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import AppHeader from '../components/AppHeader.vue'
import ProductCard from '../components/ProductCard.vue'
import HomeFooter from '../components/HomeFooter.vue'
import { banners as mockBanners } from '../mock/data'
import {
  fetchHomeBanners,
  fetchHomeOps,
  fetchHomeRecommendations,
  fetchHomeSections,
  type HomeBanner,
  type HomeMarquee,
  type HomeRecTab,
  type HomeSection,
  type ProductCard as Card,
} from '../api/catalog'
import { fetchSeckillSessions, type SeckillSession } from '../api/m7'
import { api, ApiError } from '../api/client'
import { useHomeCatalog } from '../composables/useHomeCatalog'
import { restoreRouteScroll, saveRouteScroll } from '../composables/useRouteScroll'
import { useLocaleText } from '../composables/useLocaleText'
import { isLoggedIn, loggedIn, memberState, syncSessionFromStorage } from '../composables/useSession'
import { assetUrl } from '../utils/assetUrl'

defineOptions({ name: 'HomeView' })

const { t, locale } = useI18n()
const { text } = useLocaleText()
const router = useRouter()

const feedTabs: HomeRecTab[] = ['new', 'hot', 'for_you']
const FEED_PAGE = 16
const feedTab = ref(0)
const feedItems = ref<Card[]>([])
const feedBusy = ref(false)
/** van-list 加載態 */
const feedLoading = ref(false)
const feedError = ref(false)
const feedOffset = ref(0)
const feedTotal = ref(0)
/** 首屏／切 tab 尚未有貨時顯示骨架 */
const feedInitial = ref(true)
const catsInitial = ref(true)
const sectionsInitial = ref(true)
const flashInitial = ref(true)
/** 下拉刷新中（van-pull-refresh v-model） */
const pulling = ref(false)
/** 上次真正打接口刷新的時間戳；30s 內再拉只提示、不請求 */
const REFRESH_COOLDOWN_MS = 30_000
let lastRefreshAt = 0
/** 防止 tab 切換與 list 觸底並發打亂 offset */
let feedSeq = 0
const email = ref('')
const subscribed = ref(false)
const subscribeBusy = ref(false)
/** 秒殺倒計時剩餘毫秒（動態）；目標為進行中→結束時間，未開始→開始時間 */
const flashRemain = ref(0)
const flashSession = ref<SeckillSession | null>(null)
const flashNow = ref(Date.now())
let flashTimer: number | null = null
const showSubscribe = computed(() => !(loggedIn.value && subscribed.value))
const banners = ref<HomeBanner[]>(
  mockBanners.map((b, i) => ({
    id: i + 1,
    image_url: b.image,
    title: { 'zh-HK': '', en: '' },
    link_type: 'none',
    link_value: '',
    sort_order: i,
    status: 1,
  })),
)
const marquee = ref<HomeMarquee>(null)
const footerSlogan = ref<{ 'zh-HK': string; en: string } | null>(null)
const opsLoaded = ref(false)
const sections = ref<HomeSection[]>([])
// A = display buffer (module-scoped inside useHomeCatalog); refresh writes B then A = B
const { products, categories, refresh } = useHomeCatalog()

const promoText = computed(() => {
  if (marquee.value?.enabled) {
    const loc = String(locale.value || 'zh-HK')
    return loc.startsWith('en') ? marquee.value.text.en || marquee.value.text['zh-HK'] : marquee.value.text['zh-HK'] || marquee.value.text.en
  }
  return t('home.promo')
})

async function loadSubscribeStatus() {
  if (!isLoggedIn()) {
    subscribed.value = false
    return
  }
  if (memberState.value?.email) {
    email.value = memberState.value.email
  }
  try {
    const data = await api<{ subscribed: boolean; email?: string | null }>(
      '/api/v1/newsletter/me',
      {},
      'member',
    )
    subscribed.value = Boolean(data.subscribed)
    if (data.email) email.value = data.email
  } catch {
    subscribed.value = false
  }
}

/**
 * 加載一頁推薦。reset=true 時從 offset 0 重拉並替換列表。
 * 服務端：有序切片後頁內 shuffle；offset 越界後取模往復。
 */
async function loadFeed(reset = false) {
  const tab = feedTabs[feedTab.value] || 'new'
  const seq = ++feedSeq
  if (reset) {
    feedOffset.value = 0
    feedError.value = false
  }
  const offset = reset ? 0 : feedOffset.value
  feedBusy.value = true
  feedLoading.value = true
  feedError.value = false
  try {
    const data = await fetchHomeRecommendations(tab, FEED_PAGE, offset)
    if (seq !== feedSeq) return
    const items = data.items || []
    feedTotal.value = Number(data.total || 0)
    feedOffset.value = Number(data.next_offset ?? offset + items.length)
    feedItems.value = reset ? items : feedItems.value.concat(items)
  } catch {
    if (seq !== feedSeq) return
    feedError.value = true
    if (reset || !feedItems.value.length) {
      // 首屏回退：本地 catalog 緩衝
      const local =
        tab === 'new'
          ? [...products.value].reverse().slice(0, FEED_PAGE)
          : products.value.slice(0, FEED_PAGE)
      feedItems.value = local
      feedOffset.value = local.length
      feedTotal.value = local.length
    }
  } finally {
    if (seq === feedSeq) {
      feedBusy.value = false
      feedLoading.value = false
      feedInitial.value = false
    }
  }
}

async function onFeedLoad() {
  // van-list：觸底追加；往復由服務端 offset 取模完成，故有貨時 finished 恒 false
  if (feedBusy.value) return
  await loadFeed(false)
}

const feedFinished = computed(
  () => feedTotal.value === 0 && !feedBusy.value && feedItems.value.length === 0,
)

async function loadBanners() {
  try {
    const items = await fetchHomeBanners()
    if (items.length) banners.value = items
  } catch {
    /* 保留現有 */
  }
}

async function loadOps() {
  try {
    const ops = await fetchHomeOps()
    marquee.value = ops.marquee
    footerSlogan.value = ops.footer_slogan
  } catch {
    /* 保留現有 */
  } finally {
    opsLoaded.value = true
  }
}

async function loadSections() {
  try {
    sections.value = await fetchHomeSections()
  } catch {
    /* 保留現有 */
  } finally {
    sectionsInitial.value = false
  }
}

/** 下拉刷新：只重拉當前首頁可見狀態（當前 feed tab）；30s 內拒刷 */
async function onPullRefresh() {
  const now = Date.now()
  if (lastRefreshAt > 0 && now - lastRefreshAt < REFRESH_COOLDOWN_MS) {
    showToast(t('home.refreshFresh'))
    pulling.value = false
    return
  }
  try {
    const results = await Promise.allSettled([
      refresh().then(() => loadFeed(true)),
      loadSeckillHome().then(() => startFlashTimer()),
      loadBanners(),
      loadOps(),
      loadSections(),
    ])
    if (results.some((r) => r.status === 'fulfilled')) {
      lastRefreshAt = Date.now()
      showToast(t('home.refreshDone'))
      window.scrollTo({ top: 0, left: 0, behavior: 'auto' })
    } else {
      showToast(t('home.refreshFail'))
    }
  } catch {
    showToast(t('home.refreshFail'))
  } finally {
    pulling.value = false
  }
}

function onSectionViewAll(sec: HomeSection) {
  if (sec.view_all_type === 'category' && sec.view_all_value) {
    goList(sec.view_all_value)
    return
  }
  if (sec.view_all_type === 'url' && sec.view_all_value) {
    if (sec.view_all_value.startsWith('http')) {
      window.open(sec.view_all_value, '_blank', 'noopener')
    } else {
      router.push(sec.view_all_value)
    }
    return
  }
  if (sec.view_all_type === 'none') return
  router.push({ name: 'product-list', query: { section: sec.code } })
}

const SCROLL_KEY = 'home'
let homeBooted = false

onMounted(() => {
  // keep-alive 下 onMounted 只跑一次：首屏拉數
  console.log('新包 v1.28')
  homeBooted = true
  feedInitial.value = true
  catsInitial.value = true
  void refresh()
    .catch(() => {})
    .finally(() => {
      catsInitial.value = false
    })
    .then(() => loadFeed(true))
  void loadSubscribeStatus()
  void loadSeckillHome().then(() => startFlashTimer())
  void loadBanners()
  void loadOps()
  void loadSections()
})

onDeactivated(() => {
  saveRouteScroll(SCROLL_KEY)
  stopFlashTimer()
  unbindBackTopScroll()
})

onUnmounted(() => {
  stopFlashTimer()
  unbindBackTopScroll()
})

let homeFirstActivate = true
onActivated(() => {
  startFlashTimer()
  bindBackTopScroll()
  // 首次 mounted+activated 同幀，無需還原
  if (homeFirstActivate) {
    homeFirstActivate = false
    return
  }
  void loadSeckillHome()
  // 從登錄返回：同步訂閱狀態；其餘實例內狀態保留
  syncSessionFromStorage()
  void loadSubscribeStatus()
  if (homeBooted) {
    restoreRouteScroll(SCROLL_KEY)
  }
})

watch(feedTab, () => {
  feedItems.value = []
  feedOffset.value = 0
  feedTotal.value = 0
  feedInitial.value = true
  feedError.value = false
  void loadFeed(true)
})

function onLink(linkType: string, linkValue: string) {
  if (linkType === 'product' && linkValue) {
    router.push({ name: 'product-detail', params: { id: linkValue } })
  } else if (linkType === 'category' && linkValue) {
    goList(linkValue)
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

function onBannerClick(b: HomeBanner) {
  onLink(b.link_type, b.link_value)
}

function onPromoClick() {
  if (!marquee.value?.enabled) return
  onLink(marquee.value.link_type, marquee.value.link_value)
}

/** 首頁秒殺橫滑：取當前場次商品（最多 4），價為秒殺價 */
const flashVisible = computed<Card[]>(() => {
  const session = flashSession.value
  if (!session?.products?.length) return []
  return session.products.slice(0, 4).map((p) => ({
    id: p.product_id,
    db_id: 0,
    code: p.product_id,
    name: p.name,
    price: p.seckill_price,
    compare_at: p.origin_price,
    sold: String(p.sold ?? 0),
    image: assetUrl(p.image),
    category: '',
    category_id: 0,
    badges: ['flash'],
    badge_items: [{ code: 'flash', color: 'var(--sm-accent)', name: { 'zh-HK': '秒殺', en: 'Flash' } }],
  }))
})

const flashHint = computed(() => {
  const s = flashSession.value
  if (!s) return t('home.flashUpcoming')
  if (s.status === 'live') return t('home.flashLive')
  return t('home.flashUpcoming')
})

function parseSessionTime(v: string): number {
  // API 多為 "YYYY-MM-DD HH:mm:ss"（可視為本地／伺服器時間）
  const t = Date.parse(v.includes('T') ? v : v.replace(' ', 'T'))
  return Number.isFinite(t) ? t : 0
}

function tickFlashCountdown() {
  flashNow.value = Date.now()
  const s = flashSession.value
  if (!s) {
    flashRemain.value = 0
    return
  }
  const start = parseSessionTime(s.starts_at)
  const end = parseSessionTime(s.ends_at)
  const now = flashNow.value
  if (s.status === 'upcoming' || now < start) {
    flashRemain.value = Math.max(0, start - now)
  } else {
    flashRemain.value = Math.max(0, end - now)
  }
}

function startFlashTimer() {
  stopFlashTimer()
  tickFlashCountdown()
  flashTimer = window.setInterval(tickFlashCountdown, 50)
}

function stopFlashTimer() {
  if (flashTimer != null) {
    window.clearInterval(flashTimer)
    flashTimer = null
  }
}

const showCatsSkeleton = computed(
  () => !homeCategories.value.length && catsInitial.value,
)
const showFeedSkeleton = computed(
  () => !feedItems.value.length && (feedBusy.value || feedInitial.value) && !feedError.value,
)
const showFlashSkeleton = computed(
  () => flashInitial.value && !flashSession.value && !flashVisible.value.length,
)
const showSectionsSkeleton = computed(
  () => sectionsInitial.value && !sections.value.length,
)

async function loadSeckillHome() {
  try {
    const data = await fetchSeckillSessions()
    const items = data.items || []
    // 優先進行中，否則最近未開始
    flashSession.value =
      items.find((x) => x.status === 'live') || items.find((x) => x.status === 'upcoming') || items[0] || null
    tickFlashCountdown()
  } catch {
    flashSession.value = null
    flashRemain.value = 0
  } finally {
    flashInitial.value = false
  }
}

/** Home cats use backend-configured `categories.icon` only (admin/seed), not hardcoded assets. */
const homeCategories = computed(() =>
  categories.value.map((c) => ({
    ...c,
    icon: c.icon || null,
  })),
)

function onCatImgError(event: Event) {
  const img = event.target as HTMLImageElement
  img.removeAttribute('src')
  img.style.display = 'none'
}

async function onSubscribe() {
  const target = email.value.trim()
  if (!target) {
    showToast(t('home.emailPlaceholder'))
    return
  }
  subscribeBusy.value = true
  try {
    await api(
      '/api/v1/newsletter/subscribe',
      { method: 'POST', body: JSON.stringify({ email: target }) },
      isLoggedIn() ? 'member' : 'none',
    )
    showToast(t('home.subscribeOk'))
    if (isLoggedIn()) {
      subscribed.value = true
    } else {
      email.value = ''
    }
  } catch (e) {
    const msg = e instanceof ApiError && e.message ? e.message : t('home.subscribeFail')
    showToast(msg)
  } finally {
    subscribeBusy.value = false
  }
}

function goList(categoryId?: string) {
  router.push({ name: 'product-list', query: categoryId ? { category: categoryId } : {} })
}

/** 分類區標題 /「更多」→ 底欄分類頁 */
function goCategory() {
  router.push({ name: 'category' })
}

/** 回頂：直接聽 window（避開 van-back-top 在部分 WebView 找不到滾動父級） */
const BACK_TOP_OFFSET = 280
const showBackTop = ref(false)
let backTopBound = false

function onWindowScrollForBackTop() {
  const y = window.scrollY || document.documentElement.scrollTop || 0
  showBackTop.value = y >= BACK_TOP_OFFSET
}

function bindBackTopScroll() {
  if (backTopBound || typeof window === 'undefined') return
  window.addEventListener('scroll', onWindowScrollForBackTop, { passive: true })
  backTopBound = true
  onWindowScrollForBackTop()
}

function unbindBackTopScroll() {
  if (!backTopBound || typeof window === 'undefined') return
  window.removeEventListener('scroll', onWindowScrollForBackTop)
  backTopBound = false
  showBackTop.value = false
}

function scrollHomeTop(smooth = true) {
  window.scrollTo({ top: 0, left: 0, behavior: smooth ? 'smooth' : 'auto' })
}

const flashClock = computed(() => {
  const t = Math.max(0, flashRemain.value)
  const h = Math.floor(t / 3600e3)
  const m = Math.floor((t % 3600e3) / 60e3)
  const s = Math.floor((t % 60e3) / 1e3)
  const ms = Math.floor(t % 1000)
  const pad2 = (n: number) => String(n).padStart(2, '0')
  const pad3 = (n: number) => String(n).padStart(3, '0')
  return `${pad2(h)}:${pad2(m)}:${pad2(s)}.${pad3(ms)}`
})

const tabLabel = (tab: HomeRecTab) => {
  if (tab === 'new') return t('home.new')
  if (tab === 'for_you') return t('home.forYou')
  return t('home.hot')
}
</script>

<template>
  <div class="page home">
    <AppHeader show-logo />
    <!-- Header / Tabbar 外：僅內容區下拉；滾到頂才觸發（van 內建） -->
    <van-pull-refresh
      v-model="pulling"
      class="home-pull"
      :pulling-text="t('home.refreshPulling')"
      :loosing-text="t('home.refreshLoose')"
      :loading-text="t('home.refreshLoading')"
      @refresh="onPullRefresh"
    >
      <section class="hero-wrap">
        <van-swipe class="hero" :autoplay="4000" indicator-color="var(--sm-primary)">
          <van-swipe-item v-for="b in banners" :key="b.id">
            <button type="button" class="hero-item" @click="onBannerClick(b)">
              <img :src="b.image_url" :alt="text(b.title) || ''" />
            </button>
          </van-swipe-item>
        </van-swipe>
      </section>

      <button
        v-if="!opsLoaded || marquee?.enabled"
        type="button"
        class="promo"
        :class="{ clickable: !!marquee?.enabled && marquee.link_type !== 'none' }"
        @click="onPromoClick"
      >
        {{ promoText }}
      </button>

      <section v-if="showCatsSkeleton || homeCategories.length" class="cats">
        <div class="section-title">
          <button type="button" class="section-title-btn" @click="goCategory">
            <h2>{{ t('home.categories') }}</h2>
          </button>
          <button type="button" class="more" @click="goCategory">
            {{ t('account.more') }}
          </button>
        </div>
        <div v-if="showCatsSkeleton" class="cats-row" aria-hidden="true">
          <div v-for="n in 4" :key="`sk-cat-${n}`" class="sk-cat">
            <span class="sk-circle sk-pulse" />
            <span class="sk-line sk-pulse" />
          </div>
        </div>
        <div v-else class="cats-row">
          <button v-for="c in homeCategories" :key="c.id" type="button" @click="goList(c.code)">
            <img
              v-if="c.icon"
              :src="c.icon"
              :alt="text(c.name)"
              @error="onCatImgError($event)"
            />
            <span v-else class="ph" />
            <span class="cat-name">{{ text(c.name) }}</span>
          </button>
        </div>
      </section>

      <section v-if="showFlashSkeleton" class="flash flash--sk" aria-hidden="true">
        <div class="section-title">
          <span class="sk-line sk-pulse sk-title" />
          <span class="sk-line sk-pulse sk-more" />
        </div>
        <div class="flash-row">
          <div v-for="n in 2" :key="`sk-flash-${n}`" class="sk-card">
            <div class="sk-media sk-pulse" />
            <div class="sk-meta">
              <span class="sk-line sk-pulse" />
              <span class="sk-line sk-pulse sk-short" />
            </div>
          </div>
        </div>
      </section>
      <section v-else-if="flashSession || flashVisible.length" class="flash">
        <div class="section-title">
          <h2>{{ t('home.flashSale') }}</h2>
          <button type="button" class="more" @click="router.push({ name: 'seckill' })">{{ t('account.more') }}</button>
          <div class="count" :title="flashClock">
            <span class="clock">{{ flashClock }}</span>
          </div>
        </div>
        <p class="flash-hint">{{ flashHint }}</p>
        <div class="flash-row">
          <ProductCard v-for="p in flashVisible" :key="p.id" :product="p" />
        </div>
      </section>

      <section v-if="showSectionsSkeleton" class="showcase" aria-hidden="true">
        <div class="section-title">
          <span class="sk-line sk-pulse sk-title" />
          <span class="sk-line sk-pulse sk-more" />
        </div>
        <div class="grid">
          <div v-for="m in 4" :key="`sk-sec-card-${m}`" class="sk-card">
            <div class="sk-media sk-pulse" />
            <div class="sk-meta">
              <span class="sk-line sk-pulse" />
              <span class="sk-line sk-pulse sk-short" />
            </div>
          </div>
        </div>
      </section>
      <template v-else>
        <section v-for="sec in sections" :key="sec.id" class="showcase">
          <div class="section-title">
            <h2>{{ text(sec.title) }}</h2>
            <button
              v-if="sec.view_all_type !== 'none'"
              type="button"
              class="more"
              @click="onSectionViewAll(sec)"
            >
              {{ t('home.viewAll') }}
            </button>
          </div>
          <div class="grid">
            <ProductCard v-for="p in sec.items" :key="p.id" :product="p" />
          </div>
        </section>
      </template>

      <section class="feed">
        <div class="tabs">
          <button
            v-for="(tab, i) in feedTabs"
            :key="tab"
            type="button"
            :class="{ on: feedTab === i }"
            @click="feedTab = i"
          >
            {{ tabLabel(tab) }}
          </button>
        </div>
        <div v-if="showFeedSkeleton" class="grid" aria-hidden="true">
          <div v-for="n in 6" :key="`sk-feed-${n}`" class="sk-card">
            <div class="sk-media sk-pulse" />
            <div class="sk-meta">
              <span class="sk-line sk-pulse" />
              <span class="sk-line sk-pulse sk-short" />
            </div>
          </div>
        </div>
        <van-list
          v-else
          v-model:loading="feedLoading"
          v-model:error="feedError"
          :finished="feedFinished"
          :loading-text="t('home.feedLoading')"
          :error-text="t('home.feedLoadFail')"
          :immediate-check="false"
          @load="onFeedLoad"
        >
          <div class="grid">
            <ProductCard
              v-for="(p, idx) in feedItems"
              :key="`${p.id}-${idx}`"
              :product="p"
            />
          </div>
          <p v-if="!feedBusy && !feedItems.length" class="feed-empty">{{ t('home.feedEmpty') }}</p>
        </van-list>
      </section>

      <section v-if="showSubscribe" class="subscribe">
        <h2>{{ t('home.subscribeTitle') }}</h2>
        <div class="row">
          <input v-model="email" type="email" :placeholder="t('home.emailPlaceholder')" />
          <button type="button" :disabled="subscribeBusy" @click="onSubscribe">{{ t('home.subscribe') }}</button>
        </div>
      </section>
      <HomeFooter :slogan="footerSlogan" />
    </van-pull-refresh>

    <Teleport to="body">
      <button
        v-show="showBackTop"
        type="button"
        class="sm-back-top"
        :aria-label="t('home.backTop')"
        @click="scrollHomeTop(true)"
      >
        <van-icon name="back-top" />
      </button>
    </Teleport>
  </div>
</template>

<style scoped>
.home {
  padding-top: var(--sm-header-h);
  padding-bottom: calc(var(--sm-tabbar-h) + env(safe-area-inset-bottom, 0rem));
}
.home-pull {
  min-height: calc(100vh - var(--sm-header-h) - var(--sm-tabbar-h));
  overflow: visible;
}
.hero-wrap {
  position: relative;
}
.hero {
  height: calc(100vw * 589 / 1600);
}
.hero-item {
  width: 100%;
  height: 100%;
  border: 0;
  padding: 0;
  background: transparent;
  display: block;
}
.hero-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.promo {
  display: block;
  width: 100%;
  margin: 0;
  border: 0;
  text-align: left;
  padding: 0.625rem 0.875rem;
  background: var(--sm-promo-bg);
  color: var(--sm-text);
  font: inherit;
  font-size: 0.75rem;
}
.promo.clickable {
  cursor: pointer;
}
.section-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  padding: 0.875rem 0.875rem 0.5rem;
}
.section-title h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.25;
}
.section-title-btn {
  margin: 0;
  padding: 0;
  border: 0;
  background: none;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.section-title-btn h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.25;
}
.section-title .more {
  margin-left: auto;
  margin-right: 0.5rem;
  border: 0;
  background: none;
  color: var(--sm-primary);
  font-size: 0.75rem;
}
.flash {
  margin: 0.5rem 0.75rem 0.75rem;
  padding: 0.65rem 0 0.25rem;
  border-radius: var(--sm-radius);
  background: linear-gradient(135deg, #f7edf9 0%, #c3d9fb 100%);
  overflow: hidden;
}
.flash .section-title {
  padding-top: 0;
}
.flash-hint {
  margin: 0;
  padding: 0 0.875rem 0.5rem;
  font-size: 1rem;
  color: var(--sm-text);
}
.count .clock {
  display: inline-block;
  min-width: 7.5rem;
  padding: 0.2rem 0.45rem;
  border-radius: 0.25rem;
  background: rgba(255, 255, 255, 0.72);
  color: #9b3bfc;
  text-align: center;
  font-size: 1rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  letter-spacing: 0.02em;
}
.flash-row {
  display: flex;
  gap: 0.75rem;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  padding: 0 0.875rem 0.75rem;
}
.flash-row :deep(.card) {
  flex: 0 0 calc((100% - 0.75rem) / 2);
  scroll-snap-align: start;
  background: rgba(255, 255, 255, 0.88);
  border-radius: 0.5rem;
}
.cats {
  padding-bottom: 0.5rem;
}
.cats-row {
  display: flex;
  gap: 0.5rem;
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  padding: 0 0.875rem 0.75rem;
  -webkit-overflow-scrolling: touch;
}
.cats-row button {
  flex: 0 0 calc((100% - 1.5rem) / 4);
  scroll-snap-align: start;
  border: 0;
  background: transparent;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.75rem;
  color: var(--sm-text);
  min-width: 0;
  padding: 0;
}
.cats-row img,
.cats-row .ph {
  width: 100%;
  max-width: 4.5rem;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 50%;
  background: var(--sm-bg-soft);
}
.cats-row .cat-name {
  width: 100%;
  text-align: center;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.tabs {
  display: flex;
  gap: 1rem;
  padding: 0.5rem 0.875rem;
}
.tabs button {
  border: 0;
  background: transparent;
  color: var(--sm-text-muted);
  /* 與「商品分類」section-title h2 同字號 */
  font-size: 1rem;
  line-height: 1.25;
}
.tabs button.on {
  color: var(--sm-primary);
  font-weight: 700;
}
.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  padding: 0.25rem 0.875rem 1rem;
}
.feed-empty {
  text-align: center;
  color: #999;
  font-size: 0.8125rem;
  padding: 0.5rem 0 1rem;
}
.subscribe {
  padding: 1rem 0.875rem 1.5rem;
}
.subscribe h2 {
  margin: 0 0 0.75rem;
  font-size: 1rem;
}
.row {
  display: flex;
  gap: 0.5rem;
}
.row input {
  flex: 1;
  border: 0.0625rem solid var(--sm-border);
  border-radius: 0.5rem;
  padding: 0.625rem 0.75rem;
}
.row button {
  border: 0;
  border-radius: 0.5rem;
  background: var(--sm-primary);
  color: #fff;
  padding: 0 0.875rem;
}

/* —— 加載骨架 —— */
.sk-pulse {
  background: linear-gradient(
    90deg,
    var(--sm-bg-soft) 0%,
    #ebe6de 45%,
    var(--sm-bg-soft) 90%
  );
  background-size: 200% 100%;
  animation: sk-shimmer 1.2s ease-in-out infinite;
}
@keyframes sk-shimmer {
  0% {
    background-position: 100% 0;
  }
  100% {
    background-position: -100% 0;
  }
}
.sk-cat {
  flex: 0 0 calc((100% - 1.5rem) / 4);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
  min-width: 0;
}
.sk-circle {
  width: 100%;
  max-width: 4.5rem;
  aspect-ratio: 1;
  border-radius: 50%;
  display: block;
}
.sk-line {
  display: block;
  height: 0.625rem;
  width: 70%;
  border-radius: 0.25rem;
}
.sk-line.sk-title {
  width: 5.5rem;
  height: 0.875rem;
}
.sk-line.sk-more {
  width: 2.5rem;
  height: 0.625rem;
  margin-left: auto;
}
.sk-line.sk-short {
  width: 45%;
}
.sk-card {
  min-width: 0;
}
.sk-media {
  width: 100%;
  aspect-ratio: 1;
  border-radius: 0.5rem;
}
.sk-meta {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  padding: 0.5rem 0.125rem 0;
}
.sk-meta .sk-line {
  width: 85%;
}
.flash--sk .flash-row .sk-card {
  flex: 0 0 calc((100% - 0.75rem) / 2);
}
.flash--sk .section-title {
  gap: 0.75rem;
}
</style>
