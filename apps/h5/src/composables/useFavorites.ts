import { reactive } from 'vue'
import { allProducts } from '../mock/data'

const PENDING = 'sm_pending_fav'

const liked = reactive<Record<string, boolean>>({})
for (const product of allProducts) liked[product.id] = Boolean(product.liked)

export function isLoggedIn() {
  return Boolean(localStorage.getItem('sm_token'))
}

export function markLoggedIn() {
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
