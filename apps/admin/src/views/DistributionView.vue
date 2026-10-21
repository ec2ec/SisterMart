<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import {
  NButton,
  NCard,
  NInput,
  NModal,
  NPagination,
  NSelect,
  NSpace,
  NTabPane,
  NTabs,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'

type Withdrawal = {
  id: number
  member_id: number
  member_nickname?: string
  member_phone?: string
  member_email?: string
  commission_available?: number
  channel: string
  amount: number
  status: string
  fps_name: string | null
  fps_id: string | null
  review_note: string | null
  created_at: string
  reviewed_at?: string | null
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

const REJECT_TEMPLATES = [
  '不符合提現條件',
  '收款姓名與帳戶不符',
  '轉數快資料不完整或不正確',
  '重複申請，請勿重複提交',
  '金額異常，請重新申請',
]

const STATUS_LABEL: Record<string, string> = {
  pending_review: '待審核',
  paid: '已打款',
  rejected: '已駁回',
  done: '已轉餘額',
}

const route = useRoute()
const message = useMessage()
const tab = ref(typeof route.query.tab === 'string' ? route.query.tab : 'withdrawals')
const withdrawals = ref<Withdrawal[]>([])
const pendingCount = ref(0)
const ranking = ref<RankRow[]>([])
const commissions = ref<CommRow[]>([])
const products = ref<ProdRow[]>([])
const { page: wPage, pageSize: wPageSize, total: wTotal, paged: wPaged } = useClientPager(withdrawals, 30)
const { page: pPage, pageSize: pPageSize, total: pTotal, paged: pPaged } = useClientPager(products, 30)
const { page: rPage, pageSize: rPageSize, total: rTotal, paged: rPaged } = useClientPager(ranking, 30)
const { page: cPage, pageSize: cPageSize, total: cTotal, paged: cPaged } = useClientPager(commissions, 30)
const period = ref('month')
const filter = ref(
  typeof route.query.status === 'string' ? route.query.status : 'pending_review',
)
const channelFilter = ref(typeof route.query.channel === 'string' ? route.query.channel : '')
const keyword = ref('')

const statusOptions = [
  { label: '全部狀態', value: '' },
  { label: '待審核', value: 'pending_review' },
  { label: '已打款', value: 'paid' },
  { label: '已駁回', value: 'rejected' },
  { label: '已轉餘額', value: 'done' },
]
const channelOptions = [
  { label: '全部渠道', value: '' },
  { label: '轉數快', value: 'fps' },
  { label: '轉餘額', value: 'balance' },
]

const reviewOpen = ref(false)
const reviewAction = ref<'approve' | 'reject'>('approve')
const reviewRow = ref<Withdrawal | null>(null)
const reviewNote = ref('')
const reviewBusy = ref(false)

const withdrawalsTabLabel = computed(() =>
  pendingCount.value > 0 ? `提現審核（${pendingCount.value}）` : '提現審核',
)

function statusLabel(s: string) {
  return STATUS_LABEL[s] || s
}

function statusType(s: string): 'warning' | 'success' | 'error' | 'default' {
  if (s === 'pending_review') return 'warning'
  if (s === 'paid' || s === 'done') return 'success'
  if (s === 'rejected') return 'error'
  return 'default'
}

function memberLabel(row: Withdrawal) {
  const name = row.member_nickname || row.member_email || row.member_phone || ''
  return name ? `${name} · #${row.member_id}` : `會員 #${row.member_id}`
}

async function loadWithdrawals() {
  const qs = new URLSearchParams()
  if (filter.value) qs.set('status', filter.value)
  if (channelFilter.value) qs.set('channel', channelFilter.value)
  if (keyword.value.trim()) qs.set('q', keyword.value.trim())
  const q = qs.toString() ? `?${qs}` : ''
  const data = await api<{ items: Withdrawal[]; pending_count?: number }>(
    `/api/v1/admin/withdrawals${q}`,
    {},
    true,
  )
  withdrawals.value = data.items
  pendingCount.value = data.pending_count ?? data.items.filter((x) => x.status === 'pending_review').length
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

function openReview(row: Withdrawal, action: 'approve' | 'reject') {
  reviewRow.value = row
  reviewAction.value = action
  reviewNote.value = action === 'reject' ? REJECT_TEMPLATES[0] : '已線下打款'
  reviewOpen.value = true
}

async function confirmReview() {
  if (!reviewRow.value) return
  const row = reviewRow.value
  const action = reviewAction.value
  const note = reviewNote.value.trim()
  if (action === 'reject' && !note) {
    message.warning('請填寫駁回原因')
    return
  }
  if (action === 'approve') {
    const ok = window.confirm(
      `確認已線下打款？\n將標記 #${row.id} · HK$ ${row.amount.toFixed(2)}（FPS ${row.fps_id || '-'}）為已打款。`,
    )
    if (!ok) return
  }
  await submitReview(row.id, action, note)
}

async function submitReview(id: number, action: 'approve' | 'reject', note: string) {
  reviewBusy.value = true
  try {
    await api(
      `/api/v1/admin/withdrawals/${id}/review`,
      { method: 'POST', body: JSON.stringify({ action, note }) },
      true,
    )
    message.success(action === 'approve' ? '已標記打款通過' : '已駁回')
    reviewOpen.value = false
    await loadWithdrawals()
  } catch (e: any) {
    message.error(e?.message || '審核失敗')
  } finally {
    reviewBusy.value = false
  }
}

async function onTab(name: string | number) {
  tab.value = String(name)
  if (tab.value === 'withdrawals') await loadWithdrawals()
  if (tab.value === 'ranking') await loadRanking()
  if (tab.value === 'commissions') await loadCommissions()
  if (tab.value === 'products') await loadProducts()
}

onMounted(() => {
  void onTab(tab.value)
})
</script>

<template>
  <n-card title="分銷">
    <n-tabs :value="tab" type="line" @update:value="onTab">
      <n-tab-pane name="withdrawals" :tab="withdrawalsTabLabel">
        <n-space style="margin-bottom: 1rem" wrap>
          <n-select
            v-model:value="filter"
            :options="statusOptions"
            style="width: 9rem"
            @update:value="loadWithdrawals"
          />
          <n-select
            v-model:value="channelFilter"
            :options="channelOptions"
            style="width: 8rem"
            @update:value="loadWithdrawals"
          />
          <n-input
            v-model:value="keyword"
            clearable
            placeholder="會員 / FPS / 單號"
            style="width: 12rem"
            @keyup.enter="loadWithdrawals"
          />
          <n-button @click="loadWithdrawals">刷新</n-button>
        </n-space>
        <div v-for="row in wPaged" :key="row.id" class="row">
          <div>
            <strong>#{{ row.id }} · {{ memberLabel(row) }}</strong>
            <p>
              {{ row.channel === 'fps' ? '轉數快' : '轉餘額' }} · HK$ {{ row.amount.toFixed(2) }} ·
              {{ row.created_at }}
              <n-tag size="small" :type="statusType(row.status)" style="margin-left: 0.5rem">
                {{ statusLabel(row.status) }}
              </n-tag>
            </p>
            <p v-if="row.fps_id" class="muted">FPS {{ row.fps_name }} / {{ row.fps_id }}</p>
            <p v-if="row.commission_available != null" class="muted">
              當前可提現 HK$ {{ Number(row.commission_available).toFixed(2) }}
            </p>
            <p v-if="row.review_note" class="muted">備註：{{ row.review_note }}</p>
            <p v-if="row.reviewed_at" class="muted">審核於 {{ row.reviewed_at }}</p>
          </div>
          <n-space v-if="row.status === 'pending_review'">
            <n-button size="tiny" type="primary" @click="openReview(row, 'approve')">通過</n-button>
            <n-button size="tiny" @click="openReview(row, 'reject')">駁回</n-button>
          </n-space>
        </div>
        <p v-if="!withdrawals.length" class="empty">暫無提現單</p>
        <div class="pager">
          <n-pagination
            v-model:page="wPage"
            v-model:page-size="wPageSize"
            :item-count="wTotal"
            :page-sizes="[30, 50, 100]"
            show-size-picker
          />
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
                ·
                {{
                  row.commission_type === 'percent'
                    ? `${row.commission_value}%`
                    : `HK$ ${row.commission_value}`
                }}
              </template>
            </p>
          </div>
          <n-button size="tiny" tag="a" :href="`#/catalog/products/${row.id}`">編輯</n-button>
        </div>
        <div class="pager">
          <n-pagination
            v-model:page="pPage"
            v-model:page-size="pPageSize"
            :item-count="pTotal"
            :page-sizes="[30, 50, 100]"
            show-size-picker
          />
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
          <n-pagination
            v-model:page="rPage"
            v-model:page-size="rPageSize"
            :item-count="rTotal"
            :page-sizes="[30, 50, 100]"
            show-size-picker
          />
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
          <n-pagination
            v-model:page="cPage"
            v-model:page-size="cPageSize"
            :item-count="cTotal"
            :page-sizes="[30, 50, 100]"
            show-size-picker
          />
        </div>
      </n-tab-pane>
    </n-tabs>

    <n-modal
      v-model:show="reviewOpen"
      preset="card"
      :title="reviewAction === 'approve' ? '通過提現' : '駁回提現'"
      style="width: 28rem; max-width: 94vw"
    >
      <p v-if="reviewRow" class="modal-lead">
        #{{ reviewRow.id }} · HK$ {{ reviewRow.amount.toFixed(2) }} ·
        {{ reviewRow.fps_name || '-' }} / {{ reviewRow.fps_id || '-' }}
      </p>
      <p class="hint">審核備註（會寫入記錄；駁回時一併通知會員）</p>
      <n-input v-model:value="reviewNote" type="textarea" :rows="3" placeholder="備註" />
      <n-space v-if="reviewAction === 'reject'" style="margin-top: 0.65rem" wrap>
        <n-button
          v-for="tpl in REJECT_TEMPLATES"
          :key="tpl"
          size="tiny"
          secondary
          @click="reviewNote = tpl"
        >
          {{ tpl }}
        </n-button>
      </n-space>
      <template #footer>
        <n-space justify="end">
          <n-button @click="reviewOpen = false">取消</n-button>
          <n-button
            :loading="reviewBusy"
            :type="reviewAction === 'approve' ? 'primary' : 'error'"
            @click="confirmReview"
          >
            {{ reviewAction === 'approve' ? '確認通過' : '確認駁回' }}
          </n-button>
        </n-space>
      </template>
    </n-modal>
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
.modal-lead {
  margin: 0 0 0.75rem;
  font-size: 0.875rem;
}
</style>
