<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { api, ApiError } from '../api/client'
import { uploadAvatar } from '../api/trade'
import { accountMock } from '../mock/data'
import AvatarCropDialog from '../components/AvatarCropDialog.vue'
import { clearSession, isLoggedIn, memberState, type Member } from '../composables/useSession'
import { toggleLocale } from '../i18n'

const { t } = useI18n()
const router = useRouter()
const u = accountMock
const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'
const showAvatarCrop = ref(false)
const avatarBusy = ref(false)

const loggedIn = computed(() => isLoggedIn() && memberState.value)
const displayName = computed(() => memberState.value?.email || memberState.value?.phone || u.name)
const displaySub = computed(() =>
  memberState.value ? t('account.promo', { code: memberState.value.promo_code }) : u.phone,
)
const avatarLetter = computed(() => (displayName.value || 'S').slice(0, 1).toUpperCase())
const avatarSrc = computed(() => {
  const url = memberState.value?.avatar_url
  if (!url) return ''
  return url.startsWith('http') ? url : `${apiBase}${url}`
})

function openAccount() {
  if (loggedIn.value) return
  router.push({ name: 'login', query: { redirect: '/account' } })
}

function onAvatarClick() {
  if (!loggedIn.value) {
    openAccount()
    return
  }
  showAvatarCrop.value = true
}

async function onAvatarConfirm(blob: Blob) {
  avatarBusy.value = true
  try {
    const data = await uploadAvatar(blob)
    memberState.value = data.member
    localStorage.setItem('sm_member', JSON.stringify(data.member))
    showToast(t('account.avatarOk'))
  } catch (e) {
    const msg = e instanceof ApiError && e.message ? e.message : t('account.avatarFail')
    showToast(msg)
  } finally {
    avatarBusy.value = false
  }
}

onMounted(async () => {
  if (!isLoggedIn()) return
  try {
    const data = await api<Member>('/api/v1/members/me', {}, 'member')
    memberState.value = data
    localStorage.setItem('sm_member', JSON.stringify(data))
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) clearSession()
  }
})

async function logout() {
  try {
    await api('/api/v1/member-sessions/current', { method: 'DELETE' }, 'member')
  } catch {
    /* 本地仍清除令牌 */
  }
  clearSession()
  showToast(t('auth.loggedOut'))
  router.replace('/')
}

const orderItems = [
  { key: 'toPay', icon: 'credit-pay', tab: 'pending_payment' },
  { key: 'toShip', icon: 'logistics', badge: u.toShipBadge, tab: 'pending_shipment' },
  { key: 'toReceive', icon: 'send-gift-o', tab: 'pending_receipt' },
  { key: 'toReview', icon: 'orders-o', tab: 'to_review' },
  { key: 'returns', icon: 'replay', route: 'aftersales' },
] as const

const services = [
  { key: 'address', icon: 'location-o', route: 'addresses' },
  { key: 'wishlist', icon: 'star-o', route: 'wishlist' },
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

function goService(route: string) {
  if (!route) {
    showToast(t('pages.coming'))
    return
  }
  const path = routePaths[route] || `/${route}`
  const needAuth = !['home', 'seckill', 'points-mall', 'coupon-center'].includes(route)
  if (!isLoggedIn() && needAuth) {
    router.push({ name: 'login', query: { redirect: path } })
    return
  }
  router.push({ name: route })
}

function goLevel() {
  if (!isLoggedIn()) {
    router.push({ name: 'login', query: { redirect: '/member-level' } })
    return
  }
  router.push({ name: 'member-level' })
}

function goCoupons() {
  if (!isLoggedIn()) {
    router.push({ name: 'login', query: { redirect: '/my-coupons' } })
    return
  }
  router.push({ name: 'my-coupons' })
}

function goPoints() {
  if (!isLoggedIn()) {
    router.push({ name: 'login', query: { redirect: '/point-logs' } })
    return
  }
  router.push({ name: 'point-logs' })
}

function goBills() {
  if (!isLoggedIn()) {
    router.push({ name: 'login', query: { redirect: '/balance-logs' } })
    return
  }
  router.push({ name: 'balance-logs' })
}

function goOrders(tab?: string) {
  const path = tab ? `/orders?tab=${tab}` : '/orders'
  if (!isLoggedIn()) {
    router.push({ name: 'login', query: { redirect: path } })
    return
  }
  router.push({ name: 'orders', query: tab ? { tab } : {} })
}
</script>

<template>
  <div class="page page--soft">
    <section class="hero">
      <button class="settings" type="button" :title="t('account.switchLang')" @click="toggleLocale">
        <van-icon name="setting-o" size="1.125rem" />
      </button>
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
          <van-badge :content="(!loggedIn && 'badge' in o && o.badge) || ''" :show-zero="false">
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
    </section>

    <button type="button" class="lang" @click="toggleLocale">{{ t('account.switchLang') }}</button>
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

.settings {
  position: absolute;
  top: 0.875rem;
  right: 0.875rem;
  border: 0;
  background: transparent;
  color: #666;
}

.user {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  padding-right: 2.25rem;
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
