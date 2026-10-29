/** 訂單狀態中文回顯 */
export const ORDER_STATUS_LABEL: Record<string, string> = {
  pending_payment: '待付款',
  pending_shipment: '待發貨',
  pending_receipt: '待收貨',
  completed: '已完成',
  cancelled: '已取消',
  refunded: '已退款',
}

/** Naive n-tag type（列表用顏色區分狀態） */
export type OrderStatusTagType = 'default' | 'primary' | 'info' | 'success' | 'warning' | 'error'

export const ORDER_STATUS_TAG_TYPE: Record<string, OrderStatusTagType> = {
  pending_payment: 'warning',
  pending_shipment: 'info',
  pending_receipt: 'primary',
  completed: 'success',
  cancelled: 'default',
  refunded: 'error',
}

export function orderStatusLabel(status: string | null | undefined): string {
  if (!status) return '—'
  return ORDER_STATUS_LABEL[status] || status
}

export function orderStatusTagType(status: string | null | undefined): OrderStatusTagType {
  if (!status) return 'default'
  return ORDER_STATUS_TAG_TYPE[status] || 'default'
}

export const ORDER_STATUS_FILTER_OPTIONS = [
  { label: '全部', value: '' },
  { label: '待付款', value: 'pending_payment' },
  { label: '待發貨', value: 'pending_shipment' },
  { label: '待收貨', value: 'pending_receipt' },
  { label: '已完成', value: 'completed' },
  { label: '已取消', value: 'cancelled' },
  { label: '已退款', value: 'refunded' },
]
