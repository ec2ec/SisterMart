import { onUnmounted, ref, type Ref } from 'vue'
import type { LocationQuery, Router } from 'vue-router'
import { ApiError } from '../api/client'

/** 認證流程頁：不應作為業務頁「返回」落點 */
export const AUTH_ROUTE_NAMES = new Set(['login', 'register', 'forgot'])

const POST_AUTH_BACK_KEY = 'sm_post_auth_back'

export function isAuthRouteName(name: unknown) {
  return typeof name === 'string' && AUTH_ROUTE_NAMES.has(name)
}

const authCodes: Record<string, string> = {
  NEED_AGREE: 'auth.needAgree',
  EMAIL_INVALID: 'auth.emailInvalid',
  PHONE_INVALID: 'auth.phoneInvalid',
  WEAK_PASSWORD: 'auth.passwordRule',
  BAD_PASSWORD: 'auth.badPassword',
  BAD_CODE: 'auth.badCode',
  EMAIL_TAKEN: 'auth.emailTaken',
  PHONE_TAKEN: 'auth.phoneTaken',
  ACCOUNT_NOT_FOUND: 'auth.accountNotFound',
  TOO_SOON: 'auth.tooSoon',
  NO_BOUND_EMAIL: 'auth.noBoundEmail',
  DISABLED: 'auth.disabled',
  WHATSAPP_UNAVAILABLE: 'auth.whatsappFailedNeedEmail',
  USE_PASSWORD: 'auth.usePassword',
  REGISTRATION_CLOSED: 'auth.registerClosed',
  BAD_REF: 'auth.badRef',
}

export function authError(t: (key: string) => string, error: unknown) {
  if (error instanceof ApiError) {
    const key = authCodes[error.code]
    if (key) return t(key)
    if (error.message) return error.message
  }
  return t('auth.network')
}

export function useCooldown(seconds = 60) {
  const left = ref(0)
  let timer = 0

  function start() {
    left.value = seconds
    window.clearInterval(timer)
    timer = window.setInterval(() => {
      left.value -= 1
      if (left.value <= 0) window.clearInterval(timer)
    }, 1000)
  }

  onUnmounted(() => window.clearInterval(timer))
  return { left, start }
}

export function isEmail(value: string) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())
}

export function isPhone(value: string) {
  return /^(?:\+852)?[2-9]\d{7}$/.test(value.replace(/[\s-]/g, ''))
}

export function isStrongPassword(value: string) {
  return /^(?=.*[A-Za-z])(?=.*\d).{8,}$/.test(value)
}

export function isCode(value: string) {
  return /^\d{6}$/.test(value.trim())
}

/**
 * TEMP: 未接通真實郵件／短信時，後端會在 mock／調試下回傳 debug_code。
 * 正式渠道接入後刪除此回填（連同 API 的 debug_code 暴露邏輯）。
 */
export function fillDevOtpCode(codeRef: Ref<string>, data?: { debug_code?: string } | null) {
  const raw = data?.debug_code?.trim()
  if (raw && isCode(raw)) codeRef.value = raw
}

function internalPath(value: unknown) {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//')
}

export function authQuery(query: LocationQuery) {
  const next: Record<string, string> = {}
  if (internalPath(query.redirect)) next.redirect = String(query.redirect)
  if (typeof query.ref === 'string' && query.ref.trim()) next.ref = query.ref.trim()
  else {
    const stored = typeof localStorage !== 'undefined' ? localStorage.getItem('sm_ref') : null
    if (stored) next.ref = stored
  }
  return next
}

export function afterAuthPath(query: LocationQuery) {
  return internalPath(query.redirect) ? String(query.redirect) : '/'
}

/**
 * 未登錄跳轉登錄頁。
 * - replace=true：當前頁本身需要登錄（wishlist 等），用 replace 避免棧裡多一層空頁
 * - replace=false：從公開頁打斷（頂欄心願、帳戶入口），push 保留來源頁
 */
export function goLogin(
  router: Router,
  redirect: string,
  opts: { replace?: boolean } = {},
) {
  const path = internalPath(redirect) ? redirect : '/'
  const loc = { name: 'login' as const, query: { redirect: path } }
  if (opts.replace) return router.replace(loc)
  return router.push(loc)
}

/** 登錄／註冊成功後打標：目標頁第一次點返回應跳過認證頁，回到業務來源（默認首頁） */
export function markPostAuthBack(fallback = '/') {
  try {
    const fb = internalPath(fallback) ? fallback : '/'
    sessionStorage.setItem(POST_AUTH_BACK_KEY, fb)
  } catch {
    /* private mode */
  }
}

/** SubPageNav 等：若剛從認證流進來，消費標記並返回 fallback；否則 null 表示走普通 back */
export function consumePostAuthBack(): string | null {
  try {
    const v = sessionStorage.getItem(POST_AUTH_BACK_KEY)
    if (!v) return null
    sessionStorage.removeItem(POST_AUTH_BACK_KEY)
    return internalPath(v) ? v : '/'
  } catch {
    return null
  }
}

export function clearPostAuthBack() {
  try {
    sessionStorage.removeItem(POST_AUTH_BACK_KEY)
  } catch {
    /* private mode */
  }
}
