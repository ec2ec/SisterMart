/**
 * Build an absolute share URL for the hash-mode H5 router.
 * Deep paths like `/product/x` 404 on shared-host nginx (no try_files);
 * hash links (`/#/product/x`) always hit index.html.
 *
 * Accepts relative paths (`/product/x?ref=a`), hash paths (`/#/product/x?ref=a`),
 * or absolute URLs (rewrites non-hash deep links to hash form).
 */
export function buildShareUrl(pathOrUrl: string, origin = typeof location !== 'undefined' ? location.origin : ''): string {
  const base = origin || 'http://localhost'
  let input = (pathOrUrl || '').trim()
  if (!input) {
    return `${base}/#/`
  }

  if (/^https?:\/\//i.test(input)) {
    try {
      const u = new URL(input)
      if (u.hash.startsWith('#/')) {
        return u.toString()
      }
      const path = `${u.pathname || '/'}${u.search || ''}`
      return `${u.origin}/#${normalizeAppPath(path)}`
    } catch {
      /* treat as path below */
    }
  }

  if (input.startsWith('/#')) {
    input = input.slice(2)
  } else if (input.startsWith('#')) {
    input = input.slice(1)
  }

  return `${base}/#${normalizeAppPath(input)}`
}

function normalizeAppPath(path: string): string {
  if (!path || path === '/') return '/'
  return path.startsWith('/') ? path : `/${path}`
}
