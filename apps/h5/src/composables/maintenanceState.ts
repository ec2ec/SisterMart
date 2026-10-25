import { computed, ref } from 'vue'

export type MaintenanceStatus = {
  enabled: boolean
  title: { 'zh-HK': string; en: string }
  message: { 'zh-HK': string; en: string }
  starts_at: string | null
  ends_at: string | null
  /** 是否開放註冊；缺省視為開放 */
  registration_enabled?: boolean
}

const emptyStatus = (): MaintenanceStatus => ({
  enabled: false,
  title: { 'zh-HK': '', en: '' },
  message: { 'zh-HK': '', en: '' },
  starts_at: null,
  ends_at: null,
})

/** 模組級狀態：供 App / useMaintenance / api 攔截器共用，避免循環依賴 */
export const maintenanceStatus = ref<MaintenanceStatus | null>(null)
export const maintenanceLoaded = ref(false)

export const maintenanceEnabled = computed(() => Boolean(maintenanceStatus.value?.enabled))

function asLocalized(raw: unknown, fallback = ''): { 'zh-HK': string; en: string } {
  if (raw && typeof raw === 'object') {
    const o = raw as Record<string, unknown>
    return {
      'zh-HK': String(o['zh-HK'] ?? o.zh ?? fallback),
      en: String(o.en ?? fallback),
    }
  }
  if (typeof raw === 'string' && raw) {
    return { 'zh-HK': raw, en: raw }
  }
  return { 'zh-HK': fallback, en: fallback }
}

/** api() 攔截 MAINTENANCE 時寫入；可只帶部分欄位 */
export function applyMaintenanceFromDetails(details: unknown) {
  const d = (details && typeof details === 'object' ? details : {}) as Record<string, unknown>
  const prev = maintenanceStatus.value || emptyStatus()
  maintenanceStatus.value = {
    enabled: true,
    title: asLocalized(d.title, prev.title['zh-HK'] || prev.title.en),
    message: asLocalized(d.message, prev.message['zh-HK'] || prev.message.en),
    starts_at:
      d.starts_at === undefined ? prev.starts_at : d.starts_at ? String(d.starts_at) : null,
    ends_at: d.ends_at === undefined ? prev.ends_at : d.ends_at ? String(d.ends_at) : null,
    registration_enabled: prev.registration_enabled,
  }
  maintenanceLoaded.value = true
}

export function setMaintenanceStatus(next: MaintenanceStatus | null) {
  maintenanceStatus.value = next
  maintenanceLoaded.value = true
}

export function clearMaintenanceStatus() {
  maintenanceStatus.value = emptyStatus()
  maintenanceLoaded.value = true
}
