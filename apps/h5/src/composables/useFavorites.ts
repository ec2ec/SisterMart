import { computed, reactive } from 'vue'
import type { Router } from 'vue-router'
import { showToast } from 'vant'
import { api } from '../api/client'
import { i18n } from '../i18n'
import { goLogin } from './useAuth'

const PENDING = 'sm_pending_fav'
const STORE = 'sm_favorites'

function hasToken() {
  return Boolean(localStorage.getItem('sm_token'))
}

function readStore(): Record<string, boolean> {
  try {
    return JSON.parse(localStorage.getItem(STORE) || '{}') as Record<string, boolean>
  } catch {
    return {}
  }
}

/** 未登錄不讀本地心願緩存（避免訪客角標串到上一帳號） */
function initialLiked(): Record<string, boolean> {
  if (!hasToken()) {
    try {
      localStorage.removeItem(STORE)
    } catch {
      /* private mode */
    }
    return {}
  }
  return readStore()
}

const liked = reactive<Record<string, boolean>>(initialLiked())
let hydrating: Promise<void> | null = null

/** 頂欄心願清單角標：未登錄恒為 0 */
export const favoriteCount = computed(() => {
  if (!hasToken()) return 0
  return Object.keys(liked).filter((k) => liked[k]).length
})

function persist() {
  if (!hasToken()) return
  localStorage.setItem(STORE, JSON.stringify(liked))
}

export function isLoggedIn() {
  return hasToken()
}

export function isFavorite(id: string) {
  if (!hasToken()) return false
  return Boolean(liked[id])
}

/** 登錄後從服務端同步收藏碼；並處理登錄前掛起的收藏。 */
export async function hydrateFavorites() {
  if (!hasToken()) {
    clearFavorites()
    return
  }
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
      /* 保持本地緩存（僅已登錄） */
    } finally {
      hydrating = null
    }
  })()
  return hydrating
}

export function markLoggedIn() {
  void hydrateFavorites()
}

/** 退出登錄：清空內存與本地收藏緩存（避免下一帳號看到上一帳號心願） */
export function clearFavorites() {
  for (const k of Object.keys(liked)) delete liked[k]
  try {
    localStorage.removeItem(STORE)
    sessionStorage.removeItem(PENDING)
  } catch {
    /* private mode */
  }
  hydrating = null
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

export async function requestFavorite(id: string, redirect: string, router: Router) {
  if (!hasToken()) {
    sessionStorage.setItem(PENDING, id)
    void goLogin(router, redirect)
    return
  }
  const next = !liked[id]
  liked[id] = next
  if (!next) delete liked[id]
  persist()
  try {
    // 以樂觀目標為準寫入（add/remove），避免 hydrate 覆蓋後再 toggle 反向
    await setFavoriteRemote(id, next)
    showToast(next ? i18n.global.t('pdp.favAdded') : i18n.global.t('pdp.favRemoved'))
  } catch {
    liked[id] = !next
    if (next) delete liked[id]
    else liked[id] = true
    persist()
    showToast(i18n.global.t('pdp.favFail'))
  }
}
