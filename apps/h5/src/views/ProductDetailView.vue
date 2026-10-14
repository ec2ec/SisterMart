<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { findProduct } from '../mock/data'
import { useLocaleText } from '../composables/useLocaleText'

const { t } = useI18n()
const { text } = useLocaleText()
const route = useRoute()
const router = useRouter()

const product = computed(() => findProduct(String(route.params.id || '')))
const name = computed(() => (product.value ? text(product.value.name) : ''))
</script>

<template>
  <div class="page" v-if="product">
    <SubPageNav :title="t('pages.productDetail')" />
    <img class="cover" :src="product.image" :alt="name" />
    <div class="body">
      <h1>{{ name }}</h1>
      <p class="price">HK$ {{ product.price }}</p>
      <p class="meta">{{ t('home.sold', { n: product.sold }) }} · {{ t('pages.stock') }} 99</p>
      <h2>{{ t('pages.desc') }}</h2>
      <p class="desc">{{ t('pages.descBody') }}</p>
    </div>
    <div class="bar">
      <van-button round block plain color="var(--sm-primary)" @click="showToast(t('pages.addToCart'))">
        {{ t('pages.addToCart') }}
      </van-button>
      <van-button round block color="var(--sm-primary)" @click="router.push({ name: 'cart' })">
        {{ t('pages.buyNow') }}
      </van-button>
    </div>
  </div>
</template>

<style scoped>
.cover {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  display: block;
  background: #f5f5f5;
}

.body {
  padding: 16px 16px 96px;
}

.body h1 {
  margin: 0;
  font-size: 18px;
}

.price {
  margin: 8px 0 4px;
  color: var(--sm-primary);
  font-size: 22px;
  font-weight: 700;
}

.meta {
  margin: 0 0 16px;
  color: #999;
  font-size: 12px;
}

.body h2 {
  margin: 0 0 8px;
  font-size: 15px;
}

.desc {
  margin: 0;
  color: #666;
  font-size: 13px;
  line-height: 1.6;
}

.bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  padding: 10px 12px calc(10px + env(safe-area-inset-bottom, 0px));
  background: #fff;
  border-top: 1px solid #eee;
}
</style>
