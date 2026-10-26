<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  NButton,
  NCard,
  NInput,
  NPagination,
  NSelect,
  NSpace,
  NTag,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'
import { useTableRowSelect } from '../composables/useTableRowSelect'
import { formatRelativeZh } from '../utils/relativeTime'

type Member = {
  id: number
  email: string | null
  phone: string | null
  nickname?: string | null
  promo_code: string
  balance: number
  points: number
  total_spent: number
  status?: number
  level: { stars: number; name: { 'zh-HK': string } }
  created_at?: string
  last_active_at?: string | null
}

const route = useRoute()
const router = useRouter()
const q = ref(typeof route.query.q === 'string' ? route.query.q : '')
const active = ref(typeof route.query.active === 'string' ? route.query.active : '')
const sort = ref(typeof route.query.sort === 'string' ? route.query.sort : 'last_active_at')
const order = ref(typeof route.query.order === 'string' ? route.query.order : 'desc')
const rows = ref<Member[]>([])
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const { rowAttrs } = useTableRowSelect()

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
          <th>狀態</th>
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
          <td>
            <button type="button" class="id-link" @click="router.push(`/customers/members/${row.id}`)">
              #{{ row.id }}
            </button>
          </td>
          <td>
            <div v-if="row.nickname">{{ row.nickname }}</div>
            <div>{{ row.email || row.phone || '-' }}</div>
            <small>{{ row.promo_code }}</small>
          </td>
          <td>
            <n-tag size="small" :type="row.status === 0 ? 'error' : 'success'">
              {{ row.status === 0 ? '禁用' : '正常' }}
            </n-tag>
          </td>
          <td>
            <n-tag size="small">{{ '★'.repeat(row.level?.stars || 1) }}</n-tag>
          </td>
          <td>{{ Number(row.total_spent || 0).toFixed(2) }}</td>
          <td>{{ Number(row.balance).toFixed(2) }}</td>
          <td>{{ row.points }}</td>
          <td :title="row.last_active_at || ''">{{ activeLabel(row) }}</td>
          <td>
            <n-button size="tiny" @click="router.push(`/customers/members/${row.id}`)">詳情</n-button>
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
.id-link {
  border: 0;
  background: transparent;
  color: #24315c;
  font: inherit;
  padding: 0;
  cursor: pointer;
  text-decoration: underline;
}
</style>
