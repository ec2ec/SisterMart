<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NCard, NSelect, NSpace, NTag } from 'naive-ui'
import { api } from '../api'

type Row = {
  id: number
  order_no: string
  status: string
  pay_amount: number
  member_id: number
  created_at: string
  title: string
}

const router = useRouter()
const rows = ref<Row[]>([])
const status = ref<string | null>(null)
const options = [
  { label: '全部', value: null },
  { label: '待付款', value: 'pending_payment' },
  { label: '待發貨', value: 'pending_shipment' },
  { label: '已取消', value: 'cancelled' },
]

async function load() {
  const qs = status.value ? `?status=${status.value}` : ''
  const data = await api<{ items: Row[] }>(`/api/v1/admin/orders${qs}`, {}, true)
  rows.value = data.items
}

onMounted(load)
</script>

<template>
  <n-card title="訂單">
    <n-space style="margin-bottom: 1rem">
      <n-select v-model:value="status" :options="options" style="width: 10rem" @update:value="load" />
      <n-button @click="load">刷新</n-button>
    </n-space>
    <table class="table">
      <thead>
        <tr>
          <th>單號</th>
          <th>會員</th>
          <th>商品</th>
          <th>實付</th>
          <th>狀態</th>
          <th>時間</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ row.order_no }}</td>
          <td>#{{ row.member_id }}</td>
          <td>{{ row.title }}</td>
          <td>{{ row.pay_amount }}</td>
          <td><n-tag size="small">{{ row.status }}</n-tag></td>
          <td>{{ row.created_at }}</td>
          <td><n-button size="tiny" @click="router.push(`/sales/orders/${row.id}`)">詳情</n-button></td>
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
