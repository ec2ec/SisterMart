import { ref, type Ref } from 'vue'

/** 協議行左右抖動提醒 */
export function shakeAgreeRow(el: Ref<HTMLElement | null | undefined>) {
  const node = el.value
  if (!node) return
  node.classList.remove('shake')
  // 強制重啟動畫
  void node.offsetWidth
  node.classList.add('shake')
  window.setTimeout(() => node.classList.remove('shake'), 900)
}

/** 請求中 busy 標誌，防止重複點擊 */
export function useRequestBusy() {
  const busy = ref(false)

  async function run<T>(fn: () => Promise<T>): Promise<T | undefined> {
    if (busy.value) return undefined
    busy.value = true
    try {
      return await fn()
    } finally {
      busy.value = false
    }
  }

  return { busy, run }
}

export const JUST_REGISTERED_KEY = 'sm_just_registered'

export function markJustRegistered() {
  try {
    sessionStorage.setItem(JUST_REGISTERED_KEY, '1')
  } catch {
    /* ignore */
  }
}

export function peekJustRegistered(): boolean {
  try {
    return sessionStorage.getItem(JUST_REGISTERED_KEY) === '1'
  } catch {
    return false
  }
}

export function consumeJustRegistered(): boolean {
  try {
    if (sessionStorage.getItem(JUST_REGISTERED_KEY) === '1') {
      sessionStorage.removeItem(JUST_REGISTERED_KEY)
      return true
    }
  } catch {
    /* ignore */
  }
  return false
}
