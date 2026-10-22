const base = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'

type Envelope<T> = {
  code: number
  message: string
  data: T
  error?: {
    name?: string
    details?: Record<string, unknown>
  }
}

export class ApiError extends Error {
  code: string
  businessCode: number
  constructor(code: string, message: string, businessCode = 0) {
    super(message)
    this.code = code
    this.businessCode = businessCode
  }
}

export async function api<T>(path: string, init: RequestInit = {}, auth = false): Promise<T> {
  const headers = new Headers(init.headers)
  if (init.body && !(init.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json')
  }
  if (auth) {
    const token = localStorage.getItem('sm_admin_token')
    if (token) headers.set('Authorization', `Bearer ${token}`)
  }
  const res = await fetch(`${base}${path}`, { ...init, headers })
  const body = (await res.json().catch(() => ({}))) as Envelope<T>
  if (!res.ok || body.code !== 0) {
    throw new ApiError(body.error?.name || 'HTTP', body.message || '請求失敗', body.code ?? 0)
  }
  return body.data
}

export type ImageUploadResult = {
  url: string
  width: number
  height: number
  bytes: number
  quality: number
  max_edge: number
  preset: string
}

/** 後台通用圖片上傳（服務端 ImageUploadService 等比壓縮後落地） */
export async function uploadImage(
  file: File,
  options: {
    preset?: 'admin' | 'banner' | 'product' | 'review' | 'aftersale'
    max_edge?: number
    max_bytes?: number
    max_upload_bytes?: number
    quality?: number
    min_quality?: number
    bucket?: string
  } = {},
): Promise<ImageUploadResult> {
  const body = new FormData()
  body.append('file', file)
  for (const [key, value] of Object.entries(options)) {
    if (value !== undefined && value !== null && value !== '') {
      body.append(key, String(value))
    }
  }
  return api<ImageUploadResult>('/api/v1/admin/uploads/image', { method: 'POST', body }, true)
}

export type AdminUser = {
  id: number
  email: string
  name: string
  role: string
  permissions?: string[]
}

export type TabItem = {
  slot: 'home' | 'category' | 'cart' | 'account'
  label_zh: string
  label_en: string
  icon: string
  icon_active: string
  icon_bg: string
  icon_bg_active: string
  text_color: string
  text_color_active: string
  sort: number
}

export type TabbarConfig = {
  bar_bg: string
  items: TabItem[]
}
