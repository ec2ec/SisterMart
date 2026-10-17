<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { cancelOrder, confirmReceipt, fetchOrder, type Order } from '../api/trade'
import { useLocaleText } from '../composables/useLocaleText'

const { t } = useI18n()
const { text } = useLocaleText()
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
        {{ (order.address as any).region }} {{ (order.address as any).district }} {{ (order.address as any).detail }}
      </p>
    </section>
    <section class="card">
      <div v-for="(item, idx) in order.items || []" :key="idx" class="line">
        <img :src="item.image" alt="" />
        <div>
          <h3>{{ text(item.name) }}</h3>
          <p>{{ text(item.spec) }} × {{ item.qty }}</p>
          <strong>HK$ {{ item.line_amount.toFixed(2) }}</strong>
        </div>
      </div>
    </section>
    <section class="card">
      <p>{{ t('checkout.goods') }} HK$ {{ order.goods_amount.toFixed(2) }}</p>
      <p>{{ t('checkout.shipping') }} HK$ {{ order.shipping_amount.toFixed(2) }}</p>
      <p><strong>{{ t('checkout.pay') }} HK$ {{ order.pay_amount.toFixed(2) }}</strong></p>
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
        v-if="order.status === 'pending_shipment' || order.status === 'pending_receipt'"
        plain
        round
        @click="router.push({ name: 'aftersale-apply', params: { id: order.id } })"
      >
        {{ t('fulfill.apply') }}
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
.line {
  display: grid;
  grid-template-columns: 4rem 1fr;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}
.line img {
  width: 4rem;
  height: 4rem;
  object-fit: cover;
  border-radius: 0.5rem;
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
