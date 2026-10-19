const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'

/** 靜態資源根（去掉 /api/index.php） */
export function assetOrigin(): string {
  return apiBase.replace(/\/api\/index\.php\/?$/i, '').replace(/\/api\/?$/i, '')
}

/** 後台預覽用：相對路徑補全域名 */
export function assetUrl(u: string): string {
  if (!u) return ''
  if (/^https?:\/\//i.test(u) || u.startsWith('data:')) return u
  const origin = assetOrigin()
  return `${origin}${u.startsWith('/') ? u : `/${u}`}`
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
