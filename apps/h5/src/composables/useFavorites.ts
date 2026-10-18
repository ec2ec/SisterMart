import { reactive } from 'vue'
import { api } from '../api/client'

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
let hydrating: Promise<void> | null = null

function persist() {
  localStorage.setItem(STORE, JSON.stringify(liked))
}

export function isLoggedIn() {
  return Boolean(localStorage.getItem('sm_token'))
}

export function isFavorite(id: string) {
  return Boolean(liked[id])
}

/** 登錄後從服務端同步收藏碼；並處理登錄前掛起的收藏。 */
export async function hydrateFavorites() {
  if (!isLoggedIn()) return
  if (hydrating) return hydrating
  hydrating = (async () => {
    try {
      const data = await api<{ product_codes: string[] }>('/api/v1/favorites/codes', {}, 'member')
      const next: Record<string, boolean> = {}
      for (const code of data.product_codes || []) next[code] = true
      for (const k of Object.keys(liked)) delete liked[k]
      Object.assign(liked, next)
      persist()
      const pending = sessionStorage.getItem(PENDING)
      if (pending) {
        sessionStorage.removeItem(PENDING)
        await setFavoriteRemote(pending, true)
      }
    } catch {
      /* 保持本地緩存 */
    } finally {
      hydrating = null
    }
  })()
  return hydrating
}

export function markLoggedIn() {
  void hydrateFavorites()
}

async function setFavoriteRemote(id: string, want: boolean) {
  if (want) {
    const data = await api<{ favorited: boolean; product_code: string }>(
      '/api/v1/favorites',
      { method: 'POST', body: JSON.stringify({ product_code: id }) },
      'member',
    )
    liked[data.product_code] = true
    persist()
    return true
  }
  const data = await api<{ favorited: boolean; product_code: string }>(
    `/api/v1/favorites/${encodeURIComponent(id)}`,
    { method: 'DELETE' },
    'member',
  )
  delete liked[data.product_code]
  persist()
  return false
}

export async function requestFavorite(
  id: string,
  redirect: string,
  push: (to: { name: string; query: Record<string, string> }) => void,
) {
  if (!isLoggedIn()) {
    sessionStorage.setItem(PENDING, id)
    push({ name: 'login', query: { redirect } })
    return
  }
  const next = !liked[id]
  liked[id] = next
  if (!next) delete liked[id]
  persist()
  try {
    // 以樂觀目標為準寫入（add/remove），避免 hydrate 覆蓋後再 toggle 反向
    await setFavoriteRemote(id, next)
  } catch {
    liked[id] = !next
    if (next) delete liked[id]
    else liked[id] = true
    persist()
  }
}
