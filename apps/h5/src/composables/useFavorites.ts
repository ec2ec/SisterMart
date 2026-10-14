import { reactive } from 'vue'

const PENDING = 'sm_pending_fav'
const STORE = 'sm_favorites'

function readStore(): Record<string, boolean> {
  try {
    return JSON.parse(localStorage.getItem(STORE) || '{}') as Record<string, boolean>
  } catch {
    return {}
  }
}

const liked = reactive<Record<string, boolean>>(readStore())

function persist() {
  localStorage.setItem(STORE, JSON.stringify(liked))
}

export function isLoggedIn() {
  return Boolean(localStorage.getItem('sm_token'))
}

export function markLoggedIn() {
  const pending = sessionStorage.getItem(PENDING)
  if (!pending) return
  liked[pending] = true
  sessionStorage.removeItem(PENDING)
  persist()
}

export function isFavorite(id: string) {
  return Boolean(liked[id])
}

export function requestFavorite(
  id: string,
  redirect: string,
  push: (to: { name: string; query: Record<string, string> }) => void,
) {
  if (!isLoggedIn()) {
    sessionStorage.setItem(PENDING, id)
    push({ name: 'login', query: { redirect } })
    return
  }
  liked[id] = !liked[id]
  persist()
}
