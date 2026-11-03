import { showError } from './feedback'

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

export async function api<T>(path: string, init: RequestInit = {}, auth = false, silent = false): Promise<T> {
  const headers = new Headers(init.headers)
  if (init.body && !(init.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json')
  }
  if (auth) {
    const token = localStorage.getItem('sm_admin_token')
    if (token) headers.set('Authorization', `Bearer ${token}`)
  }
  let res: Response
  try {
    res = await fetch(`${base}${path}`, { ...init, headers })
  } catch (e) {
    const err = new ApiError('NETWORK', e instanceof Error ? e.message : '網絡異常，請稍後再試', 0)
    if (!silent) showError(err, '網絡異常，請稍後再試', '網絡錯誤')
    throw err
  }
  const body = (await res.json().catch(() => ({}))) as Envelope<T>
  if (!res.ok || body.code !== 0) {
    const err = new ApiError(body.error?.name || 'HTTP', body.message || '請求失敗', body.code ?? 0)
    if (!silent) showError(err, '請求失敗', '接口錯誤')
    throw err
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
  format?: string
  scene?: string
  preset: string
}

/** 後台圖片上傳。壓縮參數只看該場景的當前配置，請求不能覆蓋。 */
export async function uploadImage(file: File, options: { scene: string }): Promise<ImageUploadResult> {
  const body = new FormData()
  body.append('file', file)
  body.append('scene', options.scene)
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
