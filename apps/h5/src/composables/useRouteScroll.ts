/** 路由級滾動位置緩存（配合 keep-alive + scrollBehavior） */

const positions = new Map<string, number>()

export function saveRouteScroll(key: string) {
  const y = window.scrollY || document.documentElement.scrollTop || 0
  // hash 切換可能已把視口打回頂部；勿用 0 覆蓋已保存的有效位置
  if (y <= 0 && positions.has(key) && (positions.get(key) as number) > 0) {
    return
  }
  positions.set(key, y)
}

/** 強制寫入（離開守衛裡、點擊跳轉前） */
export function forceSaveRouteScroll(key: string, y?: number) {
  const next =
    typeof y === 'number' ? y : window.scrollY || document.documentElement.scrollTop || 0
  positions.set(key, Math.max(0, next))
}

export function peekRouteScroll(key: string): number | undefined {
  return positions.get(key)
}

export function restoreRouteScroll(key: string, fallback = 0) {
  const y = positions.has(key) ? (positions.get(key) as number) : fallback
  if (y <= 0) {
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' })
    return
  }
  const apply = () => {
    window.scrollTo({ top: y, left: 0, behavior: 'auto' })
  }
  // 佈局未穩定 / hash 路由二次置頂時多跳幾次
  apply()
  requestAnimationFrame(() => {
    apply()
    requestAnimationFrame(apply)
  })
  ;[50, 120, 250, 450].forEach((ms) => {
    window.setTimeout(apply, ms)
  })
}

export function clearRouteScroll(key: string) {
  positions.delete(key)
}
