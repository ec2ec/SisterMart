<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { NButton, NCard, NPagination, NSelect, NSpace, NTabPane, NTabs, NTag, useMessage } from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'

type Withdrawal = {
  id: number
  member_id: number
  channel: string
  amount: number
  status: string
  fps_name: string | null
  fps_id: string | null
  review_note: string | null
  created_at: string
}

type RankRow = { rank: number; nickname: string; commission: number }
type CommRow = {
  id: number
  order_no: string
  beneficiary: string
  amount: number
  status: string
  created_at: string
}
type ProdRow = {
  id: number
  code: string
  name: string
  status: number
  commission_type: string | null
  commission_value: number | null
}

const message = useMessage()
const tab = ref('withdrawals')
const withdrawals = ref<Withdrawal[]>([])
const ranking = ref<RankRow[]>([])
const commissions = ref<CommRow[]>([])
const products = ref<ProdRow[]>([])
const { page: wPage, pageSize: wPageSize, total: wTotal, paged: wPaged } = useClientPager(withdrawals, 30)
const { page: pPage, pageSize: pPageSize, total: pTotal, paged: pPaged } = useClientPager(products, 30)
const { page: rPage, pageSize: rPageSize, total: rTotal, paged: rPaged } = useClientPager(ranking, 30)
const { page: cPage, pageSize: cPageSize, total: cTotal, paged: cPaged } = useClientPager(commissions, 30)
const period = ref('month')
const filter = ref('')

const statusOptions = [
  { label: '全部', value: '' },
  { label: '待審核', value: 'pending_review' },
  { label: '已通過', value: 'paid' },
  { label: '已駁回', value: 'rejected' },
  { label: '已轉餘額', value: 'done' },
]

async function loadWithdrawals() {
  const qs = filter.value ? `?status=${encodeURIComponent(filter.value)}` : ''
  const data = await api<{ items: Withdrawal[] }>(`/api/v1/admin/withdrawals${qs}`, {}, true)
  withdrawals.value = data.items
}

async function loadRanking() {
  const data = await api<{ items: RankRow[] }>(
    `/api/v1/admin/distribution/ranking?period=${encodeURIComponent(period.value)}`,
    {},
    true,
  )
  ranking.value = data.items
}

async function loadCommissions() {
  const data = await api<{ items: CommRow[] }>('/api/v1/admin/distribution/commissions', {}, true)
  commissions.value = data.items
}

async function loadProducts() {
  const data = await api<{ items: ProdRow[] }>('/api/v1/admin/distribution/products', {}, true)
  products.value = data.items
}

async function review(id: number, action: 'approve' | 'reject') {
  await api(`/api/v1/admin/withdrawals/${id}/review`, {
    method: 'POST',
    body: JSON.stringify({ action, note: action === 'reject' ? '不符合提現條件' : '已審核' }),
  }, true)
  message.success(action === 'approve' ? '已通過' : '已駁回')
  await loadWithdrawals()
}

async function onTab(name: string | number) {
  tab.value = String(name)
  if (tab.value === 'withdrawals') await loadWithdrawals()
  if (tab.value === 'ranking') await loadRanking()
  if (tab.value === 'commissions') await loadCommissions()
  if (tab.value === 'products') await loadProducts()
}

onMounted(() => {
  void loadWithdrawals()
})
</script>

<template>
  <n-card title="分銷">
    <n-tabs :value="tab" type="line" @update:value="onTab">
      <n-tab-pane name="withdrawals" tab="提現審核">
        <n-space style="margin-bottom: 1rem">
          <n-select v-model:value="filter" :options="statusOptions" style="width: 10rem" @update:value="loadWithdrawals" />
          <n-button @click="loadWithdrawals">刷新</n-button>
        </n-space>
        <div v-for="row in wPaged" :key="row.id" class="row">
          <div>
            <strong>#{{ row.id }} · 會員 {{ row.member_id }}</strong>
            <p>
              {{ row.channel }} · HK$ {{ row.amount.toFixed(2) }} · {{ row.created_at }}
              <n-tag size="small" style="margin-left: 0.5rem">{{ row.status }}</n-tag>
            </p>
            <p v-if="row.fps_id" class="muted">FPS {{ row.fps_name }} / {{ row.fps_id }}</p>
          </div>
          <n-space v-if="row.status === 'pending_review'">
            <n-button size="tiny" type="primary" @click="review(row.id, 'approve')">通過</n-button>
            <n-button size="tiny" @click="review(row.id, 'reject')">駁回</n-button>
          </n-space>
        </div>
        <p v-if="!withdrawals.length" class="empty">暫無提現單</p>
        <div class="pager">
          <n-pagination v-model:page="wPage" v-model:page-size="wPageSize" :item-count="wTotal" :page-sizes="[30, 50, 100]" show-size-picker />
        </div>
      </n-tab-pane>

      <n-tab-pane name="products" tab="商品佣金">
        <p class="hint">在「商品編輯」頁可改佣金類型與數值；此處只讀一覽。</p>
        <div v-for="row in pPaged" :key="row.id" class="row">
          <div>
            <strong>{{ row.code }} · {{ row.name }}</strong>
            <p>
              {{ row.commission_type || '未配置' }}
              <template v-if="row.commission_value != null">
                · {{ row.commission_type === 'percent' ? `${row.commission_value}%` : `HK$ ${row.commission_value}` }}
              </template>
            </p>
          </div>
          <n-button size="tiny" tag="a" :href="`#/catalog/products/${row.id}`">編輯</n-button>
        </div>
        <div class="pager">
          <n-pagination v-model:page="pPage" v-model:page-size="pPageSize" :item-count="pTotal" :page-sizes="[30, 50, 100]" show-size-picker />
        </div>
      </n-tab-pane>

      <n-tab-pane name="ranking" tab="佣金排行">
        <n-space style="margin-bottom: 1rem">
          <n-select
            v-model:value="period"
            :options="[
              { label: '本月', value: 'month' },
              { label: '累計', value: 'total' },
            ]"
            style="width: 8rem"
            @update:value="loadRanking"
          />
        </n-space>
        <div v-for="row in rPaged" :key="row.rank" class="row">
          <span>#{{ row.rank }} {{ row.nickname }}</span>
          <strong>HK$ {{ row.commission.toFixed(2) }}</strong>
        </div>
        <p v-if="!ranking.length" class="empty">暫無排行數據</p>
        <div class="pager">
          <n-pagination v-model:page="rPage" v-model:page-size="rPageSize" :item-count="rTotal" :page-sizes="[30, 50, 100]" show-size-picker />
        </div>
      </n-tab-pane>

      <n-tab-pane name="commissions" tab="佣金明細">
        <div v-for="row in cPaged" :key="row.id" class="row">
          <div>
            <strong>{{ row.order_no || `#${row.id}` }}</strong>
            <p>{{ row.beneficiary }} · {{ row.status }} · {{ row.created_at }}</p>
          </div>
          <strong>HK$ {{ row.amount.toFixed(2) }}</strong>
        </div>
        <p v-if="!commissions.length" class="empty">暫無佣金明細</p>
        <div class="pager">
          <n-pagination v-model:page="cPage" v-model:page-size="cPageSize" :item-count="cTotal" :page-sizes="[30, 50, 100]" show-size-picker />
        </div>
      </n-tab-pane>
    </n-tabs>
  </n-card>
</template>

<style scoped>
.row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding: 0.65rem 0;
  border-bottom: 1px solid #eee;
}
.row p {
  margin: 0.2rem 0 0;
  color: #666;
  font-size: 0.8125rem;
}
.muted,
.hint,
.empty {
  color: #888;
  font-size: 0.8125rem;
}
.hint {
  margin: 0 0 0.75rem;
}
.empty {
  text-align: center;
  padding: 1.5rem;
}
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
</style>
