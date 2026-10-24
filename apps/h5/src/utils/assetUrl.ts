const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'

/** 站點根（去掉 /api/index.php） */
export function assetOrigin(): string {
  return apiBase.replace(/\/api\/index\.php\/?$/i, '').replace(/\/api\/?$/i, '')
}

/**
 * 預覽上傳圖：/storage/... 與殘留的 files 代理 URL 一律重建為乾淨代理。
 * 使用 /files（非 /media）：部分共享主機對 media 路徑異常。
 * 避免把已含 /api/index.php 的路徑再拼到 VITE_API_BASE 造成雙重前綴 404。
 */
export function assetUrl(u: string): string {
  if (!u) return ''
  if (u.startsWith('data:')) return u

  // 任意位置的 files 代理：抽出 path 後用 apiBase 重建（不雙重拼接）
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
  // 偶發存成 /api/index.php/storage/...
  path = path.replace(/^\/api\/index\.php(?=\/)/i, '')

  if (path.startsWith('/storage/')) {
    const rel = path.slice('/storage/'.length)
    return `${apiBase.replace(/\/$/, '')}/api/v1/files?path=${encodeURIComponent(rel)}`
  }
  const origin = assetOrigin()
  return `${origin}${path}`
}
