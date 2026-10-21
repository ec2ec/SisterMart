import { api } from './client'
import type { Member } from '../composables/useSession'

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

export type CartTrustTag = {
  id: number
  code: string
  sort: number
  name: Localized
  detail: Localized
}

export function fetchCartTrustTags() {
  return api<{ items: CartTrustTag[] }>('/api/v1/cart/trust-tags')
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
  points_mall?: boolean
  points_cost?: number
}

export type Order = {
  id: number
  order_no: string
  status: string
  pay_amount: number
  goods_amount: number
  shipping_amount: number
  coupon_amount?: number
  /** 原價相對售價立減合計（compare_at − unit_price）× qty */
  promo_discount?: number
  points_amount?: number
  balance_amount?: number
  pay_channel?: string | null
  paid_at?: string | null
  shipped_at?: string | null
  completed_at?: string | null
  aftersale_touched?: boolean
  aftersale_eligible?: boolean
  aftersale_expired?: boolean
  aftersale_window_days?: number
  aftersale_deadline_at?: string | null
  cs_whatsapp?: string
  needs_review?: boolean
  points_awarded?: number
  expire_at?: string | null
  created_at: string
  address?: Address | Record<string, string>
  remark?: string
  cover?: string
  title?: string
  item_count?: number
  items?: Array<{
    id: number
    product_code: string
    sku_code: string
    name: Localized
    spec: Localized
    image: string
    unit_price: number
    /** 下單時原價/劃線價快照 */
    compare_at?: number
    qty: number
    refunded_qty?: number
    line_amount: number
    promo_discount?: number
    review_id?: number | null
    can_review?: boolean
    can_follow?: boolean
    reviewed?: boolean
  }>
}

export type ProductReview = {
  id: number
  order_item_id: number
  product_code: string
  stars: number
  author: string
  body: string
  images: string[]
  follow?: string | null
  follow_at?: string | null
  reply?: string | null
  replied_at?: string | null
  created_at: string
  can_follow?: boolean
  moderation_status?: string
  is_mine?: boolean
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

export type OrderCounts = {
  pending_payment: number
  pending_shipment: number
  pending_receipt: number
  to_review: number
  aftersales: number
}

export function fetchOrderCounts() {
  return api<OrderCounts>('/api/v1/orders/counts', {}, 'member')
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

export type Shipment = {
  order_id: number
  order_no: string
  order_status: string
  company: string
  tracking_no: string
  shipped_at: string
  nodes: Array<{ id: number; occurred_at: string; note: string }>
}

export type Aftersale = {
  id: number
  aftersale_no: string
  order_id: number
  order_item_id: number
  type: string
  reason: string
  description?: string
  evidence?: string[]
  amount: number
  qty: number
  refund_to: string
  status: string
  admin_note: string
  created_at: string
  order_no?: string
  item?: {
    name: { 'zh-HK': string; en: string }
    spec: { 'zh-HK': string; en: string }
    image: string
  }
}

export function fetchShipment(orderId: number | string) {
  return api<Shipment>(`/api/v1/orders/${orderId}/shipment`, {}, 'member')
}

export function confirmReceipt(orderId: number) {
  return api<Order>(`/api/v1/orders/${orderId}/confirm-receipt`, { method: 'POST', body: '{}' }, 'member')
}

export function fetchAftersales() {
  return api<{ items: Aftersale[] }>('/api/v1/aftersales', {}, 'member')
}

export function fetchAftersale(id: number | string) {
  return api<Aftersale>(`/api/v1/aftersales/${id}`, {}, 'member')
}

export function applyAftersale(body: Record<string, unknown>) {
  return api<Aftersale>('/api/v1/aftersales', { method: 'POST', body: JSON.stringify(body) }, 'member')
}

export function cancelAftersale(id: number) {
  return api<Aftersale>(`/api/v1/aftersales/${id}/cancel`, { method: 'POST', body: '{}' }, 'member')
}

export function uploadAftersaleEvidence(file: File) {
  const body = new FormData()
  body.append('file', file)
  return api<{ url: string }>('/api/v1/uploads/aftersale-evidence', { method: 'POST', body }, 'member')
}

export function uploadReviewImage(file: File) {
  const body = new FormData()
  body.append('file', file)
  return api<{ url: string }>('/api/v1/uploads/review-image', { method: 'POST', body }, 'member')
}

export function uploadAvatar(file: Blob | File) {
  const body = new FormData()
  // 明確帶 image/jpeg，避免部分瀏覽器 Blob 無 Content-Type 被主機拒收
  const part =
    file instanceof File
      ? file
      : new File([file], 'avatar.jpg', { type: file.type || 'image/jpeg' })
  body.append('file', part, 'avatar.jpg')
  return api<{ url: string; member: Member }>('/api/v1/uploads/avatar', { method: 'POST', body }, 'member')
}

export function createReview(body: { order_item_id: number; stars: number; body: string; images?: string[] }) {
  return api<ProductReview>('/api/v1/reviews', { method: 'POST', body: JSON.stringify(body) }, 'member')
}

export function followReview(id: number, body: string) {
  return api<ProductReview>(`/api/v1/reviews/${id}/follow`, { method: 'POST', body: JSON.stringify({ body }) }, 'member')
}
