import { showToast } from 'vant'
import { api } from '../api/client'
import { i18n } from '../i18n'

export type ShopContact = {
  cs_whatsapp: string
  cs_facebook_url: string
  whatsapp_digits: string
}

let cached: ShopContact | null = null
let inflight: Promise<ShopContact> | null = null

export async function fetchShopContact(force = false): Promise<ShopContact> {
  if (!force && cached) return cached
  if (!force && inflight) return inflight
  inflight = api<ShopContact>('/api/v1/shop/contact')
    .then((data) => {
      cached = {
        cs_whatsapp: data.cs_whatsapp || '',
        cs_facebook_url: data.cs_facebook_url || '',
        whatsapp_digits: (data.whatsapp_digits || data.cs_whatsapp || '').replace(/\D/g, ''),
      }
      return cached
    })
    .finally(() => {
      inflight = null
    })
  return inflight
}

/** 打開後台配置的客服 WhatsApp；未配置時 toast */
export async function openCustomerWhatsApp(prefill?: string): Promise<boolean> {
  const contact = await fetchShopContact().catch(() => null)
  const digits = contact?.whatsapp_digits || ''
  if (!digits) {
    showToast(i18n.global.t('fulfill.whatsappMissing'))
    return false
  }
  const qs = prefill ? `?text=${encodeURIComponent(prefill)}` : ''
  window.open(`https://wa.me/${digits}${qs}`, '_blank', 'noopener')
  return true
}
