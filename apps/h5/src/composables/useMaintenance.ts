import { computed, ref } from 'vue'
import { api } from '../api/client'

export type MaintenanceStatus = {
  enabled: boolean
  title: { 'zh-HK': string; en: string }
  message: { 'zh-HK': string; en: string }
  starts_at: string | null
  ends_at: string | null
}

const status = ref<MaintenanceStatus | null>(null)
const loaded = ref(false)
let inflight: Promise<void> | null = null

export function useMaintenance() {
  const enabled = computed(() => Boolean(status.value?.enabled))

  async function refresh() {
    if (inflight) return inflight
    inflight = (async () => {
      try {
        status.value = await api<MaintenanceStatus>('/api/v1/shop/status')
      } catch {
        // 接口失敗不擋店：視為非維護
        status.value = {
          enabled: false,
          title: { 'zh-HK': '', en: '' },
          message: { 'zh-HK': '', en: '' },
          starts_at: null,
          ends_at: null,
        }
      } finally {
        loaded.value = true
        inflight = null
      }
    })()
    return inflight
  }

  return { status, enabled, loaded, refresh }
}
