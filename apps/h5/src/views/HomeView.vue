<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import AppHeader from '../components/AppHeader.vue'
import ProductCard from '../components/ProductCard.vue'
import HomeFooter from '../components/HomeFooter.vue'
import { fetchCategories, fetchProducts, type CategoryNode, type ProductCard as Card } from '../api/catalog'
import { banners } from '../mock/data'
import { useLocaleText } from '../composables/useLocaleText'

const { t } = useI18n()
const { text } = useLocaleText()
const router = useRouter()

const feedTab = ref(0)
const email = ref('')
const flashRemain = ref(2 * 3600e3 + 14 * 60e3 + 8e3)
const products = ref<Card[]>([])
const categories = ref<CategoryNode[]>([])

onMounted(async () => {
  const [list, cats] = await Promise.all([fetchProducts(), fetchCategories()])
  products.value = list.items
  categories.value = cats
})

const flashVisible = computed(() => products.value.filter((p) => p.badges.includes('flash')).slice(0, 4))
const collection = computed(() => products.value.filter((p) => p.category.includes('n-') || p.category === 'necklaces').slice(0, 4))
const feedList = computed(() => {
  if (feedTab.value === 1) return collection.value
  if (feedTab.value === 2) return flashVisible.value
  return products.value
})
const homeCategories = computed(() => categories.value.slice(0, 6))

function onSubscribe() {
  showToast(email.value ? t('home.subscribe') : t('home.emailPlaceholder'))
}

function goList(categoryId?: string) {
  router.push({ name: 'product-list', query: categoryId ? { category: categoryId } : {} })
}

const hh = computed(() => String(Math.floor(flashRemain.value / 3600e3)).padStart(2, '0'))
const mm = computed(() => String(Math.floor((flashRemain.value % 3600e3) / 60e3)).padStart(2, '0'))
const ss = computed(() => String(Math.floor((flashRemain.value % 60e3) / 1e3)).padStart(2, '0'))
</script>

<template>
  <div class="page home">
    <section class="hero-wrap">
      <van-swipe class="hero" :autoplay="4000" indicator-color="var(--sm-primary)">
        <van-swipe-item v-for="b in banners" :key="b.id">
          <div class="hero-item">
            <img :src="b.image" alt="" />
          </div>
        </van-swipe-item>
      </van-swipe>
      <AppHeader show-logo variant="overlay" />
    </section>

    <div class="promo">{{ t('home.promo') }}</div>

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
        <img v-if="c.icon" :src="c.icon" :alt="text(c.name)" />
        <span v-else class="ph" />
        <span>{{ text(c.name) }}</span>
      </button>
    </section>

    <section class="feed">
      <div class="tabs">
        <button type="button" :class="{ on: feedTab === 0 }" @click="feedTab = 0">{{ t('home.hot') }}</button>
        <button type="button" :class="{ on: feedTab === 1 }" @click="feedTab = 1">{{ t('home.collection') }}</button>
        <button type="button" :class="{ on: feedTab === 2 }" @click="feedTab = 2">{{ t('home.flashSale') }}</button>
      </div>
      <div class="grid">
        <ProductCard v-for="p in feedList" :key="p.id" :product="p" />
      </div>
    </section>

    <section class="subscribe">
      <h2>{{ t('home.subscribeTitle') }}</h2>
      <div class="row">
        <input v-model="email" type="email" :placeholder="t('home.emailPlaceholder')" />
        <button type="button" @click="onSubscribe">{{ t('home.subscribe') }}</button>
      </div>
    </section>
    <HomeFooter />
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
.hero-item,
.hero-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.promo {
  margin: 0;
  padding: 0.625rem 0.875rem;
  background: var(--sm-promo-bg);
  color: var(--sm-text);
  font-size: 0.75rem;
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
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 0.75rem;
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
