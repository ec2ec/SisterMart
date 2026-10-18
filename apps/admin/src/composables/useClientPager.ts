import { computed, ref, watch, type Ref } from 'vue'

/** 客戶端分頁：默認每頁 30 條 */
export function useClientPager<T>(source: Ref<T[]>, defaultPageSize = 30) {
  const page = ref(1)
  const pageSize = ref(defaultPageSize)

  const total = computed(() => source.value.length)
  const paged = computed(() => {
    const start = (page.value - 1) * pageSize.value
    return source.value.slice(start, start + pageSize.value)
  })

  watch(source, () => {
    page.value = 1
  })

  watch(pageSize, () => {
    page.value = 1
  })

  return { page, pageSize, total, paged }
}
