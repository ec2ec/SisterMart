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
  sold: string
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
  return api<{ items: ProductCard[]; sold_display_mode: string }>(`/api/v1/products${qs ? `?${qs}` : ''}`)
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

export async function fetchHomeRecommendations(tab: HomeRecTab, limit = 20) {
  const qs = new URLSearchParams({ tab, limit: String(limit) })
  return api<{
    tab: string
    source: string
    items: ProductCard[]
    sold_display_mode: string
  }>(`/api/v1/home/recommendations?${qs}`, {}, 'member')
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
