const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'

/** 站點根（去掉 /api/index.php） */
export function assetOrigin(): string {
  return apiBase.replace(/\/api\/index\.php\/?$/i, '').replace(/\/api\/?$/i, '')
}

/**
 * 預覽上傳圖：優先站點 /storage；若為相對路徑且走 API 入口，改走媒體代理，
 * 避免共享主機沒有 /storage 靜態別名時 404。
 */
export function assetUrl(u: string): string {
  if (!u) return ''
  if (/^https?:\/\//i.test(u) || u.startsWith('data:')) return u
  const path = u.startsWith('/') ? u : `/${u}`
  if (path.startsWith('/storage/')) {
    const rel = path.slice('/storage/'.length)
    // 經 ThinkPHP 入口提供，不依賴 nginx alias
    return `${apiBase.replace(/\/$/, '')}/api/v1/media/${rel}`
  }
  const origin = assetOrigin()
  return `${origin}${path}`
}
