import { ref } from 'vue'
import { fetchUnreadCount } from '../api/m7'
import { isLoggedIn, syncSessionFromStorage } from './useSession'

export const unreadCount = ref(0)

export async function refreshUnread() {
  syncSessionFromStorage()
  if (!isLoggedIn()) {
    unreadCount.value = 0
    return 0
  }
  try {
    const data = await fetchUnreadCount()
    unreadCount.value = data.unread || 0
    return unreadCount.value
  } catch {
    unreadCount.value = 0
    return 0
  }
}

/** 未登錄強制清零（訪客不展示上一帳號未讀） */
export function clearUnread() {
  unreadCount.value = 0
}
