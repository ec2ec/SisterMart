<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import AppHeader from '../components/AppHeader.vue'
import ProductCard from '../components/ProductCard.vue'
import ProductZoom from '../components/ProductZoom.vue'
import { fetchProduct, fetchProducts, skuKey, type ProductCard as Card, type ProductDetail } from '../api/catalog'
import { addCartItem } from '../api/trade'
import { useLocaleText } from '../composables/useLocaleText'
import { isFavorite, requestFavorite } from '../composables/useFavorites'
import { currentMember, isLoggedIn } from '../composables/useSession'
import { ApiError } from '../api/client'

const { t } = useI18n()
const { text } = useLocaleText()
const route = useRoute()
const router = useRouter()

const product = ref<ProductDetail | null>(null)
const related = ref<Card[]>([])
const slide = ref(0)
const zooming = ref(false)
const selected = ref<Record<string, string>>({})

async function load() {
  const id = String(route.params.id || '')
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
  const list = await fetchProducts({ category: product.value.category })
  related.value = list.items.filter((item) => item.id !== product.value?.id).slice(0, 4)
}

watch(() => route.params.id, load, { immediate: true })

const name = computed(() => (product.value ? text(product.value.name) : ''))
const gallery = computed(() => {
  const images = (product.value?.media || [])
    .filter((m) => m.media_type === 'image')
    .map((m) => m.url)
  return images.length ? images : product.value?.gallery || []
})
const zoomImages = computed(() => gallery.value)

const quote = computed(() => {
  if (!product.value) return null
  const key = skuKey(selected.value, product.value.spec_groups)
  return product.value.skus.find((sku) => sku.key === key || sku.code === key) || null
})

const price = computed(() => quote.value?.price ?? product.value?.price ?? 0)
const compareAt = computed(() => quote.value?.compare_at ?? 0)
const stock = computed(() => quote.value?.stock ?? 0)
const liked = computed(() => (product.value ? isFavorite(product.value.id) : false))

const selectedImage = computed(() => {
  if (!product.value) return ''
  for (const group of product.value.spec_groups) {
    const option = group.options.find((item) => item.id === selected.value[group.id])
    if (option?.image) return option.image
  }
  return quote.value?.image || ''
})

watch(selectedImage, (src) => {
  if (!src) return
  const index = gallery.value.indexOf(src)
  if (index >= 0) slide.value = index
})

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
  if (!requireSku() || !quote.value) return
  if (!isLoggedIn()) {
    router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  try {
    await addCartItem(quote.value.id, 1)
    showToast(t('pdp.added'))
  } catch (error) {
    showToast(error instanceof ApiError ? error.message : t('pdp.outOfStock'))
  }
}

function buyNow() {
  if (!requireSku() || !quote.value) return
  if (!isLoggedIn()) {
    router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  router.push({ name: 'checkout', query: { sku_id: String(quote.value.id), qty: '1' } })
}

function toggleFav() {
  if (!product.value) return
  requestFavorite(product.value.id, route.fullPath, (to) => router.push(to))
}

async function share() {
  if (!product.value) return
  const url = new URL(`/product/${product.value.id}`, location.origin)
  const promo = currentMember()?.promo_code
  if (isLoggedIn() && promo) url.searchParams.set('ref', promo)
  const shareUrl = url.toString()
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
  <div v-if="product" class="page pdp">
    <AppHeader show-logo />
    <van-swipe v-model:active="slide" class="gallery" :show-indicators="false" :loop="gallery.length > 1">
      <van-swipe-item v-for="(src, index) in gallery" :key="`${product.id}-${index}`">
        <button type="button" class="shot" @click="zooming = true">
          <img :src="src" :alt="name" />
        </button>
      </van-swipe-item>
    </van-swipe>
    <div class="dots">
      <i v-for="(_, index) in gallery" :key="index" :class="{ on: index === slide }" />
    </div>

    <section class="info">
      <h1>{{ name }}</h1>
      <div class="price-line">
        <strong>{{ money(price) }}</strong>
        <span>{{ t('home.sold', { n: product.sold }) }}</span>
      </div>
      <p class="sub">
        <s>{{ money(compareAt) }}</s>
        <span>{{ t('pdp.stock', { n: stock }) }}</span>
      </p>
      <div class="tags">
        <span v-for="tag in product.tags" :key="tag['zh-HK']">{{ text(tag) }}</span>
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
      <p class="copy">{{ text(product.detail) }}</p>
      <img v-for="src in gallery.slice(1)" :key="src" class="detail-img" :src="src" alt="" />

      <h2 v-if="related.length">{{ t('pdp.related') }}</h2>
    </section>
    <div v-if="related.length" class="grid">
      <ProductCard v-for="item in related" :key="item.id" :product="item" />
    </div>

    <div class="buybar">
      <button type="button" class="ico" :aria-label="t('pdp.fav')" @click="toggleFav">
        <van-icon :name="liked ? 'like' : 'like-o'" size="1.25rem" />
      </button>
      <button type="button" class="ico" :aria-label="t('pdp.share')" @click="share">
        <van-icon name="guide-o" size="1.25rem" />
      </button>
      <button type="button" class="ghost" :disabled="stock <= 0" @click="addToCart">{{ t('pages.addToCart') }}</button>
      <button type="button" class="buy" :disabled="stock <= 0" @click="buyNow">{{ t('pages.buyNow') }}</button>
    </div>

    <ProductZoom v-if="zooming" :images="zoomImages" :start="slide" @close="zooming = false" />
  </div>
</template>

<style scoped>
.pdp {
  padding-top: var(--sm-header-h);
  padding-bottom: calc(var(--sm-tabbar-h) + 4.25rem + env(safe-area-inset-bottom, 0rem));
  background: #fff;
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
.shot img,
.detail-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}
.detail-img {
  height: auto;
  margin-top: 0.75rem;
  border-radius: 0.5rem;
  background: #f6f6f6;
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
.price-line {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}
.price-line strong {
  color: var(--sm-text);
  font-size: 1.25rem;
}
.price-line span,
.sub {
  color: var(--sm-text-muted);
  font-size: 0.75rem;
}
.sub {
  display: flex;
  gap: 0.75rem;
  margin: 0.25rem 0 0.75rem;
}
.tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
  margin-bottom: 0.75rem;
}
.tags span {
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
.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  padding: 0 0.75rem 1rem;
}
.buybar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: calc(var(--sm-tabbar-h) + env(safe-area-inset-bottom, 0rem));
  display: grid;
  grid-template-columns: auto auto 1fr 1fr;
  gap: 0.5rem;
  align-items: center;
  padding: 0.5rem 0.75rem;
  background: #fff;
  border-top: 0.0625rem solid var(--sm-border);
  z-index: 20;
}
.ico {
  border: 0;
  background: transparent;
  color: var(--sm-text);
}
.ghost,
.buy {
  border: 0;
  border-radius: 0.375rem;
  height: 2.25rem;
  font-size: 0.8125rem;
}
.ghost {
  background: #efeae3;
  color: var(--sm-text);
}
.buy {
  background: var(--sm-primary);
  color: #fff;
}
.ghost:disabled,
.buy:disabled {
  opacity: 0.45;
}
</style>
