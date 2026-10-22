const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'

/** 站點根（去掉 /api/index.php） */
export function assetOrigin(): string {
  return apiBase.replace(/\/api\/index\.php\/?$/i, '').replace(/\/api\/?$/i, '')
}

/**
 * 預覽上傳圖：/storage/... 走媒體代理（query），避免 nginx 對多段 PATH_INFO 404。
 * 使用 /files（非 /media）：部分共享主機對 media 路徑異常。
 */
export function assetUrl(u: string): string {
  if (!u) return ''
  if (u.startsWith('data:')) return u
  // 已是 files 代理（相對或絕對）
  if (/\/api\/v1\/files\?path=/i.test(u)) {
    if (/^https?:\/\//i.test(u)) return u
    return `${apiBase.replace(/\/$/, '')}${u.startsWith('/') ? u : `/${u}`}`
  }
  let path = u
  if (/^https?:\/\//i.test(u)) {
    try {
      const parsed = new URL(u)
      path = parsed.pathname + parsed.search
    } catch {
      return u
    }
  }
  path = path.startsWith('/') ? path : `/${path}`
  if (path.startsWith('/storage/')) {
    const rel = path.slice('/storage/'.length)
    return `${apiBase.replace(/\/$/, '')}/api/v1/files?path=${encodeURIComponent(rel)}`
  }
  const origin = assetOrigin()
  return `${origin}${path}`
}
