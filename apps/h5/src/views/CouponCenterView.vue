<script setup lang="ts">
import { goLogin } from '../composables/useAuth'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { api, ApiError } from '../api/client'
import { isLoggedIn } from '../composables/useSession'

type Item = {
  id: number
  code: string
  name: { 'zh-HK': string; en: string }
  type: string
  threshold: number
  amount: number
  can_claim: boolean
  claim_blocked_reason: string
  stock_left: number
}

const { t, locale } = useI18n()
const router = useRouter()
const items = ref<Item[]>([])

function nameOf(row: Item) {
  return locale.value === 'en' ? row.name.en : row.name['zh-HK']
}

function desc(row: Item) {
  if (row.type === 'free_shipping') return t('m6.freeShip')
  if (row.type === 'discount') return t('m6.discountOff', { n: row.amount })
  return t('m6.fixedOff', { n: row.amount })
}

async function load() {
  const data = await api<{ items: Item[] }>('/api/v1/coupons/claimable', {}, isLoggedIn() ? 'member' : 'none')
  items.value = data.items
}

onMounted(load)

async function claim(row: Item) {
  if (!isLoggedIn()) {
    void goLogin(router, '/coupons')
    return
  }
  try {
    await api(`/api/v1/coupons/${row.id}/claims`, { method: 'POST', body: '{}' }, 'member')
    showToast(t('m6.claimed'))
    await load()
  } catch (e) {
    showToast(e instanceof ApiError ? e.message : t('m6.claimFail'))
  }
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('account.couponCenter')" />
    <div class="toolbar">
      <button type="button" @click="router.push({ name: 'my-coupons' })">{{ t('m6.myCoupons') }}</button>
    </div>
    <p v-if="!items.length" class="empty">{{ t('m6.noClaimable') }}</p>
    <div v-for="row in items" :key="row.id" class="coupon">
      <div class="left">
        <strong>{{ nameOf(row) }}</strong>
        <span>{{ desc(row) }} · {{ t('m6.threshold', { n: row.threshold }) }}</span>
        <small>{{ row.code }} · {{ t('m6.left', { n: row.stock_left }) }}</small>
      </div>
      <van-button
        size="small"
        round
        :disabled="!row.can_claim && isLoggedIn()"
        color="var(--sm-primary)"
        @click="claim(row)"
      >
        {{ row.can_claim || !isLoggedIn() ? t('m6.claim') : t('m6.claimedDone') }}
      </van-button>
    </div>
  </div>
</template>

<style scoped>
.toolbar {
  padding: 0.5rem 1rem;
  text-align: right;
}
.toolbar button {
  border: 0;
  background: none;
  color: var(--sm-primary);
  font-size: 0.875rem;
}
.empty {
  text-align: center;
  color: #999;
  padding: 2rem;
}
.coupon {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin: 0.75rem 1rem;
  padding: 1rem;
  border-radius: 0.75rem;
  background: linear-gradient(135deg, #fff8f0, #fff);
  border: 1px solid #f0e0d0;
}
.left {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}
.left strong {
  font-size: 1rem;
}
.left span,
.left small {
  color: #888;
  font-size: 0.75rem;
}
</style>
