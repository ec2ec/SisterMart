/** 路由級滾動位置緩存（配合 keep-alive + scrollBehavior） */

const positions = new Map<string, number>()

export function saveRouteScroll(key: string) {
  const y = window.scrollY || document.documentElement.scrollTop || 0
  positions.set(key, y)
}

export function peekRouteScroll(key: string): number | undefined {
  return positions.get(key)
}

export function restoreRouteScroll(key: string, fallback = 0) {
  const y = positions.has(key) ? (positions.get(key) as number) : fallback
  const apply = () => window.scrollTo({ top: y, left: 0, behavior: 'auto' })
  // 佈局未穩定時多跳一次，避免長列表/圖片把高度撐開後錯位
  requestAnimationFrame(() => {
    apply()
    requestAnimationFrame(apply)
  })
}

export function clearRouteScroll(key: string) {
  positions.delete(key)
}
