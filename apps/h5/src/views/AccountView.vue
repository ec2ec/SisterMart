<script setup lang="ts">
import { computed, onActivated, onDeactivated, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { api, ApiError } from '../api/client'
import { fetchCheckinCalendar } from '../api/m7'
import { uploadAvatar } from '../api/trade'
import AvatarCropDialog from '../components/AvatarCropDialog.vue'
import HomeFooter from '../components/HomeFooter.vue'
import SupportContactPopup from '../components/SupportContactPopup.vue'
import { badgeText, useOrderCounts } from '../composables/useOrderCounts'
import { restoreRouteScroll, saveRouteScroll } from '../composables/useRouteScroll'
import { fetchHomeOps } from '../api/catalog'
import {
  clearSession,
  loggedIn,
  memberState,
  setSession,
  syncSessionFromStorage,
  token,
  type Member,
} from '../composables/useSession'
import { goLogin } from '../composables/useAuth'
import { distributionAllowed, useMaintenance } from '../composables/useMaintenance'
import { APP_VERSION } from '../config/appVersion'
import { toggleLocale } from '../i18n'
import { assetUrl } from '../utils/assetUrl'

defineOptions({ name: 'AccountView' })

const { t, locale } = useI18n()
const router = useRouter()
const SCROLL_KEY = 'account'
/** 按鈕表示「即將切換到」的語言：當前繁中 → 展示美國旗（切英文）；當前英文 → 香港區旗 */
const nextLocaleFlag = computed(() => (locale.value === 'zh-HK' ? '🇺🇸' : '🇭🇰'))
const showAvatarCrop = ref(false)
const avatarBusy = ref(false)
const { orderCounts, refreshOrderCounts } = useOrderCounts()
const checkinStreak = ref(0)
const checkinSignedToday = ref(false)
const guestDash = '-'
const showSupport = ref(false)
const footerSlogan = ref<{ 'zh-HK': string; en: string } | null>(null)

const checkinStreakDisplay = computed(() => (loggedIn.value ? checkinStreak.value : guestDash))

const displayName = computed(() => {
  if (!loggedIn.value) return t('account.guest')
  return (
    memberState.value?.nickname ||
    memberState.value?.email ||
    memberState.value?.phone ||
    t('account.guest')
  )
})
const displaySub = computed(() =>
  loggedIn.value && memberState.value
    ? t('account.promo', { code: memberState.value.promo_code })
    : guestDash,
)
const avatarLetter = computed(() => (displayName.value || 'G').slice(0, 1).toUpperCase())
const avatarSrc = computed(() => {
  const url = memberState.value?.avatar_url
  if (!url) return ''
  return assetUrl(url)
})

function showAppVersion() {
  showToast(t('account.versionToast', { v: APP_VERSION }))
}

function openAccount() {
  syncSessionFromStorage()
  if (loggedIn.value) return
  void goLogin(router, '/account')
}

function onAvatarClick() {
  syncSessionFromStorage()
  if (!loggedIn.value) {
    void goLogin(router, '/account')
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

async function refreshCheckinStreak() {
  if (!loggedIn.value) {
    checkinStreak.value = 0
    checkinSignedToday.value = false
    return
  }
  try {
    const cal = await fetchCheckinCalendar()
    checkinStreak.value = Number(cal.streak) || 0
    checkinSignedToday.value = Boolean(cal.signed_today)
  } catch {
    /* 簽到摘要失敗不擋頁面 */
  }
}

async function refreshAccount() {
  syncSessionFromStorage()
  if (!loggedIn.value) {
    await Promise.all([refreshOrderCounts(), refreshCheckinStreak(), refreshShopStatus()])
    return
  }
  try {
    const data = await api<Member>('/api/v1/members/me', {}, 'member')
    setSession(token(), data)
    await Promise.all([refreshOrderCounts(), refreshCheckinStreak(), refreshShopStatus()])
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      clearSession()
      await Promise.all([refreshOrderCounts(), refreshCheckinStreak(), refreshShopStatus()])
    }
  }
}

onMounted(() => {
  void refreshAccount()
  void loadFooterSlogan()
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
  checkinStreak.value = 0
  checkinSignedToday.value = false
  showToast(t('auth.loggedOut'))
  router.replace('/')
}

const orderItems = [
  { key: 'orderAll', icon: 'orders-o', tab: 'all' },
  { key: 'toPay', icon: 'credit-pay', tab: 'pending_payment', countKey: 'pending_payment' as const },
  { key: 'toShip', icon: 'logistics', tab: 'pending_shipment', countKey: 'pending_shipment' as const },
  { key: 'toReceive', icon: 'send-gift-o', tab: 'pending_receipt', countKey: 'pending_receipt' as const },
  { key: 'toReview', icon: 'comment-o', tab: 'to_review', countKey: 'to_review' as const },
  { key: 'orderDone', icon: 'checked', tab: 'completed' },
  { key: 'returns', icon: 'replay', route: 'aftersales', countKey: 'aftersales' as const },
] as const

function orderBadge(o: (typeof orderItems)[number]): string {
  if (!loggedIn.value) return ''
  if (!('countKey' in o) || !o.countKey) return ''
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
  { key: 'support', icon: 'service-o', action: 'support' as const },
  { key: 'messageCenter', icon: 'chat-o', route: 'messages' },
] as const

const { refresh: refreshShopStatus } = useMaintenance()

/** 分銷中心僅白名單會員可見 */
const visibleServices = computed(() =>
  services.filter((s) => s.key !== 'affiliate' || distributionAllowed.value),
)

const spentDisplay = computed(() => {
  if (!loggedIn.value) {
    return t('account.spent', { n: guestDash }).replace(/★+\s*/g, '')
  }
  const n = Number(memberState.value?.total_spent ?? 0).toFixed(0)
  const stars = '★'.repeat(memberState.value?.level?.stars || 1)
  return t('account.spent', { n }).replace(/★+/g, stars)
})

const statBalance = computed(() =>
  loggedIn.value ? Number(memberState.value?.balance ?? 0).toFixed(2) : guestDash,
)
const statCoupons = computed(() =>
  loggedIn.value ? String(memberState.value?.coupon_count ?? 0) : guestDash,
)
const statPoints = computed(() =>
  loggedIn.value ? String(memberState.value?.points ?? 0) : guestDash,
)

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
  void goLogin(router, redirect)
  return false
}

function goService(route: string) {
  if (!route) {
    showToast(t('pages.coming'))
    return
  }
  if (route === 'distribution' && !distributionAllowed.value) {
    showToast(t('account.affiliateDenied'))
    return
  }
  const path = routePaths[route] || `/${route}`
  const needAuth = !['home', 'seckill', 'points-mall', 'coupon-center'].includes(route)
  if (needAuth && !requireAuth(path)) return
  router.push({ name: route })
}

function onServiceClick(s: (typeof services)[number]) {
  if ('action' in s && s.action === 'support') {
    showSupport.value = true
    return
  }
  if ('route' in s) goService(s.route)
}

async function loadFooterSlogan() {
  try {
    const ops = await fetchHomeOps()
    footerSlogan.value = ops.footer_slogan
  } catch {
    /* 用 i18n 預設標語 */
  }
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
  const normalized = !tab || tab === 'all' ? undefined : tab
  const path = normalized ? `/orders?tab=${normalized}` : '/orders'
  if (!requireAuth(path)) return
  router.push({ name: 'orders', query: normalized ? { tab: normalized } : {} })
}
</script>

<template>
  <div class="page account-page">
    <section class="hero">
      <div class="user">
        <button type="button" class="avatar" :disabled="avatarBusy" @click="onAvatarClick">
          <img v-if="avatarSrc" :src="avatarSrc" alt="" />
          <span v-else>{{ avatarLetter }}</span>
        </button>
        <button type="button" class="user-meta sm-pressable" @click="openAccount">
          <h1>{{ displayName }}</h1>
          <p class="phone">{{ displaySub }}</p>
          <p class="spent">{{ spentDisplay }}</p>
        </button>
      </div>
      <div class="stats">
        <button type="button" class="sm-pressable" @click="goBills">
          <strong>{{ statBalance }}</strong>
          <span>{{ t('account.balance') }}</span>
        </button>
        <button type="button" class="sm-pressable" @click="goCoupons">
          <strong>{{ statCoupons }}</strong>
          <span>{{ t('account.coupons') }}</span>
        </button>
        <button type="button" class="sm-pressable" @click="goPoints">
          <strong>{{ statPoints }}</strong>
          <span>{{ t('account.points') }}</span>
        </button>
      </div>
    </section>

    <div class="quick">
      <button type="button" class="sm-pressable" @click="goLevel">
        <strong>{{ t('account.member') }}</strong>
        <span>{{ t('account.memberSub') }}</span>
      </button>
      <button type="button" class="sm-pressable" @click="goService('points-mall')">
        <strong>{{ t('account.rewards') }}</strong>
        <span>{{ t('account.rewardsSub') }}</span>
      </button>
    </div>

    <section class="card">
      <div class="card-head">
        <button type="button" class="orders-title sm-pressable" @click="goOrders('all')">
          <h2>{{ t('account.orders') }}</h2>
        </button>
        <button type="button" class="more sm-pressable" @click="goOrders('all')">{{ t('account.more') }}</button>
      </div>
      <div class="orders">
        <button
          v-for="o in orderItems"
          :key="o.key"
          type="button"
          class="sm-pressable"
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
        <van-icon name="calendar-o" color="#16162b" size="1.25rem" />
        <div>
          <p class="title">{{ t('account.checkinTitle') }}</p>
          <p class="sub">
            {{
              loggedIn
                ? t('account.checkinSub', { n: checkinStreakDisplay })
                : t('account.checkinSubGuest')
            }}
          </p>
        </div>
      </div>
      <van-button size="small" round color="#16162b" @click="goService('checkin')">
        {{ loggedIn && checkinSignedToday ? t('m7.signed') : t('account.checkinBtn') }}
      </van-button>
    </section>

    <section class="card services">
      <button
        v-for="s in visibleServices"
        :key="s.key"
        type="button"
        class="sm-pressable"
        @click="onServiceClick(s)"
      >
        <van-icon :name="s.icon" size="1.375rem" />
        <span>{{ t(`account.${s.key}`) }}</span>
      </button>
      <button type="button" class="sm-pressable" :title="t('account.switchLang')" @click="toggleLocale">
        <span class="flag" aria-hidden="true">{{ nextLocaleFlag }}</span>
        <span>{{ t('account.switchLang') }}</span>
      </button>
      <button
        type="button"
        class="ver-btn sm-pressable"
        :title="t('account.versionTip')"
        @click="showAppVersion"
      >
        <van-icon name="info-o" size="1.375rem" />
        <span>{{ APP_VERSION }}</span>
      </button>
    </section>

    <HomeFooter :slogan="footerSlogan" />

    <van-button
      v-if="loggedIn"
      class="lang"
      block
      round
      plain
      hairline
      type="primary"
      @click="logout"
    >
      {{ t('account.logout') }}
    </van-button>

    <SupportContactPopup v-model:show="showSupport" />
    <AvatarCropDialog
      v-model:show="showAvatarCrop"
      :current-src="avatarSrc"
      @confirm="onAvatarConfirm"
    />
  </div>
</template>

<style scoped>
/* 配色對齊登錄頁 AuthLayout：粉 → lavender → 淺藍（不改底欄 Tab） */
.account-page {
  --ac-accent: var(--sm-brand-sister);
  --ac-ink: #14141f;
  --ac-muted: #3a3540;
  --ac-soft: #6b6574;
  --ac-card-shadow: 0 0.25rem 0.9rem rgba(160, 130, 210, 0.22);
  position: relative;
  color: var(--ac-ink);
  /* 柔光併入背景，避免 ::before + filter 在部分 WebKit 上擋住點擊 */
  background:
    radial-gradient(circle at 92% -4%, rgba(255, 255, 255, 0.55) 0%, rgba(255, 182, 212, 0.28) 42%, transparent 68%),
    radial-gradient(circle at -8% 32%, rgba(255, 255, 255, 0.45) 0%, rgba(210, 190, 255, 0.28) 45%, transparent 70%),
    radial-gradient(circle at 108% 88%, rgba(255, 255, 255, 0.4) 0%, rgba(180, 220, 255, 0.32) 48%, transparent 72%),
    linear-gradient(
      165deg,
      #f46ca9 10%,
      #f0d8ee 28%,
      #e4daf6 55%,
      #7dbaf7 82%,
      #407cfb 100%
    );
}

.hero {
  position: relative;
  z-index: 1;
  padding: 1.25rem 1rem 0.875rem;
  background: transparent;
}

.user {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
}

.user-meta {
  flex: 1;
  min-width: 0;
  border: 0;
  background: transparent;
  padding: 0.15rem 0.35rem;
  margin: -0.15rem -0.35rem;
  border-radius: 0.5rem;
  text-align: left;
  color: inherit;
  font: inherit;
}

.avatar {
  position: relative;
  z-index: 2;
  width: 3.625rem;
  height: 3.625rem;
  border: 0.0625rem solid rgba(120, 90, 160, 0.18);
  padding: 0;
  border-radius: 50%;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, rgba(236, 228, 255, 0.7) 100%);
  color: var(--sm-primary);
  display: grid;
  place-items: center;
  font-size: 1.625rem;
  font-weight: 700;
  box-shadow: var(--ac-card-shadow);
  overflow: hidden;
  flex-shrink: 0;
  cursor: pointer;
  pointer-events: auto;
  -webkit-tap-highlight-color: transparent;
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.user-meta h1 {
  margin: 0;
  font-size: 0.9375rem;
  line-height: 1.35;
  word-break: break-all;
  color: var(--ac-ink);
  font-weight: 700;
}

.phone,
.spent {
  margin: 0.125rem 0 0;
  font-size: 0.75rem;
  color: var(--ac-soft);
}

.stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  margin-top: 1rem;
  text-align: center;
  gap: 0.25rem;
}

.stats > button {
  border: 0;
  background: transparent;
  padding: 0.35rem 0.25rem;
  border-radius: 0.5rem;
  color: inherit;
  font: inherit;
}

.stats strong {
  display: block;
  font-size: 1.125rem;
  color: var(--ac-ink);
}

.stats span {
  display: inline-block;
  margin-top: 0.2rem;
  font-size: 0.75rem;
  color: var(--ac-soft);
  border-top: 0.125rem solid rgba(222, 70, 173, 0.85);
  padding-top: 0.2rem;
}

.quick {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.625rem;
  padding: 0 0.75rem;
}

.quick button {
  border: 0.0625rem solid rgba(120, 90, 160, 0.12);
  text-align: left;
  padding: 0.875rem;
  border-radius: 1rem;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.88) 0%, rgba(236, 228, 255, 0.55) 100%);
  box-shadow: var(--ac-card-shadow);
  backdrop-filter: blur(8px);
}

.quick strong {
  display: block;
  font-size: 0.875rem;
  color: var(--ac-ink);
}

.quick span {
  display: block;
  margin-top: 0.25rem;
  font-size: 0.6875rem;
  color: var(--ac-soft);
}

.card {
  margin: 0.75rem;
  padding: 0.75rem;
  border-radius: 1rem;
  border: 0.0625rem solid rgba(120, 90, 160, 0.12);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.72) 100%);
  box-shadow: var(--ac-card-shadow);
  backdrop-filter: blur(8px);
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
  color: var(--ac-ink);
}

.orders-title {
  border: 0;
  background: transparent;
  padding: 0;
  text-align: left;
  color: inherit;
}

.more {
  border: 0;
  background: transparent;
  font-size: 0.75rem;
  color: var(--ac-muted);
}

.orders {
  display: flex;
  flex-wrap: nowrap;
  gap: 0.25rem;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  margin: 0 -0.25rem;
  padding: 0 0.25rem;
}

.orders::-webkit-scrollbar {
  display: none;
}

.orders button {
  flex: 0 0 auto;
  width: 4.25rem;
  border: 0;
  background: transparent;
  padding: 0.5rem 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.6875rem;
  color: var(--ac-ink);
}

.services button {
  border: 0;
  background: transparent;
  padding: 0.5rem 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.6875rem;
  color: var(--ac-ink);
}

.checkin {
  margin: 0 0.75rem;
  padding: 0.625rem 0.75rem;
  border: 0.0625rem solid rgba(120, 90, 160, 0.16);
  border-radius: 1rem;
  background: linear-gradient(100deg, #fdf9fd 0%, #dccefdb8 50%, #fba9cda6 100%);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.625rem;
  box-shadow: var(--ac-card-shadow);
  backdrop-filter: blur(8px);
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
  color: #d61d81;
}

.checkin .sub {
  margin: 0.125rem 0 0;
  font-size: 0.6875rem;
  color: var(--ac-soft);
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

.ver-btn span {
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
}

.lang {
  width: calc(100% - 1.5rem);
  margin: 0.25rem 0.75rem 1.25rem;
}

:deep(.footer) {
  margin: 0.25rem 0.75rem 2rem;
  padding: 1rem 0.75rem 0.75rem;
  border-radius: 1rem;
  border: 0.0625rem solid rgba(120, 90, 160, 0.12);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.72) 100%);
  box-shadow: var(--ac-card-shadow);
}
</style>
