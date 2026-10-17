import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { LocaleKey } from '../i18n/messages'

export type Localized<T = string> = Record<LocaleKey, T>

export function useLocaleText() {
  const { locale } = useI18n()
  const current = computed(() => locale.value as LocaleKey)

  function text(map: Localized) {
    return map[current.value] ?? map['zh-HK']
  }

  return { locale: current, text }
}
