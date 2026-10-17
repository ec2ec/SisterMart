<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchPointsMallItem } from '../api/m7'
import { useLocaleText } from '../composables/useLocaleText'
import { isLoggedIn, memberState } from '../composables/useSession'

const { t } = useI18n()
const { text } = useLocaleText()
const route = useRoute()
const router = useRouter()
const item = ref<Awaited<ReturnType<typeof fetchPointsMallItem>> | null>(null)

onMounted(async () => {
  item.value = await fetchPointsMallItem(String(route.params.id))
})

function exchange() {
  if (!item.value) return
  if (!isLoggedIn()) {
    router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  if (item.value.stock <= 0) {
    showToast(t('pdp.outOfStock'))
    return
  }
  const pts = memberState.value?.points ?? 0
  if (pts < item.value.points_cost) {
    showToast(t('m7.pointsShort'))
    return
  }
  router.push({
    name: 'checkout',
    query: {
      points_mall_item_id: String(item.value.points_mall_item_id),
      qty: '1',
    },
  })
}
</script>

<template>
  <div class="page" v-if="item">
    <SubPageNav :title="text(item.name)" />
    <img class="hero" :src="item.image" alt="" />
    <section class="info">
      <h1>{{ text(item.name) }}</h1>
      <p class="cost">{{ item.points_cost }} {{ t('account.points') }}</p>
      <p class="stock">{{ t('pdp.stock', { n: item.stock }) }}</p>
      <p>{{ text(item.detail) }}</p>
    </section>
    <div class="bar">
      <van-button
        block
        round
        color="var(--sm-primary)"
        :disabled="item.stock <= 0"
        @click="exchange"
      >
        {{ t('m7.exchange') }}
      </van-button>
    </div>
  </div>
</template>

<style scoped>
.hero {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
}
.info {
  padding: 1rem;
}
.cost {
  color: var(--sm-primary);
  font-weight: 700;
  font-size: 1.125rem;
}
.stock {
  color: #888;
  font-size: 0.8125rem;
}
.bar {
  padding: 0.75rem 1rem calc(0.75rem + env(safe-area-inset-bottom));
}
</style>
