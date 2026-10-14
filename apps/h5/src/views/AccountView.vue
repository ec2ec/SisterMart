<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { showToast } from 'vant'
import { accountMock } from '../mock/data'
import { toggleLocale } from '../i18n'

const { t } = useI18n()
const u = accountMock

const orderItems = [
  { key: 'toPay', icon: 'credit-pay' },
  { key: 'toShip', icon: 'logistics', badge: u.toShipBadge },
  { key: 'toReceive', icon: 'send-gift-o' },
  { key: 'toReview', icon: 'orders-o' },
  { key: 'returns', icon: 'replay' },
] as const

const services = [
  { key: 'address', icon: 'location-o' },
  { key: 'wishlist', icon: 'star-o' },
  { key: 'couponCenter', icon: 'coupon-o' },
  { key: 'pointsMall', icon: 'shop-o' },
  { key: 'dailyCheckin', icon: 'calendar-o' },
  { key: 'flashSale', icon: 'clock-o' },
  { key: 'affiliate', icon: 'friends-o' },
  { key: 'support', icon: 'chat-o' },
] as const
</script>

<template>
  <div class="page page--soft">
    <section class="hero">
      <button class="settings" type="button" @click="toggleLocale">
        <van-icon name="setting-o" size="18" />
      </button>
      <div class="user">
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
      <button type="button">
        <strong>{{ t('account.member') }}</strong>
        <span>{{ t('account.memberSub') }}</span>
      </button>
      <button type="button">
        <strong>{{ t('account.rewards') }}</strong>
        <span>{{ t('account.rewardsSub') }}</span>
      </button>
    </div>

    <section class="card">
      <div class="card-head">
        <h2>{{ t('account.orders') }}</h2>
        <span>{{ t('account.more') }}</span>
      </div>
      <div class="orders">
        <button v-for="o in orderItems" :key="o.key" type="button">
          <van-badge :content="('badge' in o && o.badge) || ''" :show-zero="false">
            <van-icon :name="o.icon" size="22" />
          </van-badge>
          <span>{{ t(`account.${o.key}`) }}</span>
        </button>
      </div>
    </section>

    <section class="checkin">
      <div class="left">
        <van-icon name="calendar-o" color="var(--sm-primary)" size="20" />
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
      <button v-for="s in services" :key="s.key" type="button">
        <van-icon :name="s.icon" size="22" />
        <span>{{ t(`account.${s.key}`) }}</span>
      </button>
    </section>
  </div>
</template>

<style scoped>
.hero {
  position: relative;
  padding: 20px 16px 14px;
  background: linear-gradient(180deg, #ffe8df 0%, #fff 70%);
}

.settings {
  position: absolute;
  top: 14px;
  right: 14px;
  border: 0;
  background: transparent;
  color: #666;
}

.user {
  display: flex;
  gap: 12px;
  align-items: center;
}

.avatar {
  width: 58px;
  height: 58px;
  border-radius: 50%;
  background: #fff;
  color: var(--sm-primary);
  display: grid;
  place-items: center;
  font-size: 26px;
  font-weight: 700;
  box-shadow: 0 2px 8px rgba(238, 80, 47, 0.15);
}

.user h1 {
  margin: 0;
  font-size: 18px;
}

.phone,
.spent {
  margin: 2px 0 0;
  font-size: 12px;
  color: #888;
}

.stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  margin-top: 16px;
  text-align: center;
}

.stats strong {
  display: block;
  font-size: 18px;
}

.stats span {
  font-size: 12px;
  color: #888;
}

.quick {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  padding: 0 12px;
}

.quick button {
  border: 0;
  text-align: left;
  padding: 14px;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 1px 6px rgba(0, 0, 0, 0.04);
}

.quick strong {
  display: block;
  font-size: 14px;
}

.quick span {
  display: block;
  margin-top: 4px;
  font-size: 11px;
  color: #999;
}

.card {
  margin: 12px;
  padding: 12px;
  border-radius: 14px;
  background: #fff;
}

.card-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.card-head h2 {
  margin: 0;
  font-size: 15px;
}

.card-head span {
  font-size: 12px;
  color: #999;
}

.orders {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 4px;
}

.orders button,
.services button {
  border: 0;
  background: transparent;
  padding: 8px 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  color: #333;
}

.checkin {
  margin: 0 12px;
  padding: 10px 12px;
  border: 1px solid #ffc9b8;
  border-radius: 12px;
  background: #fff8f5;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.checkin .left {
  display: flex;
  gap: 10px;
  align-items: center;
}

.checkin .title {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
}

.checkin .sub {
  margin: 2px 0 0;
  font-size: 11px;
  color: #999;
}

.services {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 4px 0;
  margin-bottom: 20px;
}
</style>
