/** 規格／選項代碼：優先保留已有；否則從名稱抽拉丁字符，失敗則用 fallback */
export function ensureSpecCode(raw: string, nameZh: string, nameEn: string, fallback: string): string {
  const existing = normalizeCode(raw)
  if (existing) return existing
  const fromEn = normalizeCode(nameEn)
  if (fromEn) return fromEn
  const fromZh = normalizeCode(nameZh)
  if (fromZh) return fromZh
  return normalizeCode(fallback) || 'x'
}

export function normalizeCode(input: string): string {
  return String(input || '')
    .trim()
    .toLowerCase()
    .replace(/[\s　]+/g, '_')
    .replace(/[^a-z0-9_\-]+/g, '')
    .replace(/^[^a-z]+/, '')
    .slice(0, 48)
}

export function refsKey(refs: string[]): string {
  return [...refs].map((r) => r.trim()).filter(Boolean).sort().join('|')
}
