import { api } from './client'

export type Localized = { 'zh-HK': string; en: string }

export type CategoryNode = {
  id: number
  code: string
  parent_id: number | null
  icon: string | null
  sort: number
  name: Localized
  children: CategoryNode[]
}

export type BadgeItem = {
  code: string
  color: string
  name: Localized
}

export type ProductAttr = {
  id: number
  code: string
  name: Localized
  values: Array<{ id: number; code: string; name: Localized }>
}

export type ProductCard = {
  id: string
  db_id: number
  code: string
  name: Localized
  price: number
  /** 原價（秒殺等場景劃線展示）；有值且大於 price 時顯示 */
  compare_at?: number
  sold: string
  /** 聚合評分 1.0–5.0；無評價時為 0 */
  rating?: number
  rating_count?: number
  image: string
  category: string
  category_id: number
  badges: string[]
  badge_items?: BadgeItem[]
  gallery?: string[]
  liked?: boolean
}

export type SpecGroup = {
  id: string
  code: string
  name: Localized
  options: Array<{ id: string; code: string; name: Localized; image?: string | null }>
}

export type Sku = {
  id: number
  code: string
  key: string
  price: number
  compare_at: number
  stock: number
  image?: string | null
  selected: Record<string, string>
  option_refs?: string[]
}

export type ProductDetail = ProductCard & {
  summary: Localized
  detail: Localized
  gallery: string[]
  media: Array<{ media_type: string; url: string; sort: number }>
  spec_groups: SpecGroup[]
  skus: Sku[]
  tags: Localized[]
  attrs?: ProductAttr[]
  reviews: Array<{
    id: number | string
    stars: number
    author: string
    body: string | Localized
    images?: string[]
    follow?: string | Localized | null
    reply?: string | null
    created_at?: string
    moderation_status?: string
    is_mine?: boolean
  }>
}

export type Article = {
  id: number
  code: string
  title: Localized
  body: Localized
}

export async function fetchCategories() {
  const data = await api<{ items: CategoryNode[] }>('/api/v1/categories')
  return data.items
}

export async function fetchProducts(params: Record<string, string> = {}) {
  const qs = new URLSearchParams(params).toString()
  return api<{
    items: ProductCard[]
    sold_display_mode: string
    total?: number
    offset?: number
    limit?: number
    has_more?: boolean
  }>(`/api/v1/products${qs ? `?${qs}` : ''}`)
}

export async function fetchProduct(id: string) {
  // 帶會員 token（若已登錄）以便本人看到待審評價
  return api<ProductDetail>(`/api/v1/products/${encodeURIComponent(id)}`, {}, 'member')
}

export async function fetchArticle(code: string) {
  return api<Article>(`/api/v1/articles/${encodeURIComponent(code)}`)
}

export type HomeBanner = {
  id: number
  image_url: string
  title: Localized
  link_type: string
  link_value: string
  sort_order: number
  status: number
}

export async function fetchHomeBanners() {
  const data = await api<{ items: HomeBanner[] }>('/api/v1/home/banners')
  return data.items
}

export type HomeMarquee = {
  text: Localized
  link_type: string
  link_value: string
  status: number
  enabled: boolean
} | null

export type HomeOps = {
  marquee: HomeMarquee
  footer_slogan: Localized
}

export async function fetchHomeOps() {
  return api<HomeOps>('/api/v1/home/ops')
}

export type HomeRecTab = 'hot' | 'new' | 'for_you'

export type HomeRecommendationsPage = {
  tab: string
  source: string
  items: ProductCard[]
  sold_display_mode: string
  total: number
  offset: number
  next_offset: number
  limit: number
  wrapped: boolean
}

export async function fetchHomeRecommendations(
  tab: HomeRecTab,
  limit = 16,
  offset = 0,
) {
  const qs = new URLSearchParams({
    tab,
    limit: String(limit),
    offset: String(Math.max(0, offset)),
  })
  return api<HomeRecommendationsPage>(`/api/v1/home/recommendations?${qs}`, {}, 'member')
}

export type HomeSection = {
  id: number
  code: string
  title: Localized
  view_all_type: string
  view_all_value: string
  sort_order: number
  status: number
  items: ProductCard[]
}

export async function fetchHomeSections() {
  const data = await api<{ items: HomeSection[] }>('/api/v1/home/sections')
  return data.items
}

export async function fetchHomeSectionProducts(code: string) {
  return api<{
    section: Omit<HomeSection, 'items'>
    items: ProductCard[]
    sold_display_mode: string
  }>(`/api/v1/home/sections/${encodeURIComponent(code)}/products`)
}

export function skuKey(selected: Record<string, string>, groups: SpecGroup[]) {
  return groups.map((group) => selected[group.id] || '').join('|')
}

/** 依當前選中規格找 SKU：優先比對 selected 映射（組代碼→選項代碼） */
export function findSku(
  skus: Sku[],
  selected: Record<string, string>,
  groups: SpecGroup[],
): Sku | null {
  if (!skus.length) return null
  const bySelected = skus.find((sku) => {
    const map = sku.selected || {}
    if (!groups.length) return false
    return groups.every((g) => String(map[g.id] || '') === String(selected[g.id] || ''))
  })
  if (bySelected) return bySelected
  const key = skuKey(selected, groups)
  return skus.find((sku) => sku.key === key || sku.code === key) || null
}
