<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchOrders, type Order } from '../api/trade'
import { isLoggedIn } from '../composables/useSession'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const items = ref<Order[]>([])
const loading = ref(false)

const tabs = [
  { name: 'all', status: '', label: 'orders.tabAll' },
  { name: 'pending_payment', status: 'pending_payment', label: 'orders.pendingPayment' },
  { name: 'pending_shipment', status: 'pending_shipment', label: 'orders.pendingShipment' },
  { name: 'pending_receipt', status: 'pending_receipt', label: 'orders.pendingReceipt' },
  { name: 'to_review', status: 'to_review', label: 'orders.toReview' },
  { name: 'aftersale', status: 'aftersale', label: 'orders.tabAftersale' },
] as const

const tab = ref('all')

const statusLabel: Record<string, string> = {
  pending_payment: 'orders.pendingPayment',
  pending_shipment: 'orders.pendingShipment',
  pending_receipt: 'orders.pendingReceipt',
  completed: 'orders.completed',
  cancelled: 'orders.cancelled',
  refunded: 'orders.refunded',
}

async function load(status: string) {
  if (!isLoggedIn()) {
    router.replace({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  loading.value = true
  try {
    const data = await fetchOrders(status || undefined)
    items.value = data.items
  } finally {
    loading.value = false
  }
}

function onTabChange(name: string | number) {
  const next = String(name)
  tab.value = next
  const query = next === 'all' ? {} : { tab: next }
  router.replace({ name: 'orders', query })
}

watch(
  () => String(route.query.tab || 'all'),
  (name) => {
    const valid = tabs.some((x) => x.name === name) ? name : 'all'
    tab.value = valid
    const current = tabs.find((x) => x.name === valid) || tabs[0]
    load(current.status)
  },
  { immediate: true },
)
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('pages.orders')" />
    <van-tabs :active="tab" shrink sticky @change="onTabChange">
      <van-tab v-for="x in tabs" :key="x.name" :name="x.name" :title="t(x.label)" />
    </van-tabs>
    <p v-if="!loading && !items.length" class="empty">{{ t('orders.empty') }}</p>
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
  color: var(--sm-primary);
  font-size: 0.75rem;
  flex-shrink: 0;
}
.title {
  margin: 0.25rem 0;
  font-size: 0.8125rem;
  color: #666;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.bottom {
  display: flex;
  justify-content: space-between;
  font-size: 0.8125rem;
}
.count {
  color: #999;
}
</style>
