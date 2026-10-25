<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import {
  NButton,
  NCard,
  NForm,
  NFormItem,
  NInput,
  NInputNumber,
  NPagination,
  NSelect,
  NSpace,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'
import { useTableRowSelect } from '../composables/useTableRowSelect'

type Coupon = {
  id: number
  code: string
  name: { 'zh-HK': string; en: string }
  type: string
  threshold: number
  amount: number
  status: number
  stock: number
  claimed: number
  per_member_limit: number
  starts_at: string | null
  ends_at: string | null
}

const message = useMessage()
const rows = ref<Coupon[]>([])
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const { rowAttrs } = useTableRowSelect()
const form = reactive({
  code: '',
  name_zh: '',
  name_en: '',
  type: 'fixed',
  threshold: 0,
  amount: 20,
  stock: 100,
  per_member_limit: 1,
})
const types = [
  { label: '固定減免', value: 'fixed' },
  { label: '折扣（如 0.9=九折）', value: 'discount' },
  { label: '免運', value: 'free_shipping' },
]

async function load() {
  const data = await api<{ items: Coupon[] }>('/api/v1/admin/coupons', {}, true)
  rows.value = data.items
}

onMounted(load)

async function create() {
  await api(
    '/api/v1/admin/coupons',
    {
      method: 'POST',
      body: JSON.stringify({
        ...form,
        starts_at: new Date(Date.now() - 86400000).toISOString().slice(0, 19).replace('T', ' '),
        ends_at: new Date(Date.now() + 180 * 86400000).toISOString().slice(0, 19).replace('T', ' '),
      }),
    },
    true,
  )
  message.success('已創建')
  form.code = ''
  await load()
}

async function toggle(row: Coupon) {
  await api(
    `/api/v1/admin/coupons/${row.id}`,
    { method: 'PUT', body: JSON.stringify({ status: row.status === 1 ? 0 : 1 }) },
    true,
  )
  message.success('已更新狀態')
  await load()
}
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="優惠券列表">
      <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>券碼</th>
            <th>名稱</th>
            <th>類型</th>
            <th>門檻/額度</th>
            <th>領取</th>
            <th>狀態</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in paged" :key="row.id" v-bind="rowAttrs(row.id)">
            <td>{{ row.code }}</td>
            <td>{{ row.name['zh-HK'] }}</td>
            <td>{{ row.type }}</td>
            <td>≥{{ row.threshold }} / {{ row.amount }}</td>
            <td>{{ row.claimed }}/{{ row.stock }}（限{{ row.per_member_limit }}）</td>
            <td>
              <n-tag size="small" :type="row.status === 1 ? 'success' : 'default'">
                {{ row.status === 1 ? '啟用' : '停用' }}
              </n-tag>
            </td>
            <td>
              <n-space>
                <n-button size="tiny" @click="toggle(row)">{{ row.status === 1 ? '停用' : '啟用' }}</n-button>
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
    </n-card>

    <n-card title="新建券">
      <n-form label-placement="left" label-width="6rem" style="max-width: 36rem">
        <n-form-item label="券碼"><n-input v-model:value="form.code" placeholder="如 SAVE15" /></n-form-item>
        <n-form-item label="中文名"><n-input v-model:value="form.name_zh" /></n-form-item>
        <n-form-item label="英文名"><n-input v-model:value="form.name_en" /></n-form-item>
        <n-form-item label="類型"><n-select v-model:value="form.type" :options="types" /></n-form-item>
        <n-form-item label="門檻"><n-input-number v-model:value="form.threshold" :min="0" /></n-form-item>
        <n-form-item label="面額/折扣"><n-input-number v-model:value="form.amount" :min="0" :step="0.1" /></n-form-item>
        <n-form-item label="庫存"><n-input-number v-model:value="form.stock" :min="0" /></n-form-item>
        <n-form-item label="每人限領"><n-input-number v-model:value="form.per_member_limit" :min="1" /></n-form-item>
        <n-button type="primary" @click="create">新建券</n-button>
      </n-form>
    </n-card>
  </n-space>
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
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
</style>
