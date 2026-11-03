import { api } from './api'

export type UploadRequirement = {
  key: string
  format: 'keep' | 'jpg' | 'png'
  max_edge: number
  max_upload_mb: number
  max_kb: number
}

/** 與後端場景默認值一致；接口返回後改用當前配置。 */
const DEFAULTS: UploadRequirement[] = [
  { key: 'member.avatar', format: 'jpg', max_edge: 1000, max_upload_mb: 5, max_kb: 500 },
  { key: 'member.review', format: 'jpg', max_edge: 1000, max_upload_mb: 5, max_kb: 500 },
  { key: 'member.aftersale', format: 'jpg', max_edge: 1000, max_upload_mb: 5, max_kb: 500 },
  { key: 'catalog.product', format: 'jpg', max_edge: 1600, max_upload_mb: 5, max_kb: 700 },
  { key: 'catalog.product_detail', format: 'jpg', max_edge: 1600, max_upload_mb: 5, max_kb: 700 },
  { key: 'catalog.category', format: 'png', max_edge: 512, max_upload_mb: 5, max_kb: 200 },
  { key: 'marketing.banner', format: 'jpg', max_edge: 1920, max_upload_mb: 5, max_kb: 800 },
  { key: 'marketing.image_ad', format: 'jpg', max_edge: 1920, max_upload_mb: 5, max_kb: 800 },
  { key: 'marketing.campaign', format: 'jpg', max_edge: 1920, max_upload_mb: 5, max_kb: 800 },
  { key: 'marketing.popup', format: 'jpg', max_edge: 1600, max_upload_mb: 5, max_kb: 700 },
  { key: 'marketing.coupon', format: 'jpg', max_edge: 1600, max_upload_mb: 5, max_kb: 700 },
  { key: 'content.article', format: 'jpg', max_edge: 1600, max_upload_mb: 5, max_kb: 700 },
  { key: 'content.welcome', format: 'jpg', max_edge: 1600, max_upload_mb: 5, max_kb: 700 },
  { key: 'content.campaign', format: 'jpg', max_edge: 1600, max_upload_mb: 5, max_kb: 700 },
  { key: 'content.coupon', format: 'jpg', max_edge: 1600, max_upload_mb: 5, max_kb: 700 },
  { key: 'content.richtext', format: 'jpg', max_edge: 1600, max_upload_mb: 5, max_kb: 700 },
]

const fallback: UploadRequirement = {
  key: '',
  format: 'jpg',
  max_edge: 1600,
  max_upload_mb: 5,
  max_kb: 700,
}

let loaded: Promise<UploadRequirement[]> | null = null

export function uploadRequirement(scene: string): UploadRequirement {
  return DEFAULTS.find((row) => row.key === scene) ?? { ...fallback, key: scene }
}

export function loadUploadRequirements(): Promise<UploadRequirement[]> {
  if (!loaded) {
    loaded = api<{ items: UploadRequirement[] }>('/api/v1/admin/uploads/requirements', {}, true, true)
      .then((data) => {
        const items = Array.isArray(data.items) ? data.items : []
        for (const item of items) {
          const idx = DEFAULTS.findIndex((row) => row.key === item.key)
          const next: UploadRequirement = {
            key: item.key,
            format: item.format === 'png' || item.format === 'keep' ? item.format : 'jpg',
            max_edge: Number(item.max_edge) || fallback.max_edge,
            max_upload_mb: Number(item.max_upload_mb) || fallback.max_upload_mb,
            max_kb: Number(item.max_kb) || fallback.max_kb,
          }
          if (idx >= 0) DEFAULTS[idx] = next
          else DEFAULTS.push(next)
        }
        return DEFAULTS
      })
      .catch(() => {
        loaded = null
        return DEFAULTS
      })
  }
  return loaded
}

function formatMb(mb: number): string {
  const rounded = Math.round(mb * 100) / 100
  return String(rounded)
}

function formatLabel(format: UploadRequirement['format']): string {
  if (format === 'png') return 'PNG'
  if (format === 'keep') return '原格式（PNG、WebP 保留，其餘轉 JPG）'
  return 'JPG'
}

/** 上傳區直接寫出的限制，不指向設置頁。 */
export function describeUpload(scene: string, maxCount = 0): string {
  const row = uploadRequirement(scene)
  const count = maxCount > 0 ? `最多 ${maxCount} 張。` : ''
  return `${count}可上傳 JPG、PNG、WebP，單張不超過 ${formatMb(row.max_upload_mb)}MB。輸出為 ${formatLabel(row.format)}，最長邊 ${row.max_edge}，壓縮後不超過 ${row.max_kb}KB。`
}
