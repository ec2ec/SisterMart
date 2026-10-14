<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import AppHeader from '../components/AppHeader.vue'
import ProductCard from '../components/ProductCard.vue'
import ProductZoom from '../components/ProductZoom.vue'
import { useLocaleText } from '../composables/useLocaleText'
import { isLoggedIn, isFavorite, requestFavorite } from '../composables/useFavorites'
import { allProducts, findProduct, skuKey, specsFor } from '../mock/data'

const { t } = useI18n()
const { text } = useLocaleText()
const route = useRoute()
const router = useRouter()

const product = computed(() => findProduct(String(route.params.id || '')))
const specs = computed(() => (product.value ? specsFor(product.value) : null))
const name = computed(() => (product.value ? text(product.value.name) : ''))
const gallery = computed(() => product.value?.gallery ?? (product.value ? [product.value.image] : []))
const slide = ref(0)
const zooming = ref(false)
const showAllReviews = ref(false)
const selected = ref<Record<string, string>>({})

watch(
  () => product.value?.id,
  () => {
    const next: Record<string, string> = {}
    for (const group of specs.value?.groups || []) next[group.id] = group.options[0]?.id || ''
    selected.value = next
    slide.value = 0
    zooming.value = false
    showAllReviews.value = false
  },
  { immediate: true },
)

const quote = computed(() => {
  if (!specs.value) return null
  const key = skuKey(selected.value, specs.value.groups)
  return specs.value.skus.find((sku) => sku.key === key) || null
})

const price = computed(() => quote.value?.price ?? product.value?.price ?? 0)
const compareAt = computed(() => quote.value?.compareAt ?? specs.value?.compareAt ?? 0)
const stock = computed(() => quote.value?.stock ?? specs.value?.stock ?? 0)

const selectedImage = computed(() => {
  for (const group of specs.value?.groups || []) {
    const option = group.options.find((item) => item.id === selected.value[group.id])
    if (option?.image) return option.image
  }
  return ''
})

watch(selectedImage, (src) => {
  if (!src) return
  const index = gallery.value.indexOf(src)
  if (index >= 0) slide.value = index
})

const reviews = computed(() => {
  const list = specs.value?.reviews || []
  return showAllReviews.value ? list : list.slice(0, 1)
})

const related = computed(() =>
  allProducts.filter((item) => item.category === product.value?.category && item.id !== product.value?.id),
)

const liked = computed(() => (product.value ? isFavorite(product.value.id) : false))

function choose(groupId: string, optionId: string) {
  selected.value = { ...selected.value, [groupId]: optionId }
}

function money(value: number) {
  return `HK$ ${value.toFixed(2)}`
}

function requireSku() {
  if (quote.value) return true
  showToast(t('pdp.pickSpec'))
  return false
}

function addToCart() {
  if (!requireSku()) return
  showToast(t('pdp.added'))
}

function buyNow() {
  if (!requireSku()) return
  showToast(t('pdp.buyReady'))
}

function toggleFav() {
  if (!product.value) return
  requestFavorite(product.value.id, route.fullPath, (to) => router.push(to))
}

async function share() {
  if (!product.value) return
  const url = new URL(`/product/${product.value.id}`, location.origin)
  if (isLoggedIn()) url.searchParams.set('ref', 'SM1001')
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
    /* mock still confirms the link was prepared */
  }
  showToast(t('pdp.copied'))
}
</script>

<template>
  <div v-if="product && specs" class="page pdp">
    <AppHeader show-logo />
    <van-swipe
      v-model:active="slide"
      class="gallery"
      :show-indicators="false"
      :loop="gallery.length > 1"
    >
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
        <span v-for="tag in specs.tags" :key="tag['zh-HK']">{{ text(tag) }}</span>
      </div>

      <div v-for="group in specs.groups" :key="group.id" class="spec">
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
      <p class="copy">{{ text(specs.detail) }}</p>
      <img v-for="src in gallery.slice(1)" :key="src" class="detail-img" :src="src" alt="" />

      <div class="sec">
        <h2>{{ t('pdp.reviews') }}</h2>
        <button v-if="specs.reviews.length > 1" type="button" class="more" @click="showAllReviews = !showAllReviews">
          {{ showAllReviews ? t('pdp.reviewsLess') : t('pdp.reviewsAll') }}
        </button>
      </div>
      <article v-for="review in reviews" :key="review.id" class="review">
        <div class="stars">{{ '★★★★★'.slice(0, review.stars) }}</div>
        <p>{{ text(review.body) }}</p>
        <div v-if="review.images.length" class="shots">
          <img v-for="src in review.images" :key="src" :src="src" alt="" />
        </div>
        <p v-if="review.follow" class="follow">{{ t('pdp.follow') }}{{ text(review.follow) }}</p>
      </article>

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
      <button type="button" class="ghost" @click="addToCart">{{ t('pages.addToCart') }}</button>
      <button type="button" class="buy" @click="buyNow">{{ t('pages.buyNow') }}</button>
    </div>

    <ProductZoom v-if="zooming" :images="gallery" :start="slide" @close="zooming = false" />
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
  gap: 0.5rem;
}

.price-line strong {
  color: var(--sm-primary);
  font-size: 1.25rem;
}

.price-line span,
.sub,
.copy,
.follow {
  color: #8a8378;
  font-size: 0.75rem;
}

.sub {
  margin: 0.25rem 0 0;
  display: flex;
  gap: 0.75rem;
}

.tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
  margin-top: 0.75rem;
}

.tags span {
  background: #efeae3;
  border-radius: 0.25rem;
  padding: 0.125rem 0.375rem;
  font-size: 0.6875rem;
}

.lab,
h2 {
  margin: 0.875rem 0 0.5rem;
  font-size: 0.875rem;
}

.sku {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.sku button {
  border: 0.0625rem solid #e3ddd4;
  background: #fff;
  border-radius: 0.875rem;
  padding: 0.25rem 0.625rem;
  font-size: 0.75rem;
}

.sku button.on {
  border-color: #16162b;
  font-weight: 700;
}

.copy,
.review p {
  margin: 0;
  line-height: 1.6;
}

.sec {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
}

.more {
  border: 0;
  background: transparent;
  color: #8a8378;
  font-size: 0.75rem;
  padding: 0;
}

.review {
  padding: 0.5rem 0 0.75rem;
  border-bottom: 0.0625rem solid #f0ebe4;
}

.stars {
  color: #c45c4a;
  letter-spacing: 0.08em;
  font-size: 0.75rem;
}

.shots {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.shots img {
  width: 4rem;
  height: 4rem;
  object-fit: cover;
  border-radius: 0.5rem;
  background: #f3efe8;
}

.follow {
  margin-top: 0.5rem;
}

.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  padding: 0 0.75rem 0.75rem;
}

.buybar {
  position: fixed;
  left: 0;
  right: 0;
  z-index: 90;
  bottom: calc(var(--sm-tabbar-h) + env(safe-area-inset-bottom, 0rem));
  display: flex;
  gap: 0.5rem;
  align-items: center;
  padding: 0.5rem 0.75rem;
  background: #fff;
  border-top: 0.0625rem solid #eee;
}

.ico {
  width: 2.75rem;
  height: 2.75rem;
  flex: none;
  border: 0.0625rem solid #e3ddd4;
  border-radius: 0.5rem;
  background: #fff;
  color: var(--sm-primary);
}

.ghost,
.buy {
  flex: 1;
  height: 2.75rem;
  border: 0;
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 700;
}

.ghost {
  background: #efeae3;
  color: #1c1c1c;
}

.buy {
  background: var(--sm-primary);
  color: #fff;
}
</style>
