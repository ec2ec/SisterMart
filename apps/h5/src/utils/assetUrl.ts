const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'

/** 站點根（去掉 /api/index.php） */
export function assetOrigin(): string {
  return apiBase.replace(/\/api\/index\.php\/?$/i, '').replace(/\/api\/?$/i, '')
}

/**
 * 預覽上傳圖：/storage/... 走媒體代理（query），避免 nginx 對多段 PATH_INFO 404。
 */
export function assetUrl(u: string): string {
  if (!u) return ''
  if (/^https?:\/\//i.test(u) || u.startsWith('data:')) return u
  const path = u.startsWith('/') ? u : `/${u}`
  if (path.startsWith('/storage/')) {
    const rel = path.slice('/storage/'.length)
    return `${apiBase.replace(/\/$/, '')}/api/v1/media?path=${encodeURIComponent(rel)}`
  }
  const origin = assetOrigin()
  return `${origin}${path}`
}
