<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NCard, NSelect, NSpace, NTag, useMessage } from 'naive-ui'
import { api } from '../api'

type Row = {
  id: number
  aftersale_no: string
  order_id: number
  status: string
  type: string
  amount: number
  refund_to: string
  created_at: string
}

const router = useRouter()
const message = useMessage()
const rows = ref<Row[]>([])
const status = ref<string | null>(null)
const options = [
  { label: '全部', value: null },
  { label: '待審核', value: 'pending_review' },
  { label: '待退貨', value: 'awaiting_return' },
  { label: '已完成', value: 'completed' },
  { label: '已駁回', value: 'rejected' },
]

async function load() {
  const qs = status.value ? `?status=${status.value}` : ''
  const data = await api<{ items: Row[] }>(`/api/v1/admin/aftersales${qs}`, {}, true)
  rows.value = data.items
}

onMounted(load)

async function approve(id: number) {
  await api(`/api/v1/admin/aftersales/${id}/review`, { method: 'POST', body: JSON.stringify({ action: 'approve' }) }, true)
  message.success('已通過')
  await load()
}

async function reject(id: number) {
  await api(`/api/v1/admin/aftersales/${id}/review`, { method: 'POST', body: JSON.stringify({ action: 'reject', admin_note: '不符合售後條件' }) }, true)
  message.success('已駁回')
  await load()
}

async function markReturned(id: number) {
  await api(`/api/v1/admin/aftersales/${id}/mark-returned`, { method: 'POST', body: '{}' }, true)
  message.success('已確認退貨並退款')
  await load()
}
</script>

<template>
  <n-card title="售後">
    <n-space style="margin-bottom: 1rem">
      <n-select v-model:value="status" :options="options" style="width: 10rem" @update:value="load" />
      <n-button @click="load">刷新</n-button>
    </n-space>
    <table class="table">
      <thead>
        <tr>
          <th>單號</th>
          <th>訂單</th>
          <th>類型</th>
          <th>金額</th>
          <th>去向</th>
          <th>狀態</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ row.aftersale_no }}</td>
          <td>
            <n-button text type="primary" @click="router.push(`/sales/orders/${row.order_id}`)">#{{ row.order_id }}</n-button>
          </td>
          <td>{{ row.type }}</td>
          <td>{{ row.amount }}</td>
          <td>{{ row.refund_to }}</td>
          <td><n-tag size="small">{{ row.status }}</n-tag></td>
          <td>
            <n-space>
              <n-button v-if="row.status === 'pending_review'" size="tiny" type="primary" @click="approve(row.id)">通過</n-button>
              <n-button v-if="row.status === 'pending_review'" size="tiny" @click="reject(row.id)">駁回</n-button>
              <n-button v-if="row.status === 'awaiting_return'" size="tiny" @click="markReturned(row.id)">確認退貨</n-button>
            </n-space>
          </td>
        </tr>
      </tbody>
    </table>
  </n-card>
</template>

<style scoped>
.table {
  width: 100%;
  border-collapse: collapse;
}
th,
td {
  border-bottom: 1px solid #eee;
  padding: 0.5rem;
  text-align: left;
  font-size: 0.875rem;
}
</style>
