<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
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
  type HomeBanner,
  type HomeMarquee,
  type HomeRecTab,
  type ProductCard as Card,
} from '../api/catalog'
import { api, ApiError } from '../api/client'
import { useHomeCatalog } from '../composables/useHomeCatalog'
import { useLocaleText } from '../composables/useLocaleText'
import { isLoggedIn, memberState } from '../composables/useSession'

const { t, locale } = useI18n()
const { text } = useLocaleText()
const router = useRouter()

const feedTabs: HomeRecTab[] = ['hot', 'new', 'for_you']
const feedTab = ref(0)
const feedItems = ref<Card[]>([])
const feedBusy = ref(false)
const email = ref('')
const subscribed = ref(false)
const subscribeBusy = ref(false)
const flashRemain = ref(2 * 3600e3 + 14 * 60e3 + 8e3)
const showSubscribe = computed(() => !(isLoggedIn() && subscribed.value))
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

async function loadFeed() {
  const tab = feedTabs[feedTab.value] || 'hot'
  feedBusy.value = true
  try {
    const data = await fetchHomeRecommendations(tab, 20)
    feedItems.value = data.items || []
  } catch {
    // 回退：本地 catalog 緩衝
    if (tab === 'new') {
      feedItems.value = [...products.value].reverse().slice(0, 20)
    } else {
      feedItems.value = products.value.slice(0, 20)
    }
  } finally {
    feedBusy.value = false
  }
}

onMounted(() => {
  void refresh().then(() => loadFeed())
  void loadSubscribeStatus()
  void fetchHomeBanners()
    .then((items) => {
      if (items.length) banners.value = items
    })
    .catch(() => undefined)
  void fetchHomeOps()
    .then((ops) => {
      marquee.value = ops.marquee
      footerSlogan.value = ops.footer_slogan
      opsLoaded.value = true
    })
    .catch(() => {
      opsLoaded.value = true
    })
})

watch(feedTab, () => {
  void loadFeed()
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

const flashVisible = computed(() => products.value.filter((p) => p.badges.includes('flash')).slice(0, 4))
/** Home cats use backend-configured `categories.icon` only (admin/seed), not hardcoded assets. */
const homeCategories = computed(() =>
  categories.value.slice(0, 6).map((c) => ({
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

const hh = computed(() => String(Math.floor(flashRemain.value / 3600e3)).padStart(2, '0'))
const mm = computed(() => String(Math.floor((flashRemain.value % 3600e3) / 60e3)).padStart(2, '0'))
const ss = computed(() => String(Math.floor((flashRemain.value % 60e3) / 1e3)).padStart(2, '0'))

const tabLabel = (tab: HomeRecTab) => {
  if (tab === 'new') return t('home.new')
  if (tab === 'for_you') return t('home.forYou')
  return t('home.hot')
}
</script>

<template>
  <div class="page home">
    <section class="hero-wrap">
      <van-swipe class="hero" :autoplay="4000" indicator-color="var(--sm-primary)">
        <van-swipe-item v-for="b in banners" :key="b.id">
          <button type="button" class="hero-item" @click="onBannerClick(b)">
            <img :src="b.image_url" :alt="text(b.title) || ''" />
          </button>
        </van-swipe-item>
      </van-swipe>
      <AppHeader show-logo variant="overlay" />
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

    <section class="flash">
      <div class="section-title">
        <h2>{{ t('home.flashSale') }}</h2>
        <button type="button" class="more" @click="router.push({ name: 'seckill' })">{{ t('account.more') }}</button>
        <div class="count">
          <span>{{ hh }}</span>:<span>{{ mm }}</span>:<span>{{ ss }}</span>
        </div>
      </div>
      <div class="flash-row">
        <ProductCard v-for="p in flashVisible" :key="p.id" :product="p" />
      </div>
    </section>

    <section class="cats">
      <button v-for="c in homeCategories" :key="c.id" type="button" @click="goList(c.code)">
        <img
          v-if="c.icon"
          :src="c.icon"
          :alt="text(c.name)"
          @error="onCatImgError($event)"
        />
        <span v-else class="ph" />
        <span>{{ text(c.name) }}</span>
      </button>
    </section>

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
      <div class="grid">
        <ProductCard v-for="p in feedItems" :key="p.id" :product="p" />
      </div>
      <p v-if="!feedBusy && !feedItems.length" class="feed-empty">{{ t('home.feedEmpty') }}</p>
    </section>

    <section v-if="showSubscribe" class="subscribe">
      <h2>{{ t('home.subscribeTitle') }}</h2>
      <div class="row">
        <input v-model="email" type="email" :placeholder="t('home.emailPlaceholder')" />
        <button type="button" :disabled="subscribeBusy" @click="onSubscribe">{{ t('home.subscribe') }}</button>
      </div>
    </section>
    <HomeFooter :slogan="footerSlogan" />
  </div>
</template>

<style scoped>
.home {
  padding-bottom: calc(var(--sm-tabbar-h) + env(safe-area-inset-bottom, 0rem));
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
}
.section-title .more {
  margin-left: auto;
  margin-right: 0.5rem;
  border: 0;
  background: none;
  color: var(--sm-primary);
  font-size: 0.75rem;
}
.count span {
  display: inline-block;
  min-width: 1.5rem;
  padding: 0.125rem 0.25rem;
  border-radius: 0.25rem;
  background: var(--sm-primary-soft);
  color: var(--sm-text-secondary);
  text-align: center;
  font-size: 0.75rem;
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
}
.cats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
  padding: 0.5rem 0.875rem 1rem;
}
.cats button {
  border: 0;
  background: transparent;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.75rem;
  color: var(--sm-text);
}
.cats img,
.ph {
  width: 4.5rem;
  height: 4.5rem;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 50%;
  background: var(--sm-bg-soft);
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
  font-size: 0.875rem;
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
</style>
