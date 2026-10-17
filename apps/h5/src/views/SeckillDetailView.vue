<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchSeckillSession, type SeckillProduct, type SeckillSession } from '../api/m7'
import { useLocaleText } from '../composables/useLocaleText'
import { isLoggedIn } from '../composables/useSession'

const { t } = useI18n()
const { text } = useLocaleText()
const route = useRoute()
const router = useRouter()
const session = ref<SeckillSession | null>(null)
const product = ref<SeckillProduct | null>(null)

onMounted(async () => {
  session.value = await fetchSeckillSession(String(route.params.id))
  const code = String(route.query.product || session.value.products[0]?.product_id || '')
  product.value = session.value.products.find((p) => p.product_id === code) || session.value.products[0] || null
})

const live = computed(() => session.value?.status === 'live')

function buy() {
  if (!live.value) {
    showToast(t('m7.notStarted'))
    return
  }
  if (!isLoggedIn()) {
    router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  showToast(t('m7.seckillBuyMock'))
  if (product.value) {
    router.push({ name: 'product-detail', params: { id: product.value.product_id }, query: { seckill: '1' } })
  }
}
</script>

<template>
  <div class="page" v-if="session && product">
    <SubPageNav :title="text(session.name)" />
    <p class="mock">{{ t('m7.mockBanner') }}</p>
    <img class="hero" :src="product.image" alt="" />
    <section class="info">
      <p class="badge">{{ live ? t('m7.live') : t('m7.upcoming') }} · {{ session.ends_at }}</p>
      <h1>{{ text(product.name) }}</h1>
      <p class="price">HK$ {{ product.seckill_price }} <s>HK$ {{ product.origin_price }}</s></p>
      <p class="stock">{{ t('pdp.stock', { n: product.stock }) }}</p>
      <p class="hint">{{ t('m7.seckillOnlyBuy') }}</p>
    </section>
    <div class="bar">
      <van-button block round color="var(--sm-primary)" @click="buy">{{ t('pages.buyNow') }}</van-button>
    </div>
  </div>
</template>

<style scoped>
.mock {
  margin: 0.5rem 1rem;
  font-size: 0.75rem;
  color: #a67c52;
}
.hero {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  display: block;
}
.info {
  padding: 1rem;
}
.badge {
  color: #c45c26;
  font-size: 0.8125rem;
}
.info h1 {
  margin: 0.35rem 0;
  font-size: 1.125rem;
}
.price {
  color: var(--sm-primary);
  font-size: 1.25rem;
  font-weight: 700;
}
.price s {
  color: #aaa;
  font-size: 0.875rem;
  font-weight: 400;
  margin-left: 0.35rem;
}
.stock,
.hint {
  color: #888;
  font-size: 0.8125rem;
}
.bar {
  position: sticky;
  bottom: 0;
  padding: 0.75rem 1rem calc(0.75rem + env(safe-area-inset-bottom));
  background: #fff;
  border-top: 1px solid #f0f0f0;
}
</style>
