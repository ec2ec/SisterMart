<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import CouponRulesPopup from '../components/CouponRulesPopup.vue'
import SubPageNav from '../components/SubPageNav.vue'
import { api } from '../api/client'

type Row = {
  grant_id: number
  status: string
  expires_at: string | null
  coupon: {
    code: string
    name: { 'zh-HK': string; en: string }
    rules?: { 'zh-HK': string; en: string }
    type: string
    threshold: number
    amount: number
  }
}

const { t, locale } = useI18n()
const tab = ref('unused')
const items = ref<Row[]>([])
const rulesShow = ref(false)
const rulesHtml = ref('')
const rulesTitle = ref('')

async function load() {
  const data = await api<{ items: Row[] }>(`/api/v1/my-coupons?status=${tab.value}`, {}, 'member')
  items.value = data.items
}

onMounted(load)

function nameOf(row: Row) {
  return locale.value === 'en' ? row.coupon.name.en : row.coupon.name['zh-HK']
}

function rulesOf(row: Row) {
  const pack = row.coupon.rules || { 'zh-HK': '', en: '' }
  return locale.value === 'en' ? pack.en || pack['zh-HK'] || '' : pack['zh-HK'] || pack.en || ''
}

function hasRules(row: Row) {
  return Boolean(rulesOf(row).replace(/<[^>]+>/g, '').trim())
}

function statusLabel(status: string) {
  const map: Record<string, string> = {
    unused: 'm6.unused',
    used: 'm6.used',
    expired: 'm6.expired',
    frozen: 'm6.frozen',
  }
  const key = map[status]
  return key ? t(key) : status
}

function expiryText(row: Row) {
  if (!row.expires_at) return t('m6.validForever')
  const d = String(row.expires_at).slice(0, 10)
  return t('m6.expiresAt', { d })
}

function openRules(row: Row) {
  rulesTitle.value = t('m6.rules')
  rulesHtml.value = rulesOf(row)
  rulesShow.value = true
}

const popupTitle = computed(() => rulesTitle.value)
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('m6.myCoupons')" />
    <van-tabs v-model:active="tab" @change="load">
      <van-tab :title="t('m6.unused')" name="unused" />
      <van-tab :title="t('m6.used')" name="used" />
      <van-tab :title="t('m6.expired')" name="expired" />
    </van-tabs>
    <p v-if="!items.length" class="empty">{{ t('m6.noCoupons') }}</p>
    <div v-else class="list">
      <div v-for="row in items" :key="row.grant_id" class="coupon">
        <div class="main">
          <div class="left">
            <strong>{{ nameOf(row) }}</strong>
            <span>{{ row.coupon.code }} · ≥HK${{ row.coupon.threshold }}</span>
            <small>{{ expiryText(row) }}</small>
          </div>
          <em class="status">{{ statusLabel(row.status) }}</em>
        </div>
        <button
          v-if="hasRules(row)"
          type="button"
          class="rules-btn"
          @click="openRules(row)"
        >
          <van-icon name="info-o" />
          <span>{{ t('m6.rules') }}</span>
        </button>
      </div>
    </div>

    <CouponRulesPopup v-model:show="rulesShow" :title="popupTitle" :html="rulesHtml" />
  </div>
</template>

<style scoped>
.empty {
  text-align: center;
  color: #999;
  padding: 2rem;
}
.list {
  padding: 0.5rem 0 1rem;
}
.coupon {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  margin: 0.75rem 1rem;
  padding: 1rem;
  border-radius: 0.75rem;
  background: #fff;
  border: 1px solid #eee;
}
.main {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}
.left {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}
.left strong {
  font-size: 0.9375rem;
  color: #222;
}
.left span,
.left small {
  color: #888;
  font-size: 0.75rem;
}
.status {
  flex: none;
  font-style: normal;
  font-size: 0.75rem;
  color: var(--sm-primary);
}
.rules-btn {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  margin: 0;
  padding: 0.2rem 0.6rem;
  border: 1px solid var(--sm-primary);
  border-radius: 999px;
  background: transparent;
  color: var(--sm-primary);
  font-size: 0.75rem;
  line-height: 1.2;
}
</style>
