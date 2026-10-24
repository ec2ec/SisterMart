<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { createOrder, fetchAddresses, quoteCheckout, type Address, type Quote } from '../api/trade'
import { refreshCartBadge } from '../composables/useCartBadge'
import { useLocaleText } from '../composables/useLocaleText'
import { isLoggedIn } from '../composables/useSession'
import { formatHkAddressLine, type HkLocale } from '../data/hkDistricts'

const { t, locale } = useI18n()
const { text } = useLocaleText()
const uiLocale = computed(() => (locale.value === 'en' ? 'en' : 'zh-HK') as HkLocale)
const selectedAddress = computed(() => addresses.value.find((a) => a.id === addressId.value) || null)
const route = useRoute()
const router = useRouter()

const addresses = ref<Address[]>([])
const addressId = ref<number | null>(null)
const quote = ref<Quote | null>(null)
const remark = ref('')
const usePoints = ref(false)
const useBalance = ref(false)
const couponGrantId = ref<number | null>(null)
const submitting = ref(false)

const buyNow = reactive({
  active: false,
  sku_id: 0,
  qty: 1,
})

const seckillBuy = reactive({
  active: false,
  seckill_item_id: 0,
  qty: 1,
})

const pointsMallBuy = reactive({
  active: false,
  points_mall_item_id: 0,
  qty: 1,
})

async function loadAddresses() {
  const data = await fetchAddresses()
  addresses.value = data.items
  const def = data.items.find((a) => a.is_default) || data.items[0]
  addressId.value = def?.id ?? null
}

async function refreshQuote() {
  const body: Record<string, unknown> = {
    use_points: usePoints.value,
    use_balance: useBalance.value,
    coupon_grant_id: couponGrantId.value,
  }
  if (pointsMallBuy.active) {
    body.points_mall_item_id = pointsMallBuy.points_mall_item_id
    body.qty = pointsMallBuy.qty
  } else if (seckillBuy.active) {
    body.seckill_item_id = seckillBuy.seckill_item_id
    body.qty = seckillBuy.qty
  } else if (buyNow.active) {
    body.buy_now = true
    body.sku_id = buyNow.sku_id
    body.qty = buyNow.qty
  }
  quote.value = await quoteCheckout(body)
}

onMounted(async () => {
  if (!isLoggedIn()) {
    router.replace({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  if (route.query.points_mall_item_id) {
    pointsMallBuy.active = true
    pointsMallBuy.points_mall_item_id = Number(route.query.points_mall_item_id)
    pointsMallBuy.qty = 1
  } else if (route.query.seckill_item_id) {
    seckillBuy.active = true
    seckillBuy.seckill_item_id = Number(route.query.seckill_item_id)
    seckillBuy.qty = 1
  } else if (route.query.sku_id) {
    buyNow.active = true
    buyNow.sku_id = Number(route.query.sku_id)
    buyNow.qty = Math.max(1, Number(route.query.qty || 1))
  }
  await loadAddresses()
  await refreshQuote()
})

watch([usePoints, useBalance, couponGrantId], () => {
  refreshQuote().catch(() => {})
})

async function submit() {
  if (!addressId.value) {
    showToast(t('checkout.needAddress'))
    router.push({ name: 'addresses', query: { redirect: route.fullPath } })
    return
  }
  submitting.value = true
  try {
    const body: Record<string, unknown> = {
      address_id: addressId.value,
      remark: remark.value,
      use_points: usePoints.value,
      use_balance: useBalance.value,
      coupon_grant_id: couponGrantId.value,
    }
    if (pointsMallBuy.active) {
      body.points_mall_item_id = pointsMallBuy.points_mall_item_id
      body.qty = pointsMallBuy.qty
    } else if (seckillBuy.active) {
      body.seckill_item_id = seckillBuy.seckill_item_id
      body.qty = seckillBuy.qty
    } else if (buyNow.active) {
      body.buy_now = true
      body.sku_id = buyNow.sku_id
      body.qty = buyNow.qty
    }
    const order = await createOrder(body)
    void refreshCartBadge()
    if (order.status === 'pending_shipment' || order.pay_amount <= 0) {
      router.replace({ name: 'pay-result', query: { order_id: String(order.id), zero: '1' } })
      return
    }
    router.replace({ name: 'payment', params: { id: String(order.id) } })
  } catch (error) {
    showToast(error instanceof Error ? error.message : t('checkout.fail'))
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="page checkout">
    <SubPageNav :title="t('checkout.title')" />
    <section class="card" @click="router.push({ name: 'addresses', query: { redirect: route.fullPath } })">
      <template v-if="selectedAddress">
        <strong>{{ selectedAddress.receiver }} {{ selectedAddress.phone }}</strong>
        <p>
          {{
            formatHkAddressLine(
              selectedAddress.region,
              selectedAddress.district,
              selectedAddress.detail,
              uiLocale,
            )
          }}
        </p>
      </template>
      <p v-else>{{ t('checkout.needAddress') }}</p>
    </section>

    <section v-if="quote" class="card">
      <div v-for="item in quote.items" :key="item.sku_id" class="line">
        <img :src="item.image" alt="" />
        <div>
          <h3>{{ text(item.name) }}</h3>
          <p>{{ text(item.spec) }} × {{ item.qty }}</p>
          <strong v-if="quote.points_mall">{{ quote.points_cost }} {{ t('account.points') }}</strong>
          <strong v-else>HK$ {{ (item.price * item.qty).toFixed(2) }}</strong>
        </div>
      </div>
    </section>

    <section v-if="quote && !quote.points_mall" class="card">
      <div class="row">
        <span>{{ t('checkout.coupon') }}</span>
        <select v-model="couponGrantId">
          <option :value="null">{{ t('checkout.noCoupon') }}</option>
          <option v-for="c in quote.coupons.filter((x) => x.usable)" :key="c.grant_id" :value="c.grant_id">
            {{ text(c.name) }} -HK${{ c.amount }}
          </option>
        </select>
      </div>
      <label class="row">
        <span>{{ t('checkout.points', { n: quote.points_available, cap: quote.points_cap_amount.toFixed(2) }) }}</span>
        <van-switch v-model="usePoints" size="1.125rem" />
      </label>
      <label class="row">
        <span>{{ t('checkout.balance', { n: quote.balance_available.toFixed(2) }) }}</span>
        <van-switch v-model="useBalance" size="1.125rem" />
      </label>
      <van-field v-model="remark" :label="t('checkout.remark')" :placeholder="t('checkout.remarkHint')" />
    </section>

    <section v-else-if="quote" class="card">
      <van-field v-model="remark" :label="t('checkout.remark')" :placeholder="t('checkout.remarkHint')" />
    </section>

    <section v-if="quote" class="card sum">
      <template v-if="quote.points_mall">
        <p><span>{{ t('m7.exchange') }}</span><em>{{ quote.points_used }} {{ t('account.points') }}</em></p>
      </template>
      <template v-else>
        <p><span>{{ t('checkout.goods') }}</span><em>HK$ {{ quote.goods_amount.toFixed(2) }}</em></p>
        <p class="discount">
          <span>{{ t('checkout.coupon') }}</span>
          <em>-HK$ {{ quote.coupon_amount.toFixed(2) }}</em>
        </p>
        <p class="discount">
          <span>{{ t('checkout.pointsLabel') }}</span>
          <em>-HK$ {{ quote.points_amount.toFixed(2) }}</em>
        </p>
        <p class="discount">
          <span>{{ t('checkout.balanceLabel') }}</span>
          <em>-HK$ {{ quote.balance_amount.toFixed(2) }}</em>
        </p>
      </template>
      <p>
        <span>{{ t('checkout.shipping') }}</span>
        <em>{{ quote.shipping_amount <= 0 ? t('checkout.freeShip') : `HK$ ${quote.shipping_amount.toFixed(2)}` }}</em>
      </p>
      <p class="pay"><span>{{ t('checkout.pay') }}</span><strong>HK$ {{ quote.pay_amount.toFixed(2) }}</strong></p>
      <p class="hint">{{ t('checkout.shipHint', { n: quote.free_threshold }) }}</p>
    </section>

    <div class="bar">
      <strong>HK$ {{ (quote?.pay_amount ?? 0).toFixed(2) }}</strong>
      <van-button type="primary" color="var(--sm-primary)" round :loading="submitting" @click="submit">
        {{ t('checkout.submit') }}
      </van-button>
    </div>
  </div>
</template>

<style scoped>
.checkout {
  padding-bottom: 5rem;
  background: #f6f3ee;
  min-height: 100vh;
}
.card {
  margin: 0.75rem;
  padding: 0.875rem;
  background: #fff;
  border-radius: 0.75rem;
}
.card p {
  margin: 0.25rem 0 0;
  color: #666;
  font-size: 0.8125rem;
}
.line {
  display: grid;
  grid-template-columns: 4rem minmax(0, 1fr);
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}
.line img {
  width: 4rem;
  height: 4rem;
  object-fit: cover;
  border-radius: 0.5rem;
}
.line > div {
  min-width: 0;
}
.line h3 {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.35;
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
}
.row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0;
  font-size: 0.8125rem;
}
.sum p {
  display: flex;
  justify-content: space-between;
  margin: 0.35rem 0;
  color: inherit;
  font-size: 0.8125rem;
}
.sum em {
  font-style: normal;
}
.sum .discount,
.sum .discount em {
  color: #e11d48;
}
.sum .discount em {
  font-weight: 600;
}
.pay strong {
  color: var(--sm-primary);
  font-size: 1.125rem;
}
.hint {
  color: #999 !important;
  font-size: 0.75rem !important;
}
.bar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem calc(0.75rem + env(safe-area-inset-bottom, 0rem));
  background: #fff;
  border-top: 0.0625rem solid #eee;
}
</style>
