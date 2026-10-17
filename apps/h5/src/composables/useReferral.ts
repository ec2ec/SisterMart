const REF_KEY = 'sm_ref'

/** Accept Vue Router LocationQueryValue (string | null) and arrays thereof. */
export function captureRefFromQuery(
  ref?: string | null | Array<string | null> | undefined,
) {
  const value = Array.isArray(ref) ? ref[0] : ref
  if (value && typeof value === 'string' && value.trim()) {
    localStorage.setItem(REF_KEY, value.trim())
  }
}

export function storedRef() {
  return localStorage.getItem(REF_KEY) || ''
}

export function clearStoredRef() {
  localStorage.removeItem(REF_KEY)
}
