const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'

/** 靜態資源根（去掉 /api/index.php） */
export function assetOrigin(): string {
  return apiBase.replace(/\/api\/index\.php\/?$/i, '').replace(/\/api\/?$/i, '')
}

/**
 * 後台預覽用：/storage/... 走 API 媒體代理（共享主機常無靜態 /storage）。
 */
export function assetUrl(u: string): string {
  if (!u) return ''
  if (/^https?:\/\//i.test(u) || u.startsWith('data:')) return u
  const path = u.startsWith('/') ? u : `/${u}`
  if (path.startsWith('/storage/')) {
    const rel = path.slice('/storage/'.length)
    return `${apiBase.replace(/\/$/, '')}/api/v1/files?path=${encodeURIComponent(rel)}`
  }
  const origin = assetOrigin()
  return `${origin}${path}`
}

/** 落庫前：把本站絕對地址還原成 /storage/... 相對路徑 */
export function toStoredUrl(u: string): string {
  if (!u) return u
  const origin = assetOrigin()
  if (u.startsWith(origin)) return u.slice(origin.length) || '/'
  return u
}

export function toStoredHtml(html: string): string {
  if (!html) return ''
  const origin = assetOrigin()
  return html.split(origin).join('')
}

export function toPreviewHtml(html: string): string {
  if (!html) return ''
  return html.replace(/(src=["'])(\/[^"']+)(["'])/gi, (_, a, path, b) => `${a}${assetUrl(path)}${b}`)
}
