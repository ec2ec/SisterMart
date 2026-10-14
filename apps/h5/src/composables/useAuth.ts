import { onUnmounted, ref } from 'vue'
import type { LocationQuery } from 'vue-router'

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

function internalPath(value: unknown) {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//')
}

export function authQuery(query: LocationQuery) {
  const next: Record<string, string> = {}
  if (internalPath(query.redirect)) next.redirect = String(query.redirect)
  if (typeof query.ref === 'string' && query.ref.trim()) next.ref = query.ref.trim()
  return next
}

export function afterAuthPath(query: LocationQuery) {
  return internalPath(query.redirect) ? String(query.redirect) : '/'
}
