<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showDialog, showToast, type SwipeInstance } from 'vant'
import AppHeader from '../components/AppHeader.vue'
import ProductCard from '../components/ProductCard.vue'
import ProductZoom from '../components/ProductZoom.vue'
import { fetchProduct, fetchProducts, skuKey, type ProductCard as Card, type ProductDetail } from '../api/catalog'
import { addCartItem } from '../api/trade'
import { fetchSeckillProduct, postShareView } from '../api/m7'
import { useLocaleText } from '../composables/useLocaleText'
import { isFavorite, requestFavorite } from '../composables/useFavorites'
import { captureRefFromQuery, storedRef } from '../composables/useReferral'
import { refreshCartBadge } from '../composables/useCartBadge'
import { currentMember, isLoggedIn } from '../composables/useSession'
import { ApiError } from '../api/client'
import { buildShareUrl } from '../utils/shareUrl'
import { assetUrl } from '../utils/assetUrl'
import { useTabShell } from '../composables/useTabShell'

const { t } = useI18n()
const { text } = useLocaleText()
const route = useRoute()
const router = useRouter()
const { closeOverlay } = useTabShell()

const product = ref<ProductDetail | null>(null)
const related = ref<Card[]>([])
const slide = ref(0)
const gallerySwipe = ref<SwipeInstance>()
const zooming = ref(false)
const showAllReviews = ref(false)
const selected = ref<Record<string, string>>({})
const seckill = ref<{
  active: boolean
  price?: number
  origin?: number
  ends_at?: string
  seckill_item_id?: number
  sku_id?: number
}>({ active: false })

async function load() {
  const id = String(route.params.id || '')
  captureRefFromQuery(route.query.ref)
  product.value = await fetchProduct(id)
  const next: Record<string, string> = {}
  for (const group of product.value.spec_groups) next[group.id] = group.options[0]?.id || ''
  // Prefer heart bracelet default red|16 if available
  if (product.value.code === 'p4') {
    next.color = 'red'
    next.size = '16'
  }
  selected.value = next
  slide.value = 0
  zooming.value = false
  showAllReviews.value = false
  const list = await fetchProducts({ category: product.value.category })
  related.value = list.items.filter((item) => item.id !== product.value?.id).slice(0, 4)
  try {
    const sk = await fetchSeckillProduct(product.value.code || id)
    seckill.value = {
      active: Boolean(sk.in_seckill && sk.session_status === 'live'),
      price: sk.seckill_price,
      origin: sk.origin_price,
      ends_at: sk.ends_at,
      seckill_item_id: sk.seckill_item_id,
      sku_id: sk.sku_id,
    }
  } catch {
    seckill.value = { active: false }
  }
  const refCode = storedRef() || String(route.query.ref || '')
  if (refCode) {
    postShareView(refCode, product.value.code || id).catch(() => undefined)
  }
}

watch(() => route.params.id, load, { immediate: true })

const name = computed(() => (product.value ? text(product.value.name) : ''))
const gallery = computed(() => {
  const images = (product.value?.media || [])
    .filter((m) => m.media_type === 'image')
    .map((m) => assetUrl(m.url))
    .filter(Boolean)
  if (images.length) return images
  return (product.value?.gallery || []).map((u) => assetUrl(u)).filter(Boolean)
})
const zoomImages = computed(() => gallery.value)

const detailRaw = computed(() => (product.value ? text(product.value.detail) : ''))
const detailIsHtml = computed(() => /<\/?[a-z][\s\S]*>/i.test(detailRaw.value))
const detailHtml = computed(() => {
  const raw = detailRaw.value
  if (!detailIsHtml.value) return raw
  return raw.replace(/(src=["'])(\/[^"']+)(["'])/gi, (_, a, path, b) => `${a}${assetUrl(path)}${b}`)
})

const quote = computed(() => {
  if (!product.value) return null
  const key = skuKey(selected.value, product.value.spec_groups)
  return product.value.skus.find((sku) => sku.key === key || sku.code === key) || null
})

const price = computed(() =>
  seckill.value.active && seckill.value.price != null
    ? seckill.value.price
    : (quote.value?.price ?? product.value?.price ?? 0),
)
/** 前端默認展示 5.0；有真實評價時用聚合分 */
const ratingText = computed(() => {
  const count = Number(product.value?.rating_count ?? 0)
  const rating = Number(product.value?.rating ?? 0)
  if (count > 0 && rating > 0) return rating.toFixed(1)
  return '5.0'
})
const compareAt = computed(() =>
  seckill.value.active && seckill.value.origin != null
    ? seckill.value.origin
    : (quote.value?.compare_at ?? 0),
)
const stock = computed(() => quote.value?.stock ?? 0)
const liked = computed(() => (product.value ? isFavorite(product.value.id) : false))

const badgeItems = computed(() => {
  if (!product.value) return []
  if (product.value.badge_items?.length) return product.value.badge_items
  return (product.value.badges ?? []).map((code) => ({
    code,
    color: code === 'editor' ? 'var(--sm-badge-alt)' : 'var(--sm-primary)',
    name: { 'zh-HK': code, en: code },
  }))
})

const showTagRow = computed(
  () => badgeItems.value.length > 0 || (product.value?.tags?.length ?? 0) > 0,
)

function badgeLabel(item: { code: string; name: { 'zh-HK': string; en: string } }) {
  const fromApi = text(item.name)
  if (fromApi && fromApi !== item.code) return fromApi
  const key = `badge.${item.code}`
  const fromI18n = t(key)
  return fromI18n !== key ? fromI18n : fromApi || item.code
}

const selectedImage = computed(() => {
  if (!product.value) return ''
  for (const group of product.value.spec_groups) {
    const option = group.options.find((item) => item.id === selected.value[group.id])
    if (option?.image) return assetUrl(option.image)
  }
  return assetUrl(quote.value?.image || '')
})

watch(selectedImage, (src) => {
  if (!src) return
  const index = gallery.value.indexOf(src)
  if (index < 0 || index === slide.value) return
  slide.value = index
  // Vant 4 Swipe 無 v-model:active，需 swipeTo 同步輪播位置
  nextTick(() => gallerySwipe.value?.swipeTo(index))
})

function onGalleryChange(index: number) {
  slide.value = index
}

function onZoomChange(index: number) {
  slide.value = index
  nextTick(() => gallerySwipe.value?.swipeTo(index, { immediate: true }))
}

function openZoom(index: number) {
  slide.value = index
  zooming.value = true
}

function closeZoom() {
  zooming.value = false
  nextTick(() => gallerySwipe.value?.swipeTo(slide.value, { immediate: true }))
}

function choose(groupId: string, optionId: string) {
  selected.value = { ...selected.value, [groupId]: optionId }
}

function money(value: number) {
  return `HK$ ${value.toFixed(2)}`
}

function requireSku() {
  if (!quote.value) {
    showToast(t('pdp.pickSpec'))
    return false
  }
  if (quote.value.stock <= 0) {
    showToast(t('pdp.outOfStock'))
    return false
  }
  return true
}

async function addToCart() {
  if (seckill.value.active) {
    showToast(t('m7.seckillOnlyBuy'))
    return
  }
  if (!requireSku() || !quote.value) return
  if (!isLoggedIn()) {
    router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  try {
    await addCartItem(quote.value.id, 1)
    void refreshCartBadge()
    try {
      await showDialog({
        message: t('pdp.added'),
        showCancelButton: true,
        cancelButtonText: t('pdp.continueShop'),
        confirmButtonText: t('pdp.goCart'),
        confirmButtonColor: 'var(--sm-primary)',
      })
      // 勿先 closeOverlay()（router.back）：與 push 競態會停在上一 Tab（常為首頁）
      await router.push({ name: 'cart' })
    } catch {
      /* 繼續購物：關閉對話框即可 */
    }
  } catch (error) {
    showToast(error instanceof ApiError ? error.message : t('pdp.outOfStock'))
  }
}

function buyNow() {
  if (!isLoggedIn()) {
    router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  if (seckill.value.active && seckill.value.seckill_item_id) {
    router.push({
      name: 'checkout',
      query: { seckill_item_id: String(seckill.value.seckill_item_id), qty: '1' },
    })
    return
  }
  if (!requireSku() || !quote.value) return
  router.push({ name: 'checkout', query: { sku_id: String(quote.value.id), qty: '1' } })
}

function toggleFav() {
  if (!product.value) return
  requestFavorite(product.value.id, route.fullPath, (to) => router.push(to))
}

async function share() {
  if (!product.value) return
  const promo = currentMember()?.promo_code
  const path =
    isLoggedIn() && promo
      ? `/product/${product.value.id}?ref=${encodeURIComponent(promo)}`
      : `/product/${product.value.id}`
  const shareUrl = buildShareUrl(path)
  if (navigator.share) {
    try {
      await navigator.share({ title: name.value, url: shareUrl })
      return
    } catch (error) {
      if (error instanceof DOMException && error.name === 'AbortError') return
    }
  }
  try {
    await navigator.clipboard.writeText(shareUrl)
  } catch {
    /* ignore */
  }
  showToast(t('pdp.copied'))
}
</script>

<template>
  <!-- 勿用全局 .page：其 padding-bottom 為 Tabbar 預留，疊層商詳會在購買欄下留白 -->
  <div v-if="product" class="pdp">
    <AppHeader show-back @back="closeOverlay" />
    <!-- 滾動區與購買欄分離：疊層 overflow 會讓內部 position:fixed 跟著滑走 -->
    <div class="pdp-scroll">
    <van-swipe
      :key="product.id"
      ref="gallerySwipe"
      class="gallery"
      :initial-swipe="0"
      :show-indicators="false"
      :loop="gallery.length > 1"
      @change="onGalleryChange"
    >
      <van-swipe-item v-for="(src, index) in gallery" :key="`${product.id}-${index}`">
        <button type="button" class="shot" @click="openZoom(index)">
          <img :src="src" :alt="name" />
        </button>
      </van-swipe-item>
    </van-swipe>
    <div class="dots">
      <i v-for="(_, index) in gallery" :key="index" :class="{ on: index === slide }" />
    </div>

    <section class="info">
      <h1>{{ name }}</h1>
      <p v-if="seckill.active" class="seckill-tag">{{ t('m7.live') }}</p>
      <div class="price-line">
        <strong>{{ money(price) }}</strong>
        <span class="sold-line">
          <em class="rating">⭐{{ ratingText }}</em>
          <span>{{ t('home.sold', { n: product.sold }) }}</span>
        </span>
      </div>
      <p class="sub">
        <s>{{ money(compareAt) }}</s>
        <span>{{ t('pdp.stock', { n: stock }) }}</span>
      </p>
      <div v-if="(product.attrs || []).length" class="attrs">
        <div v-for="attr in product.attrs" :key="attr.id" class="attr-row">
          <span class="attr-name">{{ text(attr.name) }}</span>
          <span class="attr-vals">{{ attr.values.map((v) => text(v.name)).join(' · ') }}</span>
        </div>
      </div>
      <div v-if="showTagRow" class="tags">
        <span
          v-for="badge in badgeItems"
          :key="`b-${badge.code}`"
          class="tag-badge"
          :style="{ background: badge.color }"
        >{{ badgeLabel(badge) }}</span>
        <span v-for="tag in product.tags" :key="tag['zh-HK']" class="tag-service">{{ text(tag) }}</span>
      </div>

      <div v-for="group in product.spec_groups" :key="group.id" class="spec">
        <div class="lab">{{ text(group.name) }}</div>
        <div class="sku">
          <button
            v-for="option in group.options"
            :key="option.id"
            type="button"
            :class="{ on: selected[group.id] === option.id }"
            @click="choose(group.id, option.id)"
          >
            {{ text(option.name) }}
          </button>
        </div>
      </div>

      <h2>{{ t('pdp.detail') }}</h2>
      <div v-if="detailIsHtml" class="copy rich" v-html="detailHtml" />
      <p v-else class="copy">{{ text(product.detail) }}</p>

      <h2>{{ t('pdp.reviews') }}</h2>
      <p v-if="!(product.reviews || []).length" class="copy muted">{{ t('pdp.reviewsEmpty') }}</p>
      <ul v-else class="reviews">
        <li v-for="r in showAllReviews ? product.reviews : product.reviews.slice(0, 2)" :key="r.id">
          <div class="rev-head">
            <strong>{{ r.author }}</strong>
            <span class="rev-stars">{{ '★'.repeat(r.stars) }}{{ '☆'.repeat(Math.max(0, 5 - r.stars)) }}</span>
            <span
              v-if="r.is_mine && r.moderation_status && r.moderation_status !== 'approved'"
              class="rev-mod"
            >
              {{ r.moderation_status === 'rejected' ? t('pdp.reviewRejected') : t('pdp.reviewPending') }}
            </span>
          </div>
          <p class="copy">{{ typeof r.body === 'string' ? r.body : text(r.body) }}</p>
          <div v-if="r.images?.length" class="rev-imgs">
            <img v-for="(img, i) in r.images" :key="i" :src="assetUrl(img)" alt="" />
          </div>
          <p v-if="r.follow" class="follow">
            <em>{{ t('pdp.follow') }}</em>
            {{ typeof r.follow === 'string' ? r.follow : text(r.follow) }}
          </p>
          <p v-if="r.reply" class="reply">
            <em>{{ t('pdp.reply') }}</em>
            {{ r.reply }}
          </p>
        </li>
      </ul>
      <button
        v-if="(product.reviews || []).length > 2"
        type="button"
        class="more-rev"
        @click="showAllReviews = !showAllReviews"
      >
        {{ showAllReviews ? t('pdp.reviewsLess') : t('pdp.reviewsAll') }}
      </button>

      <h2 v-if="related.length">{{ t('pdp.related') }}</h2>
    </section>
    <div v-if="related.length" class="grid">
      <ProductCard v-for="item in related" :key="item.id" :product="item" />
    </div>
    </div>

    <div class="buybar">
      <div class="buybar-icons">
        <button type="button" class="ico" :aria-label="t('pdp.fav')" @click="toggleFav">
          <van-icon :name="liked ? 'like' : 'like-o'" size="1.25rem" />
        </button>
        <button type="button" class="ico" :aria-label="t('pdp.share')" @click="share">
          <van-icon name="guide-o" size="1.25rem" />
        </button>
      </div>
      <div class="buybar-actions" :class="{ seckill: seckill.active }">
        <button
          v-if="!seckill.active"
          type="button"
          class="ghost"
          :disabled="stock <= 0"
          @click="addToCart"
        >
          {{ t('pages.addToCart') }}
        </button>
        <button
          type="button"
          class="buy"
          :class="{ seckill: seckill.active }"
          :disabled="stock <= 0"
          @click="buyNow"
        >
          {{ t('pages.buyNow') }}
        </button>
      </div>
    </div>

    <ProductZoom
      v-if="zooming"
      :images="zoomImages"
      :start="slide"
      @close="closeZoom"
      @change="onZoomChange"
    />
  </div>
</template>

<style scoped>
.pdp {
  /* 疊層內自管滾動：購買欄用 flex 釘底，避免 fixed 被 overflow 祖先帶著滑 */
  display: flex;
  flex-direction: column;
  height: 100%;
  /* 無全局 .page：勿用 100vh（易高出 fixed 疊層）；底部不預留 Tabbar */
  min-height: 0;
  max-height: 100%;
  padding-bottom: 0 !important;
  background: #fff;
  box-sizing: border-box;
}
.pdp-scroll {
  flex: 1;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior: contain;
  padding-top: var(--sm-header-h);
  padding-bottom: 0.75rem;
}
.gallery {
  height: 17.5rem;
  background: #f6f6f6;
}
.shot {
  width: 100%;
  height: 17.5rem;
  border: 0;
  padding: 0;
  background: #f6f6f6;
}
.shot img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}
.dots {
  display: flex;
  justify-content: center;
  gap: 0.375rem;
  padding: 0.5rem 0 0.25rem;
}
.dots i {
  width: 0.375rem;
  height: 0.375rem;
  border-radius: 50%;
  background: #ddd;
}
.dots i.on {
  background: var(--sm-primary);
}
.info {
  padding: 0.25rem 0.75rem 0.5rem;
}
h1 {
  margin: 0.5rem 0 0.375rem;
  font-size: 1rem;
  line-height: 1.4;
}
.seckill-tag {
  margin: 0 0 0.35rem;
  color: #c45c26;
  font-size: 0.8125rem;
  font-weight: 600;
}
.price-line {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}
.price-line strong {
  color: var(--sm-text);
  font-size: 1.25rem;
}
.price-line .sold-line,
.sub {
  color: var(--sm-text-muted);
  font-size: 0.75rem;
}
.price-line .sold-line {
  display: inline-flex;
  align-items: baseline;
  gap: 0.4rem;
  font-style: normal;
}
.price-line .rating {
  color: #e6a23c;
  font-weight: 600;
  font-style: normal;
  font-variant-numeric: tabular-nums;
}
.sub {
  display: flex;
  gap: 0.75rem;
  margin: 0.25rem 0 0.75rem;
}
.attrs {
  margin-bottom: 0.5rem;
}
.attr-row {
  display: flex;
  gap: 0.5rem;
  font-size: 0.75rem;
  line-height: 1.5;
  color: var(--sm-text-secondary);
}
.attr-name {
  flex: 0 0 auto;
  color: var(--sm-text-muted);
}
.attr-vals {
  flex: 1;
  min-width: 0;
}
.tags {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.375rem;
  margin-bottom: 0.75rem;
}
.tag-badge {
  border: 0;
  border-radius: 0.5rem;
  padding: 0.125rem 0.375rem;
  font-size: 0.6875rem;
  line-height: 1.4;
  color: #fff;
  background: var(--sm-primary);
}
.tag-service {
  border: 0.0625rem solid var(--sm-border);
  border-radius: 0.25rem;
  padding: 0.125rem 0.375rem;
  font-size: 0.6875rem;
  color: var(--sm-text-secondary);
}
.spec {
  margin-bottom: 0.75rem;
}
.lab {
  font-size: 0.8125rem;
  margin-bottom: 0.375rem;
}
.sku {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.sku button {
  border: 0.0625rem solid var(--sm-border);
  background: #fff;
  border-radius: 0.375rem;
  padding: 0.375rem 0.625rem;
  font-size: 0.75rem;
}
.sku button.on {
  border-color: #16162b;
  color: #16162b;
}
h2 {
  margin: 1rem 0 0.5rem;
  font-size: 0.9375rem;
}
.copy {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.6;
  color: #444;
}
.copy.muted {
  color: #999;
}
.copy.rich :deep(img) {
  display: block;
  width: 100%;
  height: auto;
  margin: 0.5rem 0;
  border-radius: 0.5rem;
}
.copy.rich :deep(p) {
  margin: 0 0 0.5rem;
}
.copy.rich :deep(a) {
  color: var(--sm-primary);
}
.reviews {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.875rem;
}
.rev-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.35rem 0.5rem;
  margin-bottom: 0.25rem;
  font-size: 0.8125rem;
}
.rev-head strong {
  margin-right: auto;
}
.rev-stars {
  color: #e6a23c;
  letter-spacing: 0.05em;
}
.rev-mod {
  font-size: 0.6875rem;
  color: #a67c52;
  background: #f5efe6;
  padding: 0.1rem 0.35rem;
  border-radius: 0.2rem;
}
.rev-imgs {
  display: flex;
  gap: 0.375rem;
  margin-top: 0.5rem;
  flex-wrap: wrap;
}
.rev-imgs img {
  width: 4rem;
  height: 4rem;
  object-fit: cover;
  border-radius: 0.375rem;
  background: #f6f6f6;
}
.follow,
.reply {
  margin: 0.35rem 0 0;
  font-size: 0.75rem;
  color: #666;
}
.follow em,
.reply em {
  font-style: normal;
  color: var(--sm-primary);
  margin-right: 0.25rem;
}
.reply {
  background: #f5f2ec;
  padding: 0.5rem 0.65rem;
  border-radius: 0.35rem;
}
.more-rev {
  margin-top: 0.5rem;
  border: 0;
  background: transparent;
  color: var(--sm-primary);
  font-size: 0.8125rem;
  padding: 0;
}
.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  padding: 0 0.75rem 1rem;
}
.buybar {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  /* 與首頁 tabbar 同垂直內邊距（--sm-tabbar-pad-y）；safe-area 疊加在底邊 */
  padding-top: var(--sm-tabbar-pad-y, 0.5rem);
  padding-left: 0.75rem;
  padding-right: 0.75rem;
  padding-bottom: calc(var(--sm-tabbar-pad-y, 0.5rem) + env(safe-area-inset-bottom, 0px));
  background: #fff;
  border-top: 0.0625rem solid var(--sm-border);
  z-index: 210;
  box-sizing: content-box;
}
.buybar-icons {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  flex-shrink: 0;
}
.buybar-actions {
  margin-left: auto;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
  flex: 1;
  min-width: 0;
}
.buybar-actions.seckill {
  flex: 0 0 auto;
}
.ico {
  border: 0;
  background: transparent;
  color: var(--sm-text);
  width: 2.25rem;
  height: 2.25rem;
  display: grid;
  place-items: center;
  padding: 0;
}
.ghost,
.buy {
  border: 0;
  border-radius: 0.375rem;
  height: 2.25rem;
  font-size: 0.8125rem;
  min-width: 6.5rem;
  padding: 0 0.875rem;
}
.buybar-actions:not(.seckill) .ghost,
.buybar-actions:not(.seckill) .buy {
  flex: 1;
  max-width: 9rem;
}
.ghost {
  background: #efeae3;
  color: var(--sm-text);
}
.buy {
  background: var(--sm-primary);
  color: #fff;
}
.buy.seckill {
  background: #f75656;
  min-width: 8.5rem;
}
.ghost:disabled,
.buy:disabled {
  opacity: 0.45;
}
</style>
