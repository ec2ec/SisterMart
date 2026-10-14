<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import AppHeader from '../components/AppHeader.vue'
import ProductCard from '../components/ProductCard.vue'
import HomeFooter from '../components/HomeFooter.vue'
import { useLocaleText } from '../composables/useLocaleText'
import {
  banners,
  collectionProducts,
  feedProducts,
  flashProducts,
  homeCategories,
} from '../mock/data'

const { t } = useI18n()
const { text } = useLocaleText()
const router = useRouter()

const feedTab = ref(0)
const email = ref('')
const flashRemain = ref(2 * 3600e3 + 14 * 60e3 + 8e3)

const flashVisible = computed(() => flashProducts.slice(0, 4))

const feedList = computed(() => {
  if (feedTab.value === 1) return collectionProducts
  if (feedTab.value === 2) return [...feedProducts, ...flashProducts]
  return feedProducts
})

function onSubscribe() {
  showToast(email.value ? t('home.subscribe') : t('home.emailPlaceholder'))
}

function goList(categoryId?: string) {
  router.push({ name: 'product-list', query: categoryId ? { category: categoryId } : {} })
}
</script>

<template>
  <div class="page home">
    <section class="hero-wrap">
      <van-swipe class="hero" :autoplay="4000" indicator-color="var(--sm-primary)">
        <van-swipe-item v-for="b in banners" :key="b.id">
          <div class="hero-item">
            <img :src="b.image" alt="" />
            <div class="hero-copy">
              <p class="eyebrow">{{ t('home.bannerEyebrow') }}</p>
              <h2>{{ t('home.bannerTitle') }}</h2>
              <p class="sub">{{ t('home.bannerSub') }}</p>
            </div>
          </div>
        </van-swipe-item>
      </van-swipe>
      <AppHeader show-logo variant="overlay" />
    </section>

    <div class="promo">{{ t('home.promo') }}</div>

    <section class="flash">
      <div class="section-title">
        <h2>{{ t('home.flashSale') }}</h2>
        <van-count-down :time="flashRemain" class="countdown" />
      </div>
      <div class="flash-scroll">
        <div v-for="p in flashVisible" :key="p.id" class="flash-item">
          <ProductCard :product="p" />
        </div>
      </div>
    </section>

    <section class="cats">
      <div class="cat-scroll">
        <button
          v-for="c in homeCategories"
          :key="c.id"
          type="button"
          class="cat"
          @click="goList(c.id)"
        >
          <img :src="c.image" :alt="text(c.name)" />
          <span>{{ text(c.name) }}</span>
        </button>
      </div>
    </section>

    <section>
      <div class="section-title">
        <h2>{{ t('home.necklaceCollection') }}</h2>
        <button type="button" class="more" @click="goList('necklaces')">{{ t('home.viewAll') }}</button>
      </div>
      <div class="grid-2 padded">
        <ProductCard v-for="p in collectionProducts" :key="p.id" :product="p" />
      </div>
    </section>

    <section class="feed">
      <van-tabs v-model:active="feedTab" shrink color="var(--sm-primary)" title-active-color="var(--sm-primary)">
        <van-tab :title="t('home.hot')" />
        <van-tab :title="t('home.new')" />
        <van-tab :title="t('home.forYou')" />
      </van-tabs>
      <div class="grid-2 padded">
        <ProductCard v-for="p in feedList" :key="`${feedTab}-${p.id}`" :product="p" />
      </div>
    </section>

    <section class="newsletter">
      <h3>{{ t('home.newsletterTitle') }}</h3>
      <p>{{ t('home.newsletterSub') }}</p>
      <div class="email-box">
        <input v-model="email" type="email" :placeholder="t('home.emailPlaceholder')" />
        <button type="button" @click="onSubscribe">{{ t('home.subscribe') }}</button>
      </div>
    </section>

    <HomeFooter />
  </div>
</template>

<style scoped>
.home {
  background: #fff;
}

.hero-wrap {
  position: relative;
  width: 100%;
}

.hero {
  width: 100%;
}

.hero-item {
  position: relative;
  height: 16.25rem;
  background: #111;
}

.hero-item::after {
  content: '';
  position: absolute;
  inset: 0;
  background:
    linear-gradient(180deg, rgba(0, 0, 0, 0.25) 0%, rgba(0, 0, 0, 0.05) 40%, rgba(0, 0, 0, 0.55) 100%),
    linear-gradient(90deg, rgba(0, 0, 0, 0.45) 0%, rgba(0, 0, 0, 0.05) 55%);
  pointer-events: none;
}

.hero-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.hero-copy {
  position: absolute;
  left: 1rem;
  bottom: 1.75rem;
  z-index: 1;
  color: #fff;
  text-shadow: 0 0.0625rem 0.25rem rgba(0, 0, 0, 0.45);
  max-width: 80%;
}

.hero-copy .eyebrow {
  margin: 0;
  font-size: 0.6875rem;
  letter-spacing: 0.06em;
}

.hero-copy h2 {
  margin: 0.25rem 0;
  font-size: 1.5rem;
  font-weight: 800;
}

.hero-copy .sub {
  margin: 0;
  font-size: 0.75rem;
}

.promo {
  padding: 0.5rem 0.75rem;
  text-align: center;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--sm-primary);
  background: var(--sm-promo-bg);
}

.countdown {
  padding: 0.125rem 0.5rem;
  border-radius: 0.375rem;
  background: #f3f3f3;
  color: var(--sm-primary);
  font-weight: 700;
  font-size: 0.8125rem;
}

.flash-scroll {
  display: flex;
  gap: 0.75rem;
  overflow-x: auto;
  padding: 0 0.75rem 0.5rem;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
}

.flash-scroll::-webkit-scrollbar {
  display: none;
}

.flash-item {
  flex: 0 0 calc((100% - 0.75rem) / 2);
  scroll-snap-align: start;
}

.grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  padding: 0 0.75rem 0.5rem;
}

.padded {
  padding-top: 0.25rem;
}

.cats {
  padding: 0.5rem 0 0.25rem;
}

.cat-scroll {
  display: flex;
  gap: 0.875rem;
  overflow-x: auto;
  padding: 0.25rem 0.875rem 0.5rem;
  scrollbar-width: none;
}

.cat-scroll::-webkit-scrollbar {
  display: none;
}

.cat {
  flex: 0 0 auto;
  width: 4rem;
  border: 0;
  background: transparent;
  padding: 0;
  text-align: center;
}

.cat img {
  width: 3.5rem;
  height: 3.5rem;
  border-radius: 50%;
  object-fit: cover;
  display: block;
  margin: 0 auto 0.375rem;
  background: #f2f2f2;
}

.cat span {
  font-size: 0.6875rem;
  color: #333;
}

.more {
  border: 0;
  background: transparent;
  font-size: 0.75rem;
  color: var(--sm-text-muted);
  padding: 0;
}

.feed :deep(.van-tabs__wrap) {
  padding: 0 0.5rem;
}

.newsletter {
  margin: 0.75rem;
  padding: 1.125rem 1rem;
  border: 0.0625rem solid #eee;
  border-radius: 0.875rem;
  background: #fff;
  box-shadow: 0 0.125rem 0.625rem rgba(0, 0, 0, 0.03);
}

.newsletter h3 {
  margin: 0;
  font-size: 0.9375rem;
}

.newsletter p {
  margin: 0.375rem 0 0.75rem;
  font-size: 0.75rem;
  color: var(--sm-text-muted);
}

.email-box {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0.25rem 0.25rem 0.75rem;
  border-radius: 62.4375rem;
  background: #f3f4f6;
}

.email-box input {
  flex: 1;
  border: 0;
  outline: 0;
  background: transparent;
  font-size: 0.8125rem;
  min-width: 0;
}

.email-box button {
  border: 0;
  background: transparent;
  color: var(--sm-primary);
  font-weight: 700;
  font-size: 0.8125rem;
  padding: 0.5rem 0.75rem;
}
</style>
