import { api } from '../api/client'
import {
  clearMaintenanceStatus,
  maintenanceEnabled,
  maintenanceLoaded,
  maintenanceStatus,
  setMaintenanceStatus,
  type MaintenanceStatus,
} from './maintenanceState'

export type { MaintenanceStatus }

let inflight: Promise<void> | null = null

export function useMaintenance() {
  async function refresh() {
    if (inflight) return inflight
    inflight = (async () => {
      try {
        // 帶會員令牌時後端可回 member_bypass（用戶白名單）
        const auth = localStorage.getItem('sm_token') ? 'member' : 'none'
        const data = await api<MaintenanceStatus>('/api/v1/shop/status', {}, auth)
        setMaintenanceStatus({
          enabled: Boolean(data.enabled),
          member_bypass: Boolean(data.member_bypass),
          title: data.title || { 'zh-HK': '', en: '' },
          message: data.message || { 'zh-HK': '', en: '' },
          starts_at: data.starts_at ?? null,
          ends_at: data.ends_at ?? null,
          registration_enabled: data.registration_enabled,
        })
      } catch {
        // shop/status 本身在白名單；仍失敗則不擋店
        clearMaintenanceStatus()
      } finally {
        inflight = null
      }
    })()
    return inflight
  }

  return {
    status: maintenanceStatus,
    enabled: maintenanceEnabled,
    loaded: maintenanceLoaded,
    refresh,
  }
}
