import { ref } from 'vue'

export type TableRowKey = string | number

/**
 * 表格單選高亮：點擊行淺黃底，橫向滾動仍可辨認選中行。
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

  function rowAttrs(key: TableRowKey) {
    return {
      class: ['sm-table-row', selectedKey.value === key ? 'is-selected' : ''] as string[],
      onClick: () => selectRow(key),
    }
  }

  function clearSelection() {
    selectedKey.value = null
  }

  return { selectedKey, selectRow, isSelected, rowAttrs, clearSelection }
}
