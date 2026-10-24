import { computed, ref } from 'vue'
import { clearCartBadge } from './useCartBadge'
import { clearFavorites, markLoggedIn } from './useFavorites'
import { clearOrderCounts } from './useOrderCounts'
import { clearOrdersListCache } from './useOrdersList'
import { clearUnread } from './useUnread'

export type Member = {
  id: number
  email: string | null
  phone: string | null
  nickname?: string | null
  locale: 'zh-HK' | 'en'
  avatar_url?: string | null
  promo_code: string
  balance?: number
  points?: number
  total_spent?: number
  coupon_count?: number
  level_id?: number
  level?: {
    id: number
    stars: number
    name: { 'zh-HK': string; en: string }
    min_spent: number
    max_spent: number | null
  }
}

const TOKEN = 'sm_token'
const MEMBER = 'sm_member'

function readMember(): Member | null {
  const raw = localStorage.getItem(MEMBER)
  if (!raw) return null
  try {
    return JSON.parse(raw) as Member
  } catch {
    return null
  }
}

function readToken(): string {
  return localStorage.getItem(TOKEN) || ''
}

/** 響應式令牌：模板／computed 才能在登錄後自動刷新 */
export const authToken = ref(readToken())
export const memberState = ref<Member | null>(readMember())

/** 是否已登錄（響應式；勿用「每次讀 localStorage」的函數當模板依賴） */
export const loggedIn = computed(() => Boolean(authToken.value))

export function token() {
  // 先對齊 storage，避免 keep-alive 頁仍持有登錄前的空 authToken
  if (!authToken.value) {
    const fromStore = readToken()
    if (fromStore) authToken.value = fromStore
  }
  return authToken.value || readToken()
}

export function isLoggedIn() {
  return Boolean(token())
}

export function currentMember() {
  if (!memberState.value) {
    memberState.value = readMember()
  }
  return memberState.value
}

/** 從 localStorage 拉齊內存（keep-alive 頁從登錄返回時用） */
export function syncSessionFromStorage() {
  authToken.value = readToken()
  memberState.value = readMember()
}

export function setSession(tokenValue: string, member: Member) {
  localStorage.setItem(TOKEN, tokenValue)
  localStorage.setItem(MEMBER, JSON.stringify(member))
  authToken.value = tokenValue
  memberState.value = member
  markLoggedIn()
}

export function clearSession() {
  localStorage.removeItem(TOKEN)
  localStorage.removeItem(MEMBER)
  localStorage.removeItem('sm_session')
  authToken.value = ''
  memberState.value = null
  // 會員相關內存／本地緩存一併清掉，避免串號
  clearFavorites()
  clearCartBadge()
  clearUnread()
  clearOrderCounts()
  clearOrdersListCache()
}
