import { ref } from 'vue'
import { fetchOrderCounts, type OrderCounts } from '../api/trade'
import { isLoggedIn } from './useSession'

const emptyCounts = (): OrderCounts => ({
  pending_payment: 0,
  pending_shipment: 0,
  pending_receipt: 0,
  to_review: 0,
  aftersales: 0,
})

/** 模組級快取：帳號訂單中心與 /orders Tab 共用同一份真實統計 */
const orderCounts = ref<OrderCounts>(emptyCounts())

export function badgeText(n: number): string {
  if (n <= 0) return ''
  return n > 99 ? '99+' : String(n)
}

export function useOrderCounts() {
  async function refreshOrderCounts() {
    if (!isLoggedIn()) {
      orderCounts.value = emptyCounts()
      return orderCounts.value
    }
    try {
      orderCounts.value = await fetchOrderCounts()
    } catch {
      /* 角標失敗不擋頁面 */
    }
    return orderCounts.value
  }

  function clearOrderCounts() {
    orderCounts.value = emptyCounts()
  }

  return {
    orderCounts,
    refreshOrderCounts,
    clearOrderCounts,
    badgeText,
  }
}
