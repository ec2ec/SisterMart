<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { cancelOrder, confirmReceipt, fetchOrder, type Order } from '../api/trade'
import { useLocaleText } from '../composables/useLocaleText'
import { formatHkAddressLine, type HkLocale } from '../data/hkDistricts'

const { t, locale } = useI18n()
const { text } = useLocaleText()
const uiLocale = computed(() => (locale.value === 'en' ? 'en' : 'zh-HK') as HkLocale)
const route = useRoute()
const router = useRouter()
const order = ref<Order | null>(null)

async function load() {
  order.value = await fetchOrder(String(route.params.id))
}

onMounted(load)

const statusMap: Record<string, string> = {
  pending_payment: 'orders.pendingPayment',
  pending_shipment: 'orders.pendingShipment',
  pending_receipt: 'orders.pendingReceipt',
  completed: 'orders.completed',
  cancelled: 'orders.cancelled',
  refunded: 'orders.refunded',
}

const promoDiscount = computed(() => {
  if (!order.value) return 0
  if (typeof order.value.promo_discount === 'number') return order.value.promo_discount
  return (order.value.items || []).reduce((sum, item) => {
    const compare = Number(item.compare_at ?? item.unit_price)
    const unit = Number(item.unit_price)
    return sum + Math.max(0, (compare - unit) * item.qty)
  }, 0)
})

const couponAmount = computed(() => Number(order.value?.coupon_amount ?? 0))

function itemCompareAt(item: NonNullable<Order['items']>[number]) {
  const compare = Number(item.compare_at ?? 0)
  return compare > item.unit_price ? compare : 0
}

async function onCancel() {
  if (!order.value) return
  await cancelOrder(order.value.id)
  showToast(t('orders.cancelled'))
  await load()
}

async function onConfirm() {
  if (!order.value) return
  await confirmReceipt(order.value.id)
  showToast(t('fulfill.confirmed'))
  await load()
}
</script>

<template>
  <div class="page" v-if="order">
    <SubPageNav :title="t('orders.detail')" />
    <section class="card">
      <p>{{ order.order_no }}</p>
      <h2>{{ t(statusMap[order.status] || order.status) }}</h2>
      <p v-if="order.address">
        {{ (order.address as any).receiver }} {{ (order.address as any).phone }}<br />
        {{
          formatHkAddressLine(
            String((order.address as any).region || ''),
            String((order.address as any).district || ''),
            String((order.address as any).detail || ''),
            uiLocale,
          )
        }}
      </p>
    </section>
    <section v-if="order.remark" class="card remark">
      <h3>{{ t('checkout.remark') }}</h3>
      <p>{{ order.remark }}</p>
    </section>
    <section class="card">
      <div v-for="(item, idx) in order.items || []" :key="idx" class="line">
        <img :src="item.image" alt="" />
        <div class="meta">
          <h3>{{ text(item.name) }}</h3>
          <p>{{ text(item.spec) }} × {{ item.qty }}</p>
          <div class="prices">
            <strong class="sale">HK$ {{ item.unit_price.toFixed(2) }}</strong>
            <s v-if="itemCompareAt(item)" class="origin">HK$ {{ itemCompareAt(item).toFixed(2) }}</s>
          </div>
          <div v-if="item.can_review || item.can_follow" class="item-actions">
            <van-button
              v-if="item.can_review"
              size="mini"
              plain
              round
              type="primary"
              @click.stop="
                router.push({ name: 'order-review', params: { id: order.id }, query: { item: String(item.id) } })
              "
            >
              {{ t('review.go') }}
            </van-button>
            <van-button
              v-else-if="item.can_follow"
              size="mini"
              plain
              round
              @click.stop="
                router.push({ name: 'order-review', params: { id: order.id }, query: { item: String(item.id) } })
              "
            >
              {{ t('review.goFollow') }}
            </van-button>
          </div>
        </div>
      </div>
    </section>
    <section class="card sum">
      <p>
        <span>{{ t('checkout.goods') }}</span>
        <em>HK$ {{ order.goods_amount.toFixed(2) }}</em>
      </p>
      <p>
        <span>{{ t('checkout.shipping') }}</span>
        <em>{{ order.shipping_amount <= 0 ? t('checkout.freeShip') : `HK$ ${order.shipping_amount.toFixed(2)}` }}</em>
      </p>
      <p v-if="promoDiscount > 0" class="discount">
        <span>{{ t('orders.promoDiscount') }}</span>
        <em>-{{ promoDiscount.toFixed(2) }}</em>
      </p>
      <p v-if="couponAmount > 0" class="discount">
        <span>{{ t('checkout.coupon') }}</span>
        <em>-{{ couponAmount.toFixed(2) }}</em>
      </p>
      <p v-if="(order.points_amount || 0) > 0" class="discount">
        <span>{{ t('checkout.pointsLabel') }}</span>
        <em>-HK$ {{ Number(order.points_amount).toFixed(2) }}</em>
      </p>
      <p v-if="(order.balance_amount || 0) > 0" class="discount">
        <span>{{ t('checkout.balanceLabel') }}</span>
        <em>-HK$ {{ Number(order.balance_amount).toFixed(2) }}</em>
      </p>
      <p class="pay">
        <span>{{ t('checkout.pay') }}</span>
        <strong>HK$ {{ order.pay_amount.toFixed(2) }}</strong>
      </p>
    </section>
    <div class="actions">
      <van-button v-if="order.status === 'pending_payment'" plain round @click="onCancel">{{ t('orders.cancel') }}</van-button>
      <van-button
        v-if="order.status === 'pending_payment'"
        type="primary"
        color="var(--sm-primary)"
        round
        @click="router.push({ name: 'payment', params: { id: order.id } })"
      >
        {{ t('orders.payNow') }}
      </van-button>
      <van-button
        v-if="order.status === 'pending_receipt' || order.shipped_at"
        plain
        round
        @click="router.push({ name: 'logistics', params: { id: order.id } })"
      >
        {{ t('fulfill.logistics') }}
      </van-button>
      <van-button v-if="order.status === 'pending_receipt'" type="primary" color="var(--sm-primary)" round @click="onConfirm">
        {{ t('fulfill.confirm') }}
      </van-button>
      <van-button
        v-if="order.status === 'pending_shipment' || order.status === 'pending_receipt' || order.status === 'completed'"
        plain
        round
        @click="router.push({ name: 'aftersale-apply', params: { id: order.id } })"
      >
        {{ t('fulfill.apply') }}
      </van-button>
      <van-button
        v-if="order.needs_review"
        type="primary"
        color="var(--sm-primary)"
        round
        @click="
          router.push({
            name: 'order-review',
            params: { id: order.id },
            query: { item: String((order.items || []).find((i) => i.can_review)?.id || '') },
          })
        "
      >
        {{ t('review.go') }}
      </van-button>
    </div>
  </div>
</template>

<style scoped>
.card {
  margin: 0.75rem;
  padding: 0.875rem;
  background: #fff;
  border-radius: 0.75rem;
}
.remark h3 {
  margin: 0 0 0.35rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #333;
}
.remark p {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.5;
  color: #555;
  white-space: pre-wrap;
  word-break: break-word;
}
.line {
  display: grid;
  grid-template-columns: 4rem 1fr;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}
.line:last-child {
  margin-bottom: 0;
}
.line img {
  width: 4rem;
  height: 4rem;
  object-fit: cover;
  border-radius: 0.5rem;
}
.item-actions {
  margin-top: 0.35rem;
}
.meta h3 {
  margin: 0 0 0.25rem;
  font-size: 0.95rem;
  font-weight: 600;
}
.meta p {
  margin: 0;
  color: #888;
  font-size: 0.8125rem;
}
.prices {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  margin-top: 0.35rem;
}
.sale {
  color: #1a1a1a;
  font-size: 0.95rem;
}
.origin {
  color: #999;
  font-size: 0.8125rem;
  font-weight: 400;
}
.sum p {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin: 0.4rem 0;
  font-size: 0.9rem;
}
.sum em {
  font-style: normal;
}
.sum .discount {
  color: #e11d48;
}
.sum .discount em {
  color: #e11d48;
  font-weight: 600;
}
.sum .pay {
  margin-top: 0.65rem;
  padding-top: 0.65rem;
  border-top: 1px solid #f0ebe4;
}
.sum .pay strong {
  font-size: 1.05rem;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  padding: 1rem;
}
.actions .van-button {
  flex: 1 1 calc(50% - 0.5rem);
}
</style>
