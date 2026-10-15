const REF_KEY = 'sm_ref'

export function captureRefFromQuery(ref?: string | string[] | null) {
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
