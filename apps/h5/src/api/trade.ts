import { api } from './client'

export type Localized = { 'zh-HK': string; en: string }

export type CartItem = {
  id: number
  product_id: string
  sku_id: number
  sku_code: string
  name: Localized
  spec: Localized
  price: number
  qty: number
  stock: number
  image: string
  checked: boolean
  available: boolean
  invalid_reason: string
}

export type Address = {
  id: number
  receiver: string
  phone: string
  region: string
  district: string
  detail: string
  is_default: boolean
}

export type Quote = {
  items: CartItem[]
  goods_amount: number
  coupon_amount: number
  coupon_grant_id: number | null
  coupons: Array<{
    grant_id: number
    code: string
    name: Localized
    threshold: number
    amount: number
    usable: boolean
    reason: string
  }>
  points_amount: number
  points_used: number
  points_available: number
  points_cap_amount: number
  balance_amount: number
  balance_available: number
  shipping_amount: number
  free_threshold: number
  shipping_fee: number
  pay_amount: number
  zero_pay: boolean
}

export type Order = {
  id: number
  order_no: string
  status: string
  pay_amount: number
  goods_amount: number
  shipping_amount: number
  coupon_amount?: number
  points_amount?: number
  balance_amount?: number
  pay_channel?: string | null
  paid_at?: string | null
  expire_at?: string | null
  created_at: string
  address?: Address | Record<string, string>
  remark?: string
  items?: Array<{
    product_code: string
    sku_code: string
    name: Localized
    spec: Localized
    image: string
    unit_price: number
    qty: number
    line_amount: number
  }>
  cover?: string
  title?: string
  item_count?: number
}

export function fetchCart() {
  return api<{ items: CartItem[]; checked_total: number; coupon_preview: unknown }>('/api/v1/cart', {}, 'member')
}

export function addCartItem(sku_id: number, qty = 1) {
  return api('/api/v1/cart/items', { method: 'POST', body: JSON.stringify({ sku_id, qty }) }, 'member')
}

export function patchCartItem(id: number, body: { qty?: number; checked?: boolean }) {
  return api(`/api/v1/cart/items/${id}`, { method: 'PATCH', body: JSON.stringify(body) }, 'member')
}

export function deleteCartItems(ids: number[]) {
  return api('/api/v1/cart/delete-items', { method: 'POST', body: JSON.stringify({ ids }) }, 'member')
}

export function checkAllCart(checked: boolean) {
  return api('/api/v1/cart/check-all', { method: 'PUT', body: JSON.stringify({ checked }) }, 'member')
}

export function fetchAddresses() {
  return api<{ items: Address[]; regions: string[] }>('/api/v1/addresses', {}, 'member')
}

export function saveAddress(body: Partial<Address> & { receiver: string; phone: string; region: string; district: string; detail: string }, id?: number) {
  return id
    ? api<Address>(`/api/v1/addresses/${id}`, { method: 'PUT', body: JSON.stringify(body) }, 'member')
    : api<Address>('/api/v1/addresses', { method: 'POST', body: JSON.stringify(body) }, 'member')
}

export function deleteAddress(id: number) {
  return api(`/api/v1/addresses/${id}`, { method: 'DELETE' }, 'member')
}

export function quoteCheckout(body: Record<string, unknown>) {
  return api<Quote>('/api/v1/checkout/quote', { method: 'POST', body: JSON.stringify(body) }, 'member')
}

export function createOrder(body: Record<string, unknown>) {
  return api<Order>('/api/v1/orders', { method: 'POST', body: JSON.stringify(body) }, 'member')
}

export function fetchOrders(status?: string) {
  const qs = status ? `?status=${encodeURIComponent(status)}` : ''
  return api<{ items: Order[] }>(`/api/v1/orders${qs}`, {}, 'member')
}

export function fetchOrder(id: string | number) {
  return api<Order>(`/api/v1/orders/${id}`, {}, 'member')
}

export function cancelOrder(id: number) {
  return api<Order>(`/api/v1/orders/${id}/cancel`, { method: 'POST', body: '{}' }, 'member')
}

export function createPayment(orderId: number, channel: string) {
  return api<{ payment_id: number; channel: string; amount: number; mock_ref: string }>(
    `/api/v1/orders/${orderId}/payments`,
    { method: 'POST', body: JSON.stringify({ channel }) },
    'member',
  )
}

export function mockConfirmPayment(paymentId: number) {
  return api<Order>(`/api/v1/payments/${paymentId}/mock-confirm`, { method: 'POST', body: '{}' }, 'member')
}
