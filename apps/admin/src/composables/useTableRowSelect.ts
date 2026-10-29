import { ref } from 'vue'

export type TableRowKey = string | number

/**
 * 表格單選高亮：點擊行淺黃底，橫向滾動仍可辨認選中行。
 * 雙擊行內容 → 觸發該行最後一個操作按鈕（通常是詳情／編輯／審核）。
 *
 * @example
 * const { rowAttrs } = useTableRowSelect()
 * <tr v-bind="rowAttrs(row.id)" :class="{ off: row.status !== 1 }">
 */
export function useTableRowSelect() {
  const selectedKey = ref<TableRowKey | null>(null)

  function selectRow(key: TableRowKey) {
    selectedKey.value = key
  }

  function isSelected(key: TableRowKey) {
    return selectedKey.value === key
  }

  function clickLastActionButton(ev: MouseEvent) {
    const target = ev.target as HTMLElement | null
    if (!target) return
    // 表單控件上的雙擊不劫持
    if (target.closest('input, textarea, select, option')) return

    const tr = ev.currentTarget as HTMLElement | null
    if (!tr) return

    const lastTd = tr.querySelector(':scope > td:last-child, :scope > th:last-child')
    const scope = lastTd || tr
    const buttons = Array.from(
      scope.querySelectorAll<HTMLElement>('button:not([disabled]), .n-button:not(.n-button--disabled)'),
    ).filter((el) => {
      // 排除隱藏鈕
      if (el.closest('[aria-hidden="true"]')) return false
      const style = window.getComputedStyle(el)
      return style.display !== 'none' && style.visibility !== 'hidden'
    })
    const last = buttons[buttons.length - 1]
    if (!last) return

    ev.preventDefault()
    last.click()
  }

  function rowAttrs(key: TableRowKey) {
    return {
      class: ['sm-table-row', selectedKey.value === key ? 'is-selected' : ''] as string[],
      onClick: () => selectRow(key),
      onDblclick: (ev: MouseEvent) => {
        selectRow(key)
        clickLastActionButton(ev)
      },
    }
  }

  function clearSelection() {
    selectedKey.value = null
  }

  return { selectedKey, selectRow, isSelected, rowAttrs, clearSelection }
}
