<script setup lang="ts">
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import RecommendFeed from '../components/RecommendFeed.vue'
import SubPageNav from '../components/SubPageNav.vue'
import { badgeText, useOrderCounts } from '../composables/useOrderCounts'
import { isOrdersTabName, useOrdersList, type OrdersTabName } from '../composables/useOrdersList'
import { goLogin } from '../composables/useAuth'
import { isLoggedIn } from '../composables/useSession'

defineOptions({ name: 'OrdersView' })

const { t } = useI18n()
const router = useRouter()
const route = useRoute()
const { orderCounts, refreshOrderCounts } = useOrderCounts()
const { items, loading, showInitialPlaceholder, showEmpty, load, activeTab } = useOrdersList()

const tabs = [
  { name: 'all' as const, label: 'orders.tabAll', countKey: null },
  { name: 'pending_payment' as const, label: 'orders.pendingPayment', countKey: 'pending_payment' as const },
  { name: 'pending_shipment' as const, label: 'orders.pendingShipment', countKey: 'pending_shipment' as const },
  { name: 'pending_receipt' as const, label: 'orders.pendingReceipt', countKey: 'pending_receipt' as const },
  { name: 'to_review' as const, label: 'orders.toReview', countKey: 'to_review' as const },
  { name: 'completed' as const, label: 'orders.tabCompleted', countKey: null },
  { name: 'aftersale' as const, label: 'orders.tabAftersale', countKey: 'aftersales' as const },
]

const statusLabel: Record<string, string> = {
  pending_payment: 'orders.pendingPayment',
  pending_shipment: 'orders.pendingShipment',
  pending_receipt: 'orders.pendingReceipt',
  completed: 'orders.completed',
  cancelled: 'orders.cancelled',
  refunded: 'orders.refunded',
}

function tabBadge(x: (typeof tabs)[number]): string {
  if (!x.countKey || !isLoggedIn()) return ''
  return badgeText(orderCounts.value[x.countKey] || 0)
}

function resolveTab(name: string): OrdersTabName {
  return isOrdersTabName(name) ? name : 'all'
}

async function ensureLoaded(tab: OrdersTabName) {
  if (!isLoggedIn()) {
    void goLogin(router, route.fullPath, { replace: true })
    return
  }
  await load(tab)
  void refreshOrderCounts()
}

function onTabChange(name: string | number) {
  const next = resolveTab(String(name))
  activeTab.value = next
  const query = next === 'all' ? {} : { tab: next }
  // 同步 URL；App 對 orders 使用穩定 key，不會因此 remount
  router.replace({ name: 'orders', query })
  void ensureLoaded(next)
}

watch(
  () => String(route.query.tab || 'all'),
  (name) => {
    const valid = resolveTab(name)
    if (activeTab.value !== valid) {
      activeTab.value = valid
    }
    void ensureLoaded(valid)
  },
  { immediate: true },
)
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('pages.orders')" />
    <van-tabs :active="activeTab" shrink sticky @change="onTabChange">
      <van-tab
        v-for="x in tabs"
        :key="x.name"
        :name="x.name"
        :title="t(x.label)"
        :badge="tabBadge(x)"
        :show-zero-badge="false"
      />
    </van-tabs>

    <p v-if="showInitialPlaceholder" class="hint">{{ t('m7.loading') }}</p>
    <p v-else-if="showEmpty" class="empty">{{ t('orders.empty') }}</p>
    <ul v-else-if="items.length" class="list" :class="{ refreshing: loading }">
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

    <RecommendFeed v-if="isLoggedIn()" />
  </div>
</template>

<style scoped>
.hint,
.empty {
  text-align: center;
  color: #999;
  padding: 2rem;
  font-size: 0.875rem;
}
.list {
  list-style: none;
  margin: 0.75rem;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}
.list.refreshing {
  opacity: 0.92;
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
  line-height: 1.35;
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
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
