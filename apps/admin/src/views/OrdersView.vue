<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NCard, NPagination, NSelect, NSpace, NTag } from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'
import { useTableRowSelect } from '../composables/useTableRowSelect'
import { ORDER_STATUS_FILTER_OPTIONS, orderStatusLabel, orderStatusTagType } from '../utils/orderStatus'

type Row = {
  id: number
  order_no: string
  status: string
  pay_amount: number
  member_id: number
  created_at: string
  title: string
}

const route = useRoute()
const router = useRouter()
const rows = ref<Row[]>([])
const status = ref(typeof route.query.status === 'string' ? route.query.status : '')
const memberId = ref(typeof route.query.member_id === 'string' ? route.query.member_id : '')
const options = ORDER_STATUS_FILTER_OPTIONS
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const { rowAttrs } = useTableRowSelect()
const loadError = ref('')

async function load() {
  loadError.value = ''
  try {
    const params = new URLSearchParams()
    if (status.value) params.set('status', status.value)
    if (memberId.value) params.set('member_id', memberId.value)
    const qs = params.toString() ? `?${params}` : ''
    const data = await api<{ items: Row[] }>(`/api/v1/admin/orders${qs}`, {}, true)
    rows.value = data.items
  } catch (e: any) {
    loadError.value = e?.message || '加載失敗'
    rows.value = []
  }
}

onMounted(load)

function clearMemberFilter() {
  memberId.value = ''
  router.replace({ path: '/sales/orders', query: status.value ? { status: status.value } : {} })
  load()
}
</script>

<template>
  <n-card title="訂單">
    <n-space style="margin-bottom: 1rem" wrap>
      <n-select v-model:value="status" :options="options" style="width: 10rem" @update:value="load" />
      <n-tag v-if="memberId" closable @close="clearMemberFilter">
        會員 #{{ memberId }}
      </n-tag>
      <n-button @click="load">刷新</n-button>
    </n-space>
    <p v-if="loadError" style="color: #d03050">{{ loadError }}</p>
    <div class="table-wrap">
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
        <tr v-for="row in paged" :key="row.id" v-bind="rowAttrs(row.id)">
          <td>{{ row.order_no }}</td>
          <td>
            <n-button text type="primary" @click="router.push(`/customers/members/${row.member_id}`)">
              #{{ row.member_id }}
            </n-button>
          </td>
          <td>{{ row.title }}</td>
          <td>{{ row.pay_amount }}</td>
          <td>
            <n-tag size="small" :type="orderStatusTagType(row.status)" :bordered="false">
              {{ orderStatusLabel(row.status) }}
            </n-tag>
          </td>
          <td>{{ row.created_at }}</td>
          <td><n-button size="tiny" @click="router.push(`/sales/orders/${row.id}`)">詳情</n-button></td>
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
  </n-card>
</template>

<style scoped>
.table {
  width: 100%;
  min-width: 900px;
  border-collapse: collapse;
}
th,
td {
  border-bottom: 1px solid #eee;
  padding: 0.5rem;
  text-align: left;
  font-size: 0.875rem;
}
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
</style>
