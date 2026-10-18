/** 訂單狀態中文回顯 */
export const ORDER_STATUS_LABEL: Record<string, string> = {
  pending_payment: '待付款',
  pending_shipment: '待發貨',
  pending_receipt: '待收貨',
  completed: '已完成',
  cancelled: '已取消',
}

export function orderStatusLabel(status: string | null | undefined): string {
  if (!status) return '—'
  return ORDER_STATUS_LABEL[status] || status
}

export const ORDER_STATUS_FILTER_OPTIONS = [
  { label: '全部', value: '' },
  { label: '待付款', value: 'pending_payment' },
  { label: '待發貨', value: 'pending_shipment' },
  { label: '待收貨', value: 'pending_receipt' },
  { label: '已完成', value: 'completed' },
  { label: '已取消', value: 'cancelled' },
]
