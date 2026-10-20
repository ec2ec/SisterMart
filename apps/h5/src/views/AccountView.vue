<script setup lang="ts">
import { computed, onActivated, onDeactivated, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { api, ApiError } from '../api/client'
import { fetchOrderCounts, uploadAvatar, type OrderCounts } from '../api/trade'
import { accountMock } from '../mock/data'
import AvatarCropDialog from '../components/AvatarCropDialog.vue'
import { restoreRouteScroll, saveRouteScroll } from '../composables/useRouteScroll'
import {
  clearSession,
  loggedIn,
  memberState,
  setSession,
  syncSessionFromStorage,
  token,
  type Member,
} from '../composables/useSession'
import { toggleLocale } from '../i18n'
import { assetUrl } from '../utils/assetUrl'

defineOptions({ name: 'AccountView' })

const { t, locale } = useI18n()
const router = useRouter()
const SCROLL_KEY = 'account'
/** 按鈕表示「即將切換到」的語言：當前繁中 → 展示美國旗（切英文）；當前英文 → 香港區旗 */
const nextLocaleFlag = computed(() => (locale.value === 'zh-HK' ? '🇺🇸' : '🇭🇰'))
const u = accountMock
const showAvatarCrop = ref(false)
const avatarBusy = ref(false)
const orderCounts = ref<OrderCounts>({
  pending_payment: 0,
  pending_shipment: 0,
  pending_receipt: 0,
  to_review: 0,
  aftersales: 0,
})

function badgeText(n: number): string {
  if (n <= 0) return ''
  return n > 99 ? '99+' : String(n)
}

const displayName = computed(
  () =>
    memberState.value?.nickname ||
    memberState.value?.email ||
    memberState.value?.phone ||
    u.name,
)
const displaySub = computed(() =>
  memberState.value ? t('account.promo', { code: memberState.value.promo_code }) : u.phone,
)
const avatarLetter = computed(() => (displayName.value || 'S').slice(0, 1).toUpperCase())
const avatarSrc = computed(() => {
  const url = memberState.value?.avatar_url
  if (!url) return ''
  return assetUrl(url)
})

function openAccount() {
  syncSessionFromStorage()
  if (loggedIn.value) return
  router.push({ name: 'login', query: { redirect: '/account' } })
}

function onAvatarClick() {
  syncSessionFromStorage()
  if (!loggedIn.value) {
    router.push({ name: 'login', query: { redirect: '/account' } })
    return
  }
  showAvatarCrop.value = true
}

async function onAvatarConfirm(blob: Blob) {
  avatarBusy.value = true
  try {
    const data = await uploadAvatar(blob)
    setSession(token(), data.member)
    showToast(t('account.avatarOk'))
  } catch (e) {
    const msg = e instanceof ApiError && e.message ? e.message : t('account.avatarFail')
    showToast(msg)
  } finally {
    avatarBusy.value = false
  }
}

async function refreshOrderCounts() {
  if (!loggedIn.value) {
    orderCounts.value = {
      pending_payment: 0,
      pending_shipment: 0,
      pending_receipt: 0,
      to_review: 0,
      aftersales: 0,
    }
    return
  }
  try {
    orderCounts.value = await fetchOrderCounts()
  } catch {
    /* 角標失敗不擋頁面 */
  }
}

async function refreshAccount() {
  syncSessionFromStorage()
  if (!loggedIn.value) {
    await refreshOrderCounts()
    return
  }
  try {
    const data = await api<Member>('/api/v1/members/me', {}, 'member')
    setSession(token(), data)
    await refreshOrderCounts()
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      clearSession()
      await refreshOrderCounts()
    }
  }
}

onMounted(() => {
  void refreshAccount()
})
onDeactivated(() => saveRouteScroll(SCROLL_KEY))
let accountFirstActivate = true
onActivated(() => {
  if (accountFirstActivate) {
    accountFirstActivate = false
    restoreRouteScroll(SCROLL_KEY)
    return
  }
  // keep-alive：從登錄頁返回時 onMounted 不會再跑，必須在此同步登錄態
  void refreshAccount().then(() => restoreRouteScroll(SCROLL_KEY))
})

async function logout() {
  try {
    await api('/api/v1/member-sessions/current', { method: 'DELETE' }, 'member')
  } catch {
    /* 本地仍清除令牌 */
  }
  clearSession()
  orderCounts.value = {
    pending_payment: 0,
    pending_shipment: 0,
    pending_receipt: 0,
    to_review: 0,
    aftersales: 0,
  }
  showToast(t('auth.loggedOut'))
  router.replace('/')
}

const orderItems = [
  { key: 'toPay', icon: 'credit-pay', tab: 'pending_payment', countKey: 'pending_payment' as const },
  { key: 'toShip', icon: 'logistics', tab: 'pending_shipment', countKey: 'pending_shipment' as const },
  { key: 'toReceive', icon: 'send-gift-o', tab: 'pending_receipt', countKey: 'pending_receipt' as const },
  { key: 'toReview', icon: 'orders-o', tab: 'to_review', countKey: 'to_review' as const },
  { key: 'returns', icon: 'replay', route: 'aftersales', countKey: 'aftersales' as const },
] as const

function orderBadge(o: (typeof orderItems)[number]): string {
  if (!loggedIn.value) return ''
  return badgeText(orderCounts.value[o.countKey] || 0)
}

const services = [
  { key: 'profile', icon: 'user-o', route: 'profile' },
  { key: 'address', icon: 'location-o', route: 'addresses' },
  { key: 'wishlist', icon: 'like-o', route: 'wishlist' },
  { key: 'footprint', icon: 'browsing-history-o', route: 'footprint' },
  { key: 'bills', icon: 'balance-list-o', route: 'balance-logs' },
  { key: 'couponCenter', icon: 'coupon-o', route: 'coupon-center' },
  { key: 'myCoupons', icon: 'coupon-o', route: 'my-coupons' },
  { key: 'pointLogs', icon: 'gold-coin-o', route: 'point-logs' },
  { key: 'pointsMall', icon: 'shop-o', route: 'points-mall' },
  { key: 'dailyCheckin', icon: 'calendar-o', route: 'checkin' },
  { key: 'flashSale', icon: 'clock-o', route: 'seckill' },
  { key: 'affiliate', icon: 'friends-o', route: 'distribution' },
  { key: 'support', icon: 'chat-o', route: 'messages' },
] as const

const spentDisplay = computed(() => {
  const n = loggedIn.value ? Number(memberState.value?.total_spent ?? 0).toFixed(0) : String(u.spent)
  const stars = '★'.repeat(loggedIn.value ? memberState.value?.level?.stars || 1 : 3)
  return t('account.spent', { n }).replace(/★+/g, stars)
})

const routePaths: Record<string, string> = {
  profile: '/profile',
  addresses: '/addresses',
  wishlist: '/wishlist',
  footprint: '/footprint',
  'balance-logs': '/balance-logs',
  'coupon-center': '/coupons',
  'my-coupons': '/my-coupons',
  'point-logs': '/point-logs',
  'points-mall': '/points-mall',
  checkin: '/checkin',
  seckill: '/seckill',
  distribution: '/distribution',
  messages: '/messages',
  home: '/',
}

function requireAuth(redirect: string): boolean {
  syncSessionFromStorage()
  if (loggedIn.value) return true
  router.push({ name: 'login', query: { redirect } })
  return false
}

function goService(route: string) {
  if (!route) {
    showToast(t('pages.coming'))
    return
  }
  const path = routePaths[route] || `/${route}`
  const needAuth = !['home', 'seckill', 'points-mall', 'coupon-center'].includes(route)
  if (needAuth && !requireAuth(path)) return
  router.push({ name: route })
}

function goLevel() {
  if (!requireAuth('/member-level')) return
  router.push({ name: 'member-level' })
}

function goCoupons() {
  if (!requireAuth('/my-coupons')) return
  router.push({ name: 'my-coupons' })
}

function goPoints() {
  if (!requireAuth('/point-logs')) return
  router.push({ name: 'point-logs' })
}

function goBills() {
  if (!requireAuth('/balance-logs')) return
  router.push({ name: 'balance-logs' })
}

function goOrders(tab?: string) {
  const path = tab ? `/orders?tab=${tab}` : '/orders'
  if (!requireAuth(path)) return
  router.push({ name: 'orders', query: tab ? { tab } : {} })
}
</script>

<template>
  <div class="page page--soft">
    <section class="hero">
      <div class="user" role="button" @click="openAccount">
        <button type="button" class="avatar" :disabled="avatarBusy" @click.stop="onAvatarClick">
          <img v-if="avatarSrc" :src="avatarSrc" alt="" />
          <span v-else>{{ avatarLetter }}</span>
        </button>
        <div>
          <h1>{{ displayName }}</h1>
          <p class="phone">{{ displaySub }}</p>
          <p class="spent">{{ spentDisplay }}</p>
        </div>
      </div>
      <div class="stats">
        <div role="button" @click="goBills">
          <strong>{{ loggedIn ? (memberState?.balance ?? 0).toFixed(2) : u.balance }}</strong>
          <span>{{ t('account.balance') }}</span>
        </div>
        <div role="button" @click="goCoupons">
          <strong>{{ loggedIn ? (memberState?.coupon_count ?? 0) : u.coupons }}</strong>
          <span>{{ t('account.coupons') }}</span>
        </div>
        <div role="button" @click="goPoints">
          <strong>{{ loggedIn ? (memberState?.points ?? 0) : u.points }}</strong>
          <span>{{ t('account.points') }}</span>
        </div>
      </div>
    </section>

    <div class="quick">
      <button type="button" @click="goLevel">
        <strong>{{ t('account.member') }}</strong>
        <span>{{ t('account.memberSub') }}</span>
      </button>
      <button type="button" @click="goService('points-mall')">
        <strong>{{ t('account.rewards') }}</strong>
        <span>{{ t('account.rewardsSub') }}</span>
      </button>
    </div>

    <section class="card">
      <div class="card-head">
        <h2>{{ t('account.orders') }}</h2>
        <button type="button" class="more" @click="() => goOrders()">{{ t('account.more') }}</button>
      </div>
      <div class="orders">
        <button
          v-for="o in orderItems"
          :key="o.key"
          type="button"
          @click="'route' in o && o.route ? goService(o.route) : goOrders('tab' in o ? o.tab : undefined)"
        >
          <van-badge :content="orderBadge(o)" :show-zero="false">
            <van-icon :name="o.icon" size="1.375rem" />
          </van-badge>
          <span>{{ t(`account.${o.key}`) }}</span>
        </button>
      </div>
    </section>

    <section class="checkin">
      <div class="left">
        <van-icon name="calendar-o" color="var(--sm-primary)" size="1.25rem" />
        <div>
          <p class="title">{{ t('account.checkinTitle') }}</p>
          <p class="sub">{{ t('account.checkinSub', { n: loggedIn ? 0 : u.streak }) }}</p>
        </div>
      </div>
      <van-button size="small" round color="var(--sm-primary)" @click="goService('checkin')">
        {{ t('account.checkinBtn') }}
      </van-button>
    </section>

    <section class="card services">
      <button v-for="s in services" :key="s.key" type="button" @click="goService(s.route)">
        <van-icon :name="s.icon" size="1.375rem" />
        <span>{{ t(`account.${s.key}`) }}</span>
      </button>
      <button type="button" :title="t('account.switchLang')" @click="toggleLocale">
        <span class="flag" aria-hidden="true">{{ nextLocaleFlag }}</span>
        <span>{{ t('account.switchLang') }}</span>
      </button>
    </section>

    <button v-if="loggedIn" type="button" class="lang" @click="logout">{{ t('account.logout') }}</button>

    <AvatarCropDialog v-model:show="showAvatarCrop" @confirm="onAvatarConfirm" />
  </div>
</template>

<style scoped>
.hero {
  position: relative;
  padding: 1.25rem 1rem 0.875rem;
  background: linear-gradient(180deg, #ffe8df 0%, #fff 70%);
}

.user {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
}

.avatar {
  width: 3.625rem;
  height: 3.625rem;
  border: 0;
  padding: 0;
  border-radius: 50%;
  background: #fff;
  color: var(--sm-primary);
  display: grid;
  place-items: center;
  font-size: 1.625rem;
  font-weight: 700;
  box-shadow: 0 0.125rem 0.5rem rgba(238, 80, 47, 0.15);
  overflow: hidden;
  flex-shrink: 0;
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.user h1 {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.35;
  word-break: break-all;
}

.phone,
.spent {
  margin: 0.125rem 0 0;
  font-size: 0.75rem;
  color: #888;
}

.stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  margin-top: 1rem;
  text-align: center;
}

.stats strong {
  display: block;
  font-size: 1.125rem;
}

.stats span {
  font-size: 0.75rem;
  color: #888;
}

.quick {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.625rem;
  padding: 0 0.75rem;
}

.quick button {
  border: 0;
  text-align: left;
  padding: 0.875rem;
  border-radius: 0.75rem;
  background: #fff;
  box-shadow: 0 0.0625rem 0.375rem rgba(0, 0, 0, 0.04);
}

.quick strong {
  display: block;
  font-size: 0.875rem;
}

.quick span {
  display: block;
  margin-top: 0.25rem;
  font-size: 0.6875rem;
  color: #999;
}

.card {
  margin: 0.75rem;
  padding: 0.75rem;
  border-radius: 0.875rem;
  background: #fff;
}

.card-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.card-head h2 {
  margin: 0;
  font-size: 0.9375rem;
}

.more {
  border: 0;
  background: transparent;
  font-size: 0.75rem;
  color: #999;
}

.orders {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0.25rem;
}

.orders button,
.services button {
  border: 0;
  background: transparent;
  padding: 0.5rem 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.6875rem;
  color: #333;
}

.checkin {
  margin: 0 0.75rem;
  padding: 0.625rem 0.75rem;
  border: 0.0625rem solid #ffc9b8;
  border-radius: 0.75rem;
  background: #fff8f5;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.625rem;
}

.checkin .left {
  display: flex;
  gap: 0.625rem;
  align-items: center;
}

.checkin .title {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
}

.checkin .sub {
  margin: 0.125rem 0 0;
  font-size: 0.6875rem;
  color: #999;
}

.services {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.25rem 0;
  margin-bottom: 0.5rem;
}

.flag {
  display: grid;
  place-items: center;
  width: 1.375rem;
  height: 1.375rem;
  font-size: 1.125rem;
  line-height: 1;
}

.lang {
  display: block;
  width: calc(100% - 1.5rem);
  margin: 0 0.75rem 1.25rem;
  border: 0.0625rem solid #eee;
  border-radius: 0.625rem;
  background: #fff;
  padding: 0.75rem;
  color: #666;
  font-size: 0.8125rem;
}
</style>
