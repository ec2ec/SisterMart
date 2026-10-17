<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchDistributionOverview } from '../api/m7'

const { t } = useI18n()
const router = useRouter()
const data = ref<Awaited<ReturnType<typeof fetchDistributionOverview>> | null>(null)

onMounted(async () => {
  data.value = await fetchDistributionOverview()
})

function copyLink() {
  if (!data.value) return
  const url = new URL(data.value.share_link, location.origin).toString()
  navigator.clipboard?.writeText(url).catch(() => undefined)
  showToast(t('pdp.copied'))
}

const links = [
  { name: 'dist-downlines', label: 'm7.downlines' },
  { name: 'dist-commissions', label: 'm7.commissions' },
  { name: 'dist-orders', label: 'm7.promoOrders' },
  { name: 'dist-withdraw', label: 'm7.withdraw' },
  { name: 'dist-poster', label: 'm7.poster' },
  { name: 'dist-ranking', label: 'm7.ranking' },
] as const
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('account.affiliate')" />
    <p class="mock">{{ t('m7.mockBanner') }}</p>
    <section class="hero" v-if="data">
      <p>{{ t('account.promo', { code: data.promo_code }) }}</p>
      <div class="stats">
        <div>
          <strong>{{ data.available_commission.toFixed(2) }}</strong>
          <span>{{ t('m7.available') }}</span>
        </div>
        <div>
          <strong>{{ data.pending_commission.toFixed(2) }}</strong>
          <span>{{ t('m7.pending') }}</span>
        </div>
        <div>
          <strong>{{ data.total_commission.toFixed(2) }}</strong>
          <span>{{ t('m7.totalComm') }}</span>
        </div>
      </div>
      <van-button size="small" round plain hairline color="#f7efe4" @click="copyLink">{{ t('m7.copyLink') }}</van-button>
    </section>
    <van-cell-group inset>
      <van-cell
        v-for="l in links"
        :key="l.name"
        :title="t(l.label)"
        is-link
        @click="router.push({ name: l.name })"
      />
    </van-cell-group>
  </div>
</template>

<style scoped>
.mock {
  margin: 0.5rem 1rem;
  font-size: 0.75rem;
  color: #a67c52;
}
.hero {
  margin: 1rem;
  padding: 1.25rem;
  border-radius: 1rem;
  background: linear-gradient(160deg, #1f2a24, #3d5248);
  color: #f3f7f4;
}
.stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.5rem;
  margin: 1rem 0;
  text-align: center;
}
.stats strong {
  display: block;
  font-size: 1.05rem;
}
.stats span {
  font-size: 0.7rem;
  opacity: 0.85;
}
</style>
