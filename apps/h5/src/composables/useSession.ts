import { ref } from 'vue'
import { markLoggedIn } from './useFavorites'

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

export const memberState = ref<Member | null>(readMember())

export function token() {
  return localStorage.getItem(TOKEN) || ''
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

export function setSession(tokenValue: string, member: Member) {
  localStorage.setItem(TOKEN, tokenValue)
  localStorage.setItem(MEMBER, JSON.stringify(member))
  memberState.value = member
  markLoggedIn()
}

export function clearSession() {
  localStorage.removeItem(TOKEN)
  localStorage.removeItem(MEMBER)
  localStorage.removeItem('sm_session')
  memberState.value = null
}
