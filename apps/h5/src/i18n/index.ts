import { createI18n } from 'vue-i18n'
import { messages, type LocaleKey } from './messages'

const saved = (localStorage.getItem('sm_locale') as LocaleKey | null) || 'zh-HK'

export const i18n = createI18n({
  legacy: false,
  locale: saved,
  fallbackLocale: 'en',
  messages,
})

export function toggleLocale() {
  const next: LocaleKey = i18n.global.locale.value === 'zh-HK' ? 'en' : 'zh-HK'
  i18n.global.locale.value = next
  localStorage.setItem('sm_locale', next)
  document.documentElement.lang = next
}
