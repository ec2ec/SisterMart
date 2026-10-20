import { ref } from 'vue'
import { fetchOrders, fetchOrderCounts, type OrderCounts } from '../api/trade'
import { api } from '../api/client'
import { isLoggedIn } from './useSession'

const emptyCounts = (): OrderCounts => ({
  pending_payment: 0,
  pending_shipment: 0,
  pending_receipt: 0,
  to_review: 0,
  aftersales: 0,
})

/** 模組級快取：帳號訂單中心與 /orders Tab 共用 */
const orderCounts = ref<OrderCounts>(emptyCounts())

export function badgeText(n: number): string {
  if (n <= 0) return ''
  return n > 99 ? '99+' : String(n)
}

/**
 * VPS 若尚未部署 /orders/counts（會 nginx 404），改用已有列表接口推算角標。
 * 與訂單中心 Tab 篩選一致。
 */
async function fetchOrderCountsFallback(): Promise<OrderCounts> {
  const [pendingPayment, pendingShipment, pendingReceipt, toReview, aftersales] = await Promise.all([
    fetchOrders('pending_payment').catch(() => ({ items: [] as { id: number }[] })),
    fetchOrders('pending_shipment').catch(() => ({ items: [] as { id: number }[] })),
    fetchOrders('pending_receipt').catch(() => ({ items: [] as { id: number }[] })),
    fetchOrders('to_review').catch(() => ({ items: [] as { id: number }[] })),
    api<{ items: { id: number; status?: string }[] }>('/api/v1/aftersales', {}, 'member').catch(
      () => ({ items: [] as { id: number; status?: string }[] }),
    ),
  ])

  const openAftersale = aftersales.items.filter((x) =>
    ['pending_review', 'awaiting_return', 'pending_refund'].includes(String(x.status || '')),
  )

  return {
    pending_payment: pendingPayment.items.length,
    pending_shipment: pendingShipment.items.length,
    pending_receipt: pendingReceipt.items.length,
    to_review: toReview.items.length,
    aftersales: openAftersale.length || aftersales.items.length,
  }
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
      try {
        orderCounts.value = await fetchOrderCountsFallback()
      } catch {
        /* 角標失敗不擋頁面 */
      }
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
