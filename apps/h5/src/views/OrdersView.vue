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
  cancelled: 'orders.cancelled',
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
    <van-cell-group v-else inset>
      <van-cell
        v-for="o in items"
        :key="o.id"
        :title="o.order_no"
        :value="t(statusLabel[o.status] || o.status)"
        :label="`HK$ ${o.pay_amount.toFixed(2)} · ${o.title || ''}`"
        is-link
        @click="router.push({ name: 'order-detail', params: { id: o.id } })"
      />
    </van-cell-group>
  </div>
</template>

<style scoped>
.empty {
  text-align: center;
  color: #999;
  padding: 2rem;
}
</style>
