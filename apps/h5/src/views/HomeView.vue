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
      <div class="grid-2">
        <ProductCard v-for="p in flashProducts" :key="p.id" :product="p" />
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
  height: 260px;
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
  left: 16px;
  bottom: 28px;
  z-index: 1;
  color: #fff;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.45);
  max-width: 80%;
}

.hero-copy .eyebrow {
  margin: 0;
  font-size: 11px;
  letter-spacing: 0.06em;
}

.hero-copy h2 {
  margin: 4px 0;
  font-size: 24px;
  font-weight: 800;
}

.hero-copy .sub {
  margin: 0;
  font-size: 12px;
}

.promo {
  padding: 8px 12px;
  text-align: center;
  font-size: 12px;
  font-weight: 600;
  color: var(--sm-primary);
  background: var(--sm-promo-bg);
}

.countdown {
  padding: 2px 8px;
  border-radius: 6px;
  background: #f3f3f3;
  color: var(--sm-primary);
  font-weight: 700;
  font-size: 13px;
}

.grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  padding: 0 12px 8px;
}

.padded {
  padding-top: 4px;
}

.cats {
  padding: 8px 0 4px;
}

.cat-scroll {
  display: flex;
  gap: 14px;
  overflow-x: auto;
  padding: 4px 14px 8px;
  scrollbar-width: none;
}

.cat-scroll::-webkit-scrollbar {
  display: none;
}

.cat {
  flex: 0 0 auto;
  width: 64px;
  border: 0;
  background: transparent;
  padding: 0;
  text-align: center;
}

.cat img {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  object-fit: cover;
  display: block;
  margin: 0 auto 6px;
  background: #f2f2f2;
}

.cat span {
  font-size: 11px;
  color: #333;
}

.more {
  border: 0;
  background: transparent;
  font-size: 12px;
  color: var(--sm-text-muted);
  padding: 0;
}

.feed :deep(.van-tabs__wrap) {
  padding: 0 8px;
}

.newsletter {
  margin: 12px;
  padding: 18px 16px;
  border: 1px solid #eee;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
}

.newsletter h3 {
  margin: 0;
  font-size: 15px;
}

.newsletter p {
  margin: 6px 0 12px;
  font-size: 12px;
  color: var(--sm-text-muted);
}

.email-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 4px 4px 12px;
  border-radius: 999px;
  background: #f3f4f6;
}

.email-box input {
  flex: 1;
  border: 0;
  outline: 0;
  background: transparent;
  font-size: 13px;
  min-width: 0;
}

.email-box button {
  border: 0;
  background: transparent;
  color: var(--sm-primary);
  font-weight: 700;
  font-size: 13px;
  padding: 8px 12px;
}
</style>
