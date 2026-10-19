import { ref } from 'vue'
import { fetchCart } from '../api/trade'
import { isLoggedIn, syncSessionFromStorage } from './useSession'

/** 底欄購物車角標：商品件數合計；空車為 0 不顯示 */
export const cartBadgeCount = ref(0)

export async function refreshCartBadge() {
  syncSessionFromStorage()
  if (!isLoggedIn()) {
    cartBadgeCount.value = 0
    return 0
  }
  try {
    const data = await fetchCart()
    const n = (data.items || []).reduce((sum, i) => sum + Math.max(0, Number(i.qty) || 0), 0)
    cartBadgeCount.value = n
    return n
  } catch {
    cartBadgeCount.value = 0
    return 0
  }
}

export function setCartBadgeCount(n: number) {
  cartBadgeCount.value = Math.max(0, Math.floor(n))
}
