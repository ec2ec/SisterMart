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
  status: number
  businessCode: number
  debugCode?: string

  constructor(code: string, message: string, status: number, businessCode = 0, debugCode?: string) {
    super(message)
    this.code = code
    this.status = status
    this.businessCode = businessCode
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
  const body = (await res.json().catch(() => ({}))) as Envelope<T & { debug_code?: string }>
  if (!res.ok || body.code !== 0) {
    const details = body.error?.details || {}
    const debug =
      typeof details.debug_code === 'string'
        ? details.debug_code
        : typeof body.data?.debug_code === 'string'
          ? body.data.debug_code
          : undefined
    throw new ApiError(body.error?.name || 'HTTP', body.message || '', res.status, body.code ?? 0, debug)
  }
  return body.data
}
