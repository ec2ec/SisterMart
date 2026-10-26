<script setup lang="ts">
import { goLogin } from '../composables/useAuth'
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
  const byItem = Number(route.query.seckill_item_id || 0)
  product.value =
    (byItem
      ? session.value.products.find((p) => p.seckill_item_id === byItem)
      : undefined) ||
    session.value.products.find((p) => p.product_id === code) ||
    session.value.products[0] ||
    null
})

const live = computed(() => session.value?.status === 'live')

function buy() {
  if (!live.value) {
    showToast(t('m7.notStarted'))
    return
  }
  if (!product.value || product.value.stock <= 0) {
    showToast(t('pdp.outOfStock'))
    return
  }
  if (!isLoggedIn()) {
    void goLogin(router, route.fullPath)
    return
  }
  router.push({
    name: 'checkout',
    query: {
      seckill_item_id: String(product.value.seckill_item_id),
      qty: '1',
    },
  })
}
</script>

<template>
  <div class="page" v-if="session && product">
    <SubPageNav :title="text(session.name)" />
    <img class="hero" :src="product.image" alt="" />
    <section class="info">
      <p class="badge">{{ live ? t('m7.live') : t('m7.upcoming') }} · {{ session.ends_at }}</p>
      <h1>{{ text(product.name) }}</h1>
      <p class="price">HK$ {{ product.seckill_price }} <s>HK$ {{ product.origin_price }}</s></p>
      <p class="stock">{{ t('pdp.stock', { n: product.stock }) }}</p>
      <p class="hint">{{ t('m7.seckillOnlyBuy') }}</p>
    </section>
    <div class="bar">
      <van-button
        class="buy-btn"
        round
        color="#f75656"
        :disabled="!live || product.stock <= 0"
        @click="buy"
      >
        {{ t('pages.buyNow') }}
      </van-button>
    </div>
  </div>
</template>

<style scoped>
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
  display: flex;
  justify-content: flex-end;
  align-items: center;
  /* 與首頁 tabbar / 商詳 buybar 同垂直內邊距 */
  padding-top: var(--sm-tabbar-pad-y, 0.5rem);
  padding-left: 0.75rem;
  padding-right: 0.75rem;
  padding-bottom: calc(var(--sm-tabbar-pad-y, 0.5rem) + env(safe-area-inset-bottom, 0px));
  background: #fff;
  border-top: 1px solid #f0f0f0;
  box-sizing: content-box;
}
.buy-btn {
  min-width: 8.5rem;
}
</style>
