<script setup lang="ts">
import { goLogin } from '../composables/useAuth'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import CouponRulesPopup from '../components/CouponRulesPopup.vue'
import SubPageNav from '../components/SubPageNav.vue'
import { api, ApiError } from '../api/client'
import { isLoggedIn } from '../composables/useSession'
import { assetUrl } from '../utils/assetUrl'

type Item = {
  id: number
  code: string
  name: { 'zh-HK': string; en: string }
  rules?: { 'zh-HK': string; en: string }
  background_image?: string | null
  type: string
  threshold: number
  amount: number
  valid_days?: number
  can_claim: boolean
  claim_blocked_reason: string
  stock_left: number
  upcoming?: boolean
  starts_in_ms?: number
  starts_at?: string | null
  ends_at?: string | null
  claimed_by_me?: number
}

const { t, locale } = useI18n()
const router = useRouter()
const items = ref<Item[]>([])
const rulesShow = ref(false)
const rulesHtml = ref('')
const rulesTitle = ref('')

function nameOf(row: Item) {
  return locale.value === 'en' ? row.name.en : row.name['zh-HK']
}

function rulesOf(row: Item) {
  const pack = row.rules || { 'zh-HK': '', en: '' }
  return locale.value === 'en' ? pack.en || pack['zh-HK'] || '' : pack['zh-HK'] || pack.en || ''
}

function hasRules(row: Item) {
  return Boolean(rulesOf(row).replace(/<[^>]+>/g, '').trim())
}

function desc(row: Item) {
  if (row.type === 'free_shipping') return t('m6.freeShip')
  if (row.type === 'discount') return t('m6.discountOff', { n: row.amount })
  return t('m6.fixedOff', { n: row.amount })
}

function validText(row: Item) {
  const days = Number(row.valid_days ?? 0)
  return days > 0 ? t('m6.validDays', { n: days }) : t('m6.validForever')
}

function isUpcoming(row: Item) {
  return Boolean(row.upcoming) || row.claim_blocked_reason === 'not_started'
}

function startsInMs(row: Item) {
  const fromApi = Number(row.starts_in_ms ?? 0)
  if (fromApi > 0) return fromApi
  if (!row.starts_at) return 0
  const ts = Date.parse(String(row.starts_at).replace(' ', 'T'))
  if (!Number.isFinite(ts)) return 0
  return Math.max(0, ts - Date.now())
}

function claimLabel(row: Item) {
  if (isUpcoming(row)) return t('m6.waitStart')
  if (row.can_claim || !isLoggedIn()) return t('m6.claim')
  return t('m6.claimedDone')
}

function claimDisabled(row: Item) {
  if (isUpcoming(row)) return true
  return !row.can_claim && isLoggedIn()
}

/** 已開始且尚未領取：顯示離發放結束剩餘天數 */
function isStartedUnclaimed(row: Item) {
  if (isUpcoming(row)) return false
  if ((row.claimed_by_me ?? 0) > 0) return false
  if (isLoggedIn() && !row.can_claim && row.claim_blocked_reason === 'limit_reached') return false
  return true
}

function endsRemainingText(row: Item) {
  if (!isStartedUnclaimed(row) || !row.ends_at) return ''
  const ts = Date.parse(String(row.ends_at).replace(' ', 'T'))
  if (!Number.isFinite(ts)) return ''
  const ms = ts - Date.now()
  if (ms <= 0) return ''
  const days = Math.max(1, Math.ceil(ms / 86400000))
  return t('m6.endsInDays', { n: days })
}

function bgStyle(row: Item) {
  const raw = (row.background_image || '').trim()
  if (!raw) return undefined
  const url = assetUrl(raw)
  return {
    backgroundImage: `url("${url}")`,
    backgroundSize: '100% auto',
    backgroundRepeat: 'no-repeat',
    backgroundPosition: 'center top',
    backgroundColor: 'transparent',
  } as Record<string, string>
}

async function load() {
  const data = await api<{ items: Item[] }>('/api/v1/coupons/claimable', {}, isLoggedIn() ? 'member' : 'none')
  items.value = data.items
}

onMounted(load)

async function claim(row: Item) {
  if (isUpcoming(row)) return
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

function openRules(row: Item) {
  rulesTitle.value = t('m6.rules')
  rulesHtml.value = rulesOf(row)
  rulesShow.value = true
}

function onCountdownFinish() {
  void load()
}

const popupTitle = computed(() => rulesTitle.value)
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('account.couponCenter')" />
    <div class="toolbar">
      <button type="button" @click="router.push({ name: 'my-coupons' })">{{ t('m6.myCoupons') }}</button>
    </div>
    <p v-if="!items.length" class="empty">{{ t('m6.noClaimable') }}</p>
    <div
      v-for="row in items"
      :key="row.id"
      class="coupon"
      :class="{ 'has-bg': !!bgStyle(row) }"
      :style="bgStyle(row)"
    >
      <div class="main">
        <div class="left">
          <strong>{{ nameOf(row) }}</strong>
          <span>{{ desc(row) }} · {{ t('m6.threshold', { n: row.threshold }) }}</span>
          <small>{{ validText(row) }} · {{ t('m6.left', { n: row.stock_left }) }}</small>
        </div>
        <van-button
          size="small"
          round
          :disabled="claimDisabled(row)"
          color="var(--sm-primary)"
          @click="claim(row)"
        >
          {{ claimLabel(row) }}
        </van-button>
      </div>
      <div
        v-if="hasRules(row) || isUpcoming(row) || endsRemainingText(row)"
        class="foot"
        :class="{
          'has-tip': isUpcoming(row) || !!endsRemainingText(row),
          'tip-only': (isUpcoming(row) || !!endsRemainingText(row)) && !hasRules(row),
        }"
      >
        <button
          v-if="hasRules(row)"
          type="button"
          class="rules-btn"
          @click="openRules(row)"
        >
          <van-icon name="info" />
          <span>{{ t('m6.rules') }}</span>
        </button>
        <div v-if="isUpcoming(row)" class="side-tip">
          <span>{{ t('m6.upcomingBefore') }}</span>
          <van-count-down
            class="upcoming-cd"
            :time="startsInMs(row)"
            format="HH:mm:ss"
            @finish="onCountdownFinish"
          />
          <span>{{ t('m6.upcomingAfter') }}</span>
        </div>
        <div v-else-if="endsRemainingText(row)" class="side-tip ends-tip">
          {{ endsRemainingText(row) }}
        </div>
      </div>
    </div>

    <CouponRulesPopup v-model:show="rulesShow" :title="popupTitle" :html="rulesHtml" />
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
  flex-direction: column;
  gap: 0.65rem;
  width: 100%;
  box-sizing: border-box;
  margin: 0.75rem 0;
  padding: 1rem;
  border-radius: 0;
  background: linear-gradient(135deg, #fff8f0, #fff);
  border: 1px solid #f0e0d0;
  border-left: 0;
  border-right: 0;
}
.coupon.has-bg {
  border-color: transparent;
}
.main {
  display: flex;
  align-items: center;
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
  font-size: 1rem;
}
.left span,
.left small {
  color: #888;
  font-size: 0.75rem;
}
.foot {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.foot.has-tip {
  justify-content: space-between;
}
.foot.tip-only {
  justify-content: flex-end;
}
.rules-btn {
  flex: none;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  margin: 0;
  padding: 0;
  border: 0;
  background: none;
  color: var(--sm-primary);
  font-size: 0.75rem;
  line-height: 1.2;
}
.side-tip {
  margin-left: auto;
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 0.15rem;
  text-align: right;
  color: #8a8378;
  font-size: 0.75rem;
  line-height: 1.4;
}
.ends-tip {
  color: #c45c4a;
}
.upcoming-cd {
  display: inline-block;
  color: var(--sm-primary);
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
  line-height: 1.4;
}
.upcoming-cd :deep(.van-count-down) {
  color: inherit;
  font-size: inherit;
  line-height: inherit;
}
</style>
