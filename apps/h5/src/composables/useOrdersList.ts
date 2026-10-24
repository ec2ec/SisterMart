import { computed, ref } from 'vue'
import { fetchOrders, type Order } from '../api/trade'

/** Tab name → API status query（空字串 = 全部） */
export type OrdersTabName =
  | 'all'
  | 'pending_payment'
  | 'pending_shipment'
  | 'pending_receipt'
  | 'to_review'
  | 'aftersale'

const TAB_STATUS: Record<OrdersTabName, string> = {
  all: '',
  pending_payment: 'pending_payment',
  pending_shipment: 'pending_shipment',
  pending_receipt: 'pending_receipt',
  to_review: 'to_review',
  aftersale: 'aftersale',
}

/** 模組級展示緩衝：切 Tab／短暫卸載不丟列表，避免白屏 */
const cache = ref<Partial<Record<OrdersTabName, Order[]>>>({})
const activeTab = ref<OrdersTabName>('all')
const loading = ref(false)
const booted = ref(false)

const inflight = new Map<OrdersTabName, Promise<void>>()

export function isOrdersTabName(name: string): name is OrdersTabName {
  return Object.prototype.hasOwnProperty.call(TAB_STATUS, name)
}

/**
 * 訂單列表 stale-while-revalidate（對齊 useHomeCatalog）：
 * - 模板綁定當前 tab 的 cache，請求期間不清空
 * - 同 tab 並發合併；完成後一次性寫入
 */
export function useOrdersList() {
  const items = computed(() => cache.value[activeTab.value] ?? [])

  /** 當前 tab 尚無任何成功資料且正在請求 → 首次占位 */
  const showInitialPlaceholder = computed(
    () => loading.value && !(activeTab.value in cache.value),
  )

  /** 請求結束且該 tab 為空 → 空態 */
  const showEmpty = computed(
    () => booted.value && !loading.value && items.value.length === 0 && activeTab.value in cache.value,
  )

  async function load(tab: OrdersTabName) {
    activeTab.value = tab
    const existing = inflight.get(tab)
    if (existing) {
      loading.value = true
      await existing
      return
    }

    loading.value = true
    const run = (async () => {
      try {
        const status = TAB_STATUS[tab]
        const data = await fetchOrders(status || undefined)
        cache.value = { ...cache.value, [tab]: data.items || [] }
      } catch {
        // 失敗時保留舊 cache；若從未成功過則寫入空陣列以結束「首次占位」
        if (!(tab in cache.value)) {
          cache.value = { ...cache.value, [tab]: [] }
        }
      } finally {
        inflight.delete(tab)
        if (activeTab.value === tab) {
          loading.value = inflight.has(tab)
        } else {
          loading.value = inflight.has(activeTab.value)
        }
        booted.value = true
      }
    })()
    inflight.set(tab, run)
    await run
  }

  /** 從詳情返回等：刷新當前 tab，不清空展示 */
  function refreshActive() {
    return load(activeTab.value)
  }

  function invalidateAll() {
    clearOrdersListCache()
  }

  return {
    activeTab,
    items,
    loading,
    showInitialPlaceholder,
    showEmpty,
    load,
    refreshActive,
    invalidateAll,
    tabStatus: TAB_STATUS,
  }
}

/** 退出登錄時清空訂單列表緩存 */
export function clearOrdersListCache() {
  cache.value = {}
  booted.value = false
  inflight.clear()
  loading.value = false
}
