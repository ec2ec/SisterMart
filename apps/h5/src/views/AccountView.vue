<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { accountMock } from '../mock/data'
import { toggleLocale } from '../i18n'

const { t } = useI18n()
const router = useRouter()
const u = accountMock

const orderItems = [
  { key: 'toPay', icon: 'credit-pay' },
  { key: 'toShip', icon: 'logistics', badge: u.toShipBadge },
  { key: 'toReceive', icon: 'send-gift-o' },
  { key: 'toReview', icon: 'orders-o' },
  { key: 'returns', icon: 'replay' },
] as const

const services = [
  { key: 'address', icon: 'location-o', route: '' },
  { key: 'wishlist', icon: 'star-o', route: 'wishlist' },
  { key: 'couponCenter', icon: 'coupon-o', route: '' },
  { key: 'pointsMall', icon: 'shop-o', route: '' },
  { key: 'dailyCheckin', icon: 'calendar-o', route: '' },
  { key: 'flashSale', icon: 'clock-o', route: 'home' },
  { key: 'affiliate', icon: 'friends-o', route: '' },
  { key: 'support', icon: 'chat-o', route: 'messages' },
] as const

function goService(route: string) {
  if (!route) {
    showToast(t('pages.coming'))
    return
  }
  router.push({ name: route })
}
</script>

<template>
  <div class="page page--soft">
    <section class="hero">
      <button class="settings" type="button" :title="t('account.switchLang')" @click="toggleLocale">
        <van-icon name="setting-o" size="1.125rem" />
      </button>
      <div class="user" role="button" @click="router.push({ name: 'login', query: { redirect: '/account' } })">
        <div class="avatar">{{ u.avatarLetter }}</div>
        <div>
          <h1>{{ u.name }}</h1>
          <p class="phone">{{ u.phone }}</p>
          <p class="spent">{{ t('account.spent', { n: u.spent }) }}</p>
        </div>
      </div>
      <div class="stats">
        <div>
          <strong>{{ u.balance }}</strong>
          <span>{{ t('account.balance') }}</span>
        </div>
        <div>
          <strong>{{ u.coupons }}</strong>
          <span>{{ t('account.coupons') }}</span>
        </div>
        <div>
          <strong>{{ u.points }}</strong>
          <span>{{ t('account.points') }}</span>
        </div>
      </div>
    </section>

    <div class="quick">
      <button type="button" @click="showToast(t('pages.coming'))">
        <strong>{{ t('account.member') }}</strong>
        <span>{{ t('account.memberSub') }}</span>
      </button>
      <button type="button" @click="showToast(t('pages.coming'))">
        <strong>{{ t('account.rewards') }}</strong>
        <span>{{ t('account.rewardsSub') }}</span>
      </button>
    </div>

    <section class="card">
      <div class="card-head">
        <h2>{{ t('account.orders') }}</h2>
        <button type="button" class="more" @click="router.push({ name: 'orders' })">{{ t('account.more') }}</button>
      </div>
      <div class="orders">
        <button v-for="o in orderItems" :key="o.key" type="button" @click="router.push({ name: 'orders' })">
          <van-badge :content="('badge' in o && o.badge) || ''" :show-zero="false">
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
          <p class="sub">{{ t('account.checkinSub', { n: u.streak }) }}</p>
        </div>
      </div>
      <van-button size="small" round color="var(--sm-primary)" @click="showToast(t('account.checkinBtn'))">
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
  align-items: center;
}

.avatar {
  width: 3.625rem;
  height: 3.625rem;
  border-radius: 50%;
  background: #fff;
  color: var(--sm-primary);
  display: grid;
  place-items: center;
  font-size: 1.625rem;
  font-weight: 700;
  box-shadow: 0 0.125rem 0.5rem rgba(238, 80, 47, 0.15);
}

.user h1 {
  margin: 0;
  font-size: 1.125rem;
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
