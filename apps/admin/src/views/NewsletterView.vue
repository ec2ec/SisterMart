<script setup lang="ts">
import { showError } from '../feedback'
import { onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NInput,
  NSelect,
  NSpace,
  NTag,
  useMessage,
  NPagination,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'
import { useTableRowSelect } from '../composables/useTableRowSelect'

type Row = {
  id: number
  email: string
  member_id: number | null
  status: number
  created_at: string
  updated_at: string
}

const message = useMessage()
const rows = ref<Row[]>([])
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const { rowAttrs } = useTableRowSelect()
const statusFilter = ref<string>('')
const q = ref('')
const busy = ref(false)

const statusOptions = [
  { label: '全部', value: '' },
  { label: '有效', value: '1' },
  { label: '已關閉', value: '0' },
]

async function load() {
  const qs = new URLSearchParams()
  if (statusFilter.value !== '') qs.set('status', statusFilter.value)
  if (q.value.trim()) qs.set('q', q.value.trim())
  const suffix = qs.toString() ? `?${qs}` : ''
  const data = await api<{ items: Row[] }>(`/api/v1/admin/newsletter-subscribers${suffix}`, {}, true)
  rows.value = data.items
}

onMounted(load)

async function setStatus(row: Row, status: number) {
  busy.value = true
  try {
    await api(
      `/api/v1/admin/newsletter-subscribers/${row.id}/status`,
      { method: 'POST', body: JSON.stringify({ status }) },
      true,
    )
    message.success(status ? '已恢復' : '已關閉')
    await load()
  } catch (e: any) {
    showError(e?.message || '操作失敗')
  } finally {
    busy.value = false
  }
}

async function remove(row: Row) {
  if (!window.confirm(`確定刪除 ${row.email}？`)) return
  busy.value = true
  try {
    await api(`/api/v1/admin/newsletter-subscribers/${row.id}`, { method: 'DELETE' }, true)
    message.success('已刪除')
    await load()
  } catch (e: any) {
    showError(e?.message || '刪除失敗')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <n-card title="訂閱名單">
    <n-space style="margin-bottom: 1rem">
      <n-select v-model:value="statusFilter" :options="statusOptions" style="width: 8rem" @update:value="load" />
      <n-input v-model:value="q" placeholder="郵箱關鍵字" style="width: 14rem" @keyup.enter="load" />
      <n-button @click="load">刷新</n-button>
    </n-space>
    <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>郵箱</th>
          <th>會員 ID</th>
          <th>狀態</th>
          <th>訂閱時間</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in paged" :key="row.id" v-bind="rowAttrs(row.id)">
          <td>{{ row.email }}</td>
          <td>{{ row.member_id ?? '—' }}</td>
          <td>
            <n-tag size="small" :type="row.status ? 'success' : 'warning'">
              {{ row.status ? '有效' : '已關閉' }}
            </n-tag>
          </td>
          <td>{{ row.created_at }}</td>
          <td>
            <n-space>
              <n-button
                v-if="row.status"
                size="tiny"
                :disabled="busy"
                @click="setStatus(row, 0)"
              >
                關閉
              </n-button>
              <n-button
                v-else
                size="tiny"
                type="primary"
                :disabled="busy"
                @click="setStatus(row, 1)"
              >
                恢復
              </n-button>
              <n-button size="tiny" type="error" :disabled="busy" @click="remove(row)">刪除</n-button>
            </n-space>
          </td>
        </tr>
      </tbody>
    </table>
    </div>
    <div class="pager">
      <n-pagination
        v-model:page="page"
        v-model:page-size="pageSize"
        :item-count="total"
        :page-sizes="[30, 50, 100]"
        show-size-picker
      />
    </div>
    <p v-if="!rows.length" class="empty">暫無訂閱</p>
  </n-card>
</template>

<style scoped>
.table {
  width: 100%;
  min-width: 900px;
  border-collapse: collapse;
  font-size: 0.875rem;
}
th,
td {
  border-bottom: 1px solid #eee;
  padding: 0.5rem;
  text-align: left;
}
.empty {
  color: #999;
  padding: 1rem 0;
}
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
</style>
