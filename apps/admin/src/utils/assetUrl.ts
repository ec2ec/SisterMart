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

/** 原圖 → 同目錄 `.thumb.` 路徑（管理列表預覽用；缺檔時 API 會回退原圖） */
export function toThumbStoredUrl(u: string): string {
  const stored = toStoredUrl(u)
  if (!stored.startsWith('/storage/')) return stored
  if (/\.thumb\.[a-z0-9]+$/i.test(stored)) return stored
  return stored.replace(/(\.[a-z0-9]+)$/i, '.thumb$1')
}

/** 管理後台列表／挑選器小圖；商品詳情富文本仍請用 assetUrl(原圖) */
export function thumbUrl(u: string): string {
  if (!u) return ''
  if (u.startsWith('data:')) return u
  return assetUrl(toThumbStoredUrl(u))
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
