import { onUnmounted, ref, type Ref } from 'vue'
import type { LocationQuery } from 'vue-router'
import { ApiError } from '../api/client'

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
