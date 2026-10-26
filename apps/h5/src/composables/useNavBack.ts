import { useRouter } from 'vue-router'
import { consumePostAuthBack, isAuthRouteName } from './useAuth'
import { TAB_ROUTE_NAMES } from './useTabShell'

/**
 * 子頁返回：跳過登錄棧殘留。
 * 1) 剛認證成功進入 → replace 到標記的 fallback（通常首頁／來源 Tab）
 * 2) 有歷史 → back（若落到認證頁由 router guard 再導走）
 * 3) 否則 → replace fallback
 */
export function useNavBack(fallback: string | (() => string) = '/') {
  const router = useRouter()

  function resolveFallback() {
    const fb = typeof fallback === 'function' ? fallback() : fallback
    return fb || '/'
  }

  function goBack() {
    const gated = consumePostAuthBack()
    if (gated) {
      void router.replace(gated)
      return
    }
    if (window.history.state?.back != null) {
      router.back()
      return
    }
    void router.replace(resolveFallback())
  }

  return { goBack }
}

/** 已登錄卻進入認證頁時的落點（勿用 redirect，避免 wishlist←login 死循環） */
export function loggedInAuthBouncePath(): string {
  try {
    const tab = sessionStorage.getItem('sm_last_tab')
    if (tab && (TAB_ROUTE_NAMES as readonly string[]).includes(tab)) {
      if (tab === 'home') return '/'
      return `/${tab}`
    }
  } catch {
    /* private mode */
  }
  return '/'
}

export function rememberLastTabName(name: unknown) {
  if (typeof name === 'string' && (TAB_ROUTE_NAMES as readonly string[]).includes(name)) {
    try {
      sessionStorage.setItem('sm_last_tab', name)
    } catch {
      /* private mode */
    }
  }
}

export { isAuthRouteName }
