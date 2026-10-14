const base = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'

export class ApiError extends Error {
  code: string
  status: number
  debugCode?: string

  constructor(code: string, message: string, status: number, debugCode?: string) {
    super(message)
    this.code = code
    this.status = status
    this.debugCode = debugCode
  }
}

export async function api<T>(path: string, init: RequestInit = {}, auth: 'member' | 'none' = 'none'): Promise<T> {
  const headers = new Headers(init.headers)
  if (init.body) headers.set('Content-Type', 'application/json')
  if (auth === 'member') {
    const token = localStorage.getItem('sm_token')
    if (token) headers.set('Authorization', `Bearer ${token}`)
  }
  let res: Response
  try {
    res = await fetch(`${base}${path}`, { ...init, headers })
  } catch {
    throw new ApiError('NETWORK', '', 0)
  }
  const data = (await res.json().catch(() => ({}))) as {
    ok?: boolean
    code?: string
    message?: string
    debug_code?: string
  }
  if (!res.ok || data.ok === false) {
    throw new ApiError(data.code || 'HTTP', data.message || '', res.status, data.debug_code)
  }
  return data as T
}
