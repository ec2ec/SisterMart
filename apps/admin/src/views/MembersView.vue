<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import {
  NButton,
  NCard,
  NForm,
  NFormItem,
  NInput,
  NInputNumber,
  NModal,
  NPagination,
  NSelect,
  NSpace,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'
import { useTableRowSelect } from '../composables/useTableRowSelect'
import { formatRelativeZh } from '../utils/relativeTime'

type Member = {
  id: number
  email: string | null
  phone: string | null
  promo_code: string
  balance: number
  points: number
  total_spent: number
  level: { stars: number; name: { 'zh-HK': string } }
  created_at?: string
  last_active_at?: string | null
}

const route = useRoute()
const message = useMessage()
const q = ref(typeof route.query.q === 'string' ? route.query.q : '')
const active = ref(typeof route.query.active === 'string' ? route.query.active : '')
const sort = ref(typeof route.query.sort === 'string' ? route.query.sort : 'id')
const order = ref(typeof route.query.order === 'string' ? route.query.order : 'desc')
const rows = ref<Member[]>([])
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const { rowAttrs } = useTableRowSelect()
const show = ref(false)
const current = ref<Member | null>(null)
const adjust = reactive({ points_delta: 0, balance_delta: 0, reason: '' })

const activeOptions = [
  { label: '全部活躍', value: '' },
  { label: '近 1 天活躍', value: '1d' },
  { label: '近 7 天活躍', value: '7d' },
  { label: '近 30 天活躍', value: '30d' },
  { label: '從未活躍', value: 'never' },
  { label: '7 天未活躍', value: 'inactive_7d' },
  { label: '30 天未活躍', value: 'inactive_30d' },
]

const sortOptions = [
  { label: '按 ID', value: 'id' },
  { label: '按最後活躍', value: 'last_active_at' },
]

const orderOptions = [
  { label: '降序', value: 'desc' },
  { label: '升序', value: 'asc' },
]

async function load() {
  const params = new URLSearchParams()
  if (q.value) params.set('q', q.value)
  if (active.value) params.set('active', active.value)
  if (sort.value) params.set('sort', sort.value)
  if (order.value) params.set('order', order.value)
  const qs = params.toString() ? `?${params}` : ''
  const data = await api<{ items: Member[] }>(`/api/v1/admin/members${qs}`, {}, true)
  rows.value = data.items
  page.value = 1
}

onMounted(load)

function openAdjust(row: Member) {
  current.value = row
  adjust.points_delta = 0
  adjust.balance_delta = 0
  adjust.reason = ''
  show.value = true
}

async function submitAdjust() {
  if (!current.value) return
  await api(
    `/api/v1/admin/members/${current.value.id}/asset-adjustments`,
    { method: 'POST', body: JSON.stringify(adjust) },
    true,
  )
  message.success('已調整')
  show.value = false
  await load()
}

function activeLabel(row: Member) {
  if (!row.last_active_at) return '-'
  return formatRelativeZh(row.last_active_at) || '-'
}
</script>

<template>
  <n-card title="會員">
    <n-space style="margin-bottom: 1rem" wrap>
      <n-input v-model:value="q" placeholder="郵箱 / 手機 / 推廣碼" style="width: 16rem" @keyup.enter="load" />
      <n-select
        v-model:value="active"
        :options="activeOptions"
        style="width: 10rem"
        @update:value="load"
      />
      <n-select v-model:value="sort" :options="sortOptions" style="width: 9rem" @update:value="load" />
      <n-select v-model:value="order" :options="orderOptions" style="width: 6rem" @update:value="load" />
      <n-button @click="load">搜索</n-button>
    </n-space>
    <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>帳號</th>
          <th>等級</th>
          <th>累計實付</th>
          <th>餘額</th>
          <th>積分</th>
          <th>最後活躍</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in paged" :key="row.id" v-bind="rowAttrs(row.id)">
          <td>{{ row.id }}</td>
          <td>{{ row.email || row.phone }}<br /><small>{{ row.promo_code }}</small></td>
          <td>
            <n-tag size="small">{{ '★'.repeat(row.level?.stars || 1) }} {{ row.level?.name?.['zh-HK'] }}</n-tag>
          </td>
          <td>{{ Number(row.total_spent || 0).toFixed(2) }}</td>
          <td>{{ Number(row.balance).toFixed(2) }}</td>
          <td>{{ row.points }}</td>
          <td :title="row.last_active_at || ''">{{ activeLabel(row) }}</td>
          <td><n-button size="tiny" @click="openAdjust(row)">調整資產</n-button></td>
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

    <n-modal v-model:show="show" preset="card" title="調整會員資產" style="width: 28rem">
      <p v-if="current">會員 #{{ current.id }} · {{ current.email || current.phone }}</p>
      <n-form label-placement="left" label-width="6rem">
        <n-form-item label="積分變動"><n-input-number v-model:value="adjust.points_delta" /></n-form-item>
        <n-form-item label="餘額變動"><n-input-number v-model:value="adjust.balance_delta" :step="0.01" /></n-form-item>
        <n-form-item label="原因"><n-input v-model:value="adjust.reason" type="textarea" placeholder="必填" /></n-form-item>
      </n-form>
      <n-button type="primary" @click="submitAdjust">確認調整</n-button>
    </n-modal>
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
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
</style>
