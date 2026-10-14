import { reactive } from 'vue'
import { allProducts } from '../mock/data'

const SESSION = 'sm_session'
const PENDING = 'sm_pending_fav'

const liked = reactive<Record<string, boolean>>({})
for (const product of allProducts) liked[product.id] = Boolean(product.liked)

export function isLoggedIn() {
  return localStorage.getItem(SESSION) === '1'
}

export function markLoggedIn() {
  localStorage.setItem(SESSION, '1')
  const pending = sessionStorage.getItem(PENDING)
  if (!pending) return
  liked[pending] = true
  sessionStorage.removeItem(PENDING)
}

export function isFavorite(id: string) {
  return Boolean(liked[id])
}

export function requestFavorite(id: string, redirect: string, push: (to: { name: string; query: Record<string, string> }) => void) {
  if (!isLoggedIn()) {
    sessionStorage.setItem(PENDING, id)
    push({ name: 'login', query: { redirect } })
    return
  }
  liked[id] = !liked[id]
}
