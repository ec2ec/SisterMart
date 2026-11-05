import type { ProductCard as Card } from '../api/catalog'

const TTL_MS = 15 * 60 * 1000
/** v2：丟棄舊緩存裡失控的 nextOffset（曾隨機 0–500 且 finished 永不為真） */
const STORAGE_KEY = 'sm_recommend_feed_v2'

export type RecommendFeedCache = {
  expiresAt: number
  items: Card[]
  nextOffset: number
  total: number
  exhausted?: boolean
}

function readStorage(): RecommendFeedCache | null {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as RecommendFeedCache
    if (!parsed || !Array.isArray(parsed.items) || typeof parsed.expiresAt !== 'number') {
      return null
    }
    return parsed
  } catch {
    return null
  }
}

function writeStorage(cache: RecommendFeedCache | null) {
  try {
    if (!cache) {
      sessionStorage.removeItem(STORAGE_KEY)
      return
    }
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(cache))
  } catch {
    /* quota / private mode */
  }
}

/** 模塊級 + sessionStorage，跨購物車／訂單／心願清單共用 */
let memory: RecommendFeedCache | null = readStorage()

export function getRecommendFeedCache(): RecommendFeedCache | null {
  const cur = memory || readStorage()
  if (!cur) return null
  if (Date.now() >= cur.expiresAt) {
    memory = null
    writeStorage(null)
    return null
  }
  memory = cur
  return cur
}

export function saveRecommendFeedCache(partial: {
  items: Card[]
  nextOffset: number
  total: number
  exhausted?: boolean
  /** 是否刷新過期時間（首次寫入／整表重拉） */
  renewTtl?: boolean
}) {
  const prev = getRecommendFeedCache()
  const expiresAt = partial.renewTtl || !prev ? Date.now() + TTL_MS : prev.expiresAt
  memory = {
    expiresAt,
    items: partial.items,
    nextOffset: partial.nextOffset,
    total: partial.total,
    exhausted:
      partial.exhausted === true ||
      (partial.total > 0 && partial.items.length >= partial.total) ||
      partial.total === 0,
  }
  writeStorage(memory)
}

export function clearRecommendFeedCache() {
  memory = null
  writeStorage(null)
  try {
    sessionStorage.removeItem('sm_recommend_feed_v1')
  } catch {
    /* ignore */
  }
}

export const RECOMMEND_FEED_TTL_MS = TTL_MS
