import { api } from './client'

export type SeckillProduct = {
  id: string
  seckill_item_id: number
  product_id: string
  sku_id: number
  name: { 'zh-HK': string; en: string }
  image: string
  seckill_price: number
  origin_price: number
  stock: number
  sold?: number
}

export type SeckillSession = {
  id: string
  name: { 'zh-HK': string; en: string }
  status: 'live' | 'upcoming' | 'ended'
  starts_at: string
  ends_at: string
  products: SeckillProduct[]
  mock?: boolean
}

export function fetchSeckillSessions() {
  return api<{ items: SeckillSession[]; mock: boolean }>('/api/v1/seckill/sessions')
}

export function fetchSeckillSession(id: string) {
  return api<SeckillSession>(`/api/v1/seckill/sessions/${id}`)
}

export function fetchSeckillProduct(productId: string) {
  return api<{
    in_seckill?: boolean
    seckill_item_id?: number
    sku_id?: number
    seckill_price?: number
    origin_price?: number
    stock?: number
    session_id?: string
    session_status?: string
    ends_at?: string
    mock?: boolean
  }>(`/api/v1/seckill/products/${productId}`)
}

export function fetchPointsMall() {
  return api<{
    mock: boolean
    items: Array<{
      id: string
      points_mall_item_id: number
      product_id: string
      sku_id: number
      name: { 'zh-HK': string; en: string }
      image: string
      points_cost: number
      stock: number
      sold?: number
    }>
  }>('/api/v1/points-mall/products')
}

export function fetchPointsMallItem(id: string) {
  return api<{
    id: string
    points_mall_item_id: number
    product_id: string
    sku_id: number
    name: { 'zh-HK': string; en: string }
    image: string
    points_cost: number
    stock: number
    sold?: number
    detail: { 'zh-HK': string; en: string }
    mock: boolean
  }>(`/api/v1/points-mall/products/${id}`)
}

export function fetchCheckinCalendar() {
  return api<{
    mock: boolean
    year: number
    month: number
    streak: number
    reward_today: number
    signed_today: boolean
    days: Array<{ day: number; signed: boolean; today: boolean }>
  }>('/api/v1/checkins/calendar', {}, 'member')
}

export function postCheckin() {
  return api<{ mock: boolean; reward: number; streak: number; points: number }>(
    '/api/v1/checkins',
    { method: 'POST', body: '{}' },
    'member',
  )
}

export function fetchDistributionOverview() {
  return api<{
    mock: boolean
    promo_code: string
    available_commission: number
    pending_commission: number
    total_commission: number
    downline_count: number
    share_link: string
  }>('/api/v1/distribution/overview', {}, 'member')
}

export function fetchDownlines() {
  return api<{
    mock: boolean
    items: Array<{
      id: number
      nickname: string
      registered_at: string
      spent: number
      commission_earned: number
    }>
  }>('/api/v1/distribution/downlines', {}, 'member')
}

export function fetchCommissions() {
  return api<{ mock: boolean; items: Array<{ id: number; order_no: string; amount: number; status: string; created_at: string }> }>(
    '/api/v1/distribution/commissions',
    {},
    'member',
  )
}

export function fetchPromoOrders() {
  return api<{ mock: boolean; items: Array<{ order_no: string; title: string; commission: number; status: string }> }>(
    '/api/v1/distribution/orders',
    {},
    'member',
  )
}

export function fetchRanking(period: 'month' | 'total' = 'month') {
  return api<{ mock: boolean; period: string; items: Array<{ rank: number; nickname: string; commission: number }> }>(
    `/api/v1/distribution/ranking?period=${period}`,
    {},
    'member',
  )
}

export function fetchPoster(productId?: string) {
  const qs = productId ? `?product_id=${encodeURIComponent(productId)}` : ''
  return api<{
    mock: boolean
    product_id: string
    name: { 'zh-HK': string; en: string }
    price: number
    image: string
    qr_payload: string
    promo_code: string
  }>(`/api/v1/distribution/poster${qs}`, {}, 'member')
}

export function postWithdraw(body: { channel: 'balance' | 'fps'; amount: number; fps_name?: string; fps_id?: string }) {
  return api<{ mock: boolean; status: string; amount: number }>(
    '/api/v1/distribution/withdrawals',
    { method: 'POST', body: JSON.stringify(body) },
    'member',
  )
}

export type Msg = {
  id: string
  type: string
  title: { 'zh-HK': string; en: string }
  body: { 'zh-HK': string; en: string }
  created_at: string
  read?: boolean
  order_id?: number | null
  order_no?: string | null
}

export function fetchMessages() {
  return api<{ mock: boolean; order: Msg[]; system: Msg[]; unread: number }>('/api/v1/messages', {}, 'member')
}

export function fetchUnreadCount() {
  return api<{ mock: boolean; unread: number }>('/api/v1/messages/unread-count', {}, 'member')
}

export function markMessageRead(id: string) {
  return api<{ mock: boolean; unread: number }>(
    '/api/v1/messages/read',
    { method: 'POST', body: JSON.stringify({ id }) },
    'member',
  )
}

export function postShareView(ref: string, productId: string) {
  return api<{ mock: boolean; credited: boolean; reason?: string }>(
    '/api/v1/share-views',
    { method: 'POST', body: JSON.stringify({ ref, product_id: productId }) },
  )
}
