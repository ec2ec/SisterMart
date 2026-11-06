/** 規格／選項／SKU 代碼：優先保留已有；否則從名稱抽拉丁字符，失敗則用 fallback */
export function ensureSpecCode(raw: string, nameZh: string, nameEn: string, fallback: string): string {
  const existing = normalizeCode(raw)
  if (existing) return existing
  const fromEn = normalizeCode(nameEn)
  if (fromEn) return fromEn
  const fromZh = normalizeCode(nameZh)
  if (fromZh) return fromZh
  return normalizeCode(fallback) || 'x'
}

/**
 * 正規化代碼：小寫、空白變底線、去掉非法字符。
 * 保留開頭數字（如 10045S1 → 10045s1），只去掉開頭的符號。
 */
export function normalizeCode(input: string): string {
  return String(input || '')
    .trim()
    .toLowerCase()
    .replace(/[\s　]+/g, '_')
    .replace(/[^a-z0-9_\-]+/g, '')
    .replace(/^[^a-z0-9]+/, '')
    .slice(0, 48)
}

export function refsKey(refs: string[]): string {
  return [...refs].map((r) => r.trim()).filter(Boolean).sort().join('|')
}

/**
 * SKU 代碼默認：商品代碼 + 規格組合代碼（以 `_` 連接）。
 * 無商品代碼時僅用組合代碼；若組合已帶商品前綴則不重複拼接。
 */
export function defaultSkuCode(productCode: string, comboCode: string): string {
  const base = normalizeCode(productCode)
  const combo =
    normalizeCode(comboCode) ||
    String(comboCode || '')
      .trim()
      .toLowerCase()
      .slice(0, 64)
  if (!base) return (combo || 'sku').slice(0, 64)
  if (!combo) return base.slice(0, 64)
  if (combo === base || combo.startsWith(`${base}_`)) return combo.slice(0, 64)
  return `${base}_${combo}`.slice(0, 64)
}
