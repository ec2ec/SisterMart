const base = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'

export class ApiError extends Error {
  code: string
  constructor(code: string, message: string) {
    super(message)
    this.code = code
  }
}

export async function api<T>(path: string, init: RequestInit = {}, auth = false): Promise<T> {
  const headers = new Headers(init.headers)
  if (init.body) headers.set('Content-Type', 'application/json')
  if (auth) {
    const token = localStorage.getItem('sm_admin_token')
    if (token) headers.set('Authorization', `Bearer ${token}`)
  }
  const res = await fetch(`${base}${path}`, { ...init, headers })
  const data = (await res.json().catch(() => ({}))) as { ok?: boolean; code?: string; message?: string }
  if (!res.ok || data.ok === false) {
    throw new ApiError(data.code || 'HTTP', data.message || '請求失敗')
  }
  return data as T
}

export type AdminUser = {
  id: number
  email: string
  name: string
  role: string
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
