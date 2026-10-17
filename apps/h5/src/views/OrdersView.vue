<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchOrders, type Order } from '../api/trade'
import { isLoggedIn } from '../composables/useSession'

const { t } = useI18n()
const router = useRouter()
const items = ref<Order[]>([])

const statusLabel: Record<string, string> = {
  pending_payment: 'orders.pendingPayment',
  pending_shipment: 'orders.pendingShipment',
  pending_receipt: 'orders.pendingReceipt',
  completed: 'orders.completed',
  cancelled: 'orders.cancelled',
  refunded: 'orders.refunded',
}

onMounted(async () => {
  if (!isLoggedIn()) {
    router.replace({ name: 'login', query: { redirect: '/orders' } })
    return
  }
  const data = await fetchOrders()
  items.value = data.items
})
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('pages.orders')" />
    <p v-if="!items.length" class="empty">{{ t('orders.empty') }}</p>
    <ul v-else class="list">
      <li
        v-for="o in items"
        :key="o.id"
        class="row"
        @click="router.push({ name: 'order-detail', params: { id: o.id } })"
      >
        <div class="thumb">
          <img v-if="o.cover" :src="o.cover" alt="" />
          <span v-else class="ph" />
        </div>
        <div class="meta">
          <div class="top">
            <strong>{{ o.order_no }}</strong>
            <em>{{ t(statusLabel[o.status] || o.status) }}</em>
          </div>
          <p class="title">{{ o.title || '—' }}</p>
          <div class="bottom">
            <span>HK$ {{ o.pay_amount.toFixed(2) }}</span>
            <span class="count">{{ t('orders.itemCount', { n: o.item_count || 1 }) }}</span>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.empty {
  text-align: center;
  color: #999;
  padding: 2rem;
}
.list {
  list-style: none;
  margin: 0.75rem;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}
.row {
  display: grid;
  grid-template-columns: 4.25rem 1fr;
  gap: 0.75rem;
  padding: 0.75rem;
  background: #fff;
  border-radius: 0.75rem;
  cursor: pointer;
}
.thumb {
  width: 4.25rem;
  height: 4.25rem;
  border-radius: 0.5rem;
  overflow: hidden;
  background: #f3f0ea;
}
.thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.ph {
  display: block;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #efeae2, #e2ddd4);
}
.meta {
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.top {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
  align-items: baseline;
}
.top strong {
  font-size: 0.8125rem;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.top em {
  font-style: normal;
  font-size: 0.75rem;
  color: #888;
  flex-shrink: 0;
}
.title {
  margin: 0.2rem 0;
  font-size: 0.8125rem;
  color: #555;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.bottom {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  font-size: 0.875rem;
  font-weight: 600;
}
.count {
  font-weight: 400;
  font-size: 0.75rem;
  color: #999;
}
</style>
