/** H5 展示版本；發佈構建可由 VITE_APP_VERSION 覆蓋。 */
function normalizeVersion(raw?: string): string {
  const v = (raw || '').trim()
  if (!v || v === 'dev') return ''
  // v1.30.6-account-version → v1.30.6
  const m = v.match(/^v?\d+\.\d+\.\d+/)
  if (m) {
    const base = m[0]
    return base.startsWith('v') ? base : `v${base}`
  }
  return v.startsWith('v') ? v : `v${v}`
}

export const APP_VERSION =
  normalizeVersion(import.meta.env.VITE_APP_VERSION as string | undefined) || 'v1.30.9'
