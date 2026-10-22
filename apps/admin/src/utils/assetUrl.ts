const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'

/** 靜態資源根（去掉 /api/index.php） */
export function assetOrigin(): string {
  return apiBase.replace(/\/api\/index\.php\/?$/i, '').replace(/\/api\/?$/i, '')
}

/**
 * 後台預覽用：/storage/... 與殘留 files 代理 URL 一律重建為乾淨代理。
 * 避免 /api/index.php/... 路徑再拼 VITE_API_BASE 造成雙重前綴。
 */
export function assetUrl(u: string): string {
  if (!u) return ''
  if (u.startsWith('data:')) return u
  const files = u.match(/\/api\/v1\/files\?path=([^&]*)/i)
  if (files) {
    return `${apiBase.replace(/\/$/, '')}/api/v1/files?path=${files[1]}`
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
  path = path.replace(/^\/api\/index\.php(?=\/)/i, '')
  if (path.startsWith('/storage/')) {
    const rel = path.slice('/storage/'.length)
    return `${apiBase.replace(/\/$/, '')}/api/v1/files?path=${encodeURIComponent(rel)}`
  }
  const origin = assetOrigin()
  return `${origin}${path}`
}

/** 落庫前：把本站絕對地址 / files 代理還原成 /storage/... 相對路徑 */
export function toStoredUrl(u: string): string {
  if (!u) return u
  const files = u.match(/\/api\/v1\/files\?path=([^&]+)/i)
  if (files) {
    return `/storage/${decodeURIComponent(files[1]).replace(/^\/+/, '')}`
  }
  const origin = assetOrigin()
  if (u.startsWith(origin)) return u.slice(origin.length) || '/'
  if (/^https?:\/\//i.test(u)) {
    try {
      const parsed = new URL(u)
      if (parsed.pathname.startsWith('/storage/')) return parsed.pathname
    } catch {
      /* keep */
    }
  }
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
