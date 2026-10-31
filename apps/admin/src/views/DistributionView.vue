<script setup lang="ts">
import { showError } from '../feedback'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  NButton,
  NCard,
  NDatePicker,
  NInput,
  NModal,
  NPagination,
  NSelect,
  NSpace,
  NSwitch,
  NTabPane,
  NTabs,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import MemberPickerDialog, { type MemberPickResult } from '../components/MemberPickerDialog.vue'
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
  reviewed_by_admin_id?: number | null
  reviewer_name?: string
  reviewer_email?: string
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
const router = useRouter()
const message = useMessage()
const tab = ref(typeof route.query.tab === 'string' ? route.query.tab : 'withdrawals')
const withdrawals = ref<Withdrawal[]>([])
const pendingCount = ref(0)
const ranking = ref<RankRow[]>([])
const commissions = ref<CommRow[]>([])
const products = ref<ProdRow[]>([])
const wPage = ref(1)
const wPageSize = ref(30)
const wTotal = ref(0)
const { page: pPage, pageSize: pPageSize, total: pTotal, paged: pPaged } = useClientPager(products, 30)
const { page: rPage, pageSize: rPageSize, total: rTotal, paged: rPaged } = useClientPager(ranking, 30)
const { page: cPage, pageSize: cPageSize, total: cTotal, paged: cPaged } = useClientPager(commissions, 30)
const period = ref('month')
const filter = ref(
  typeof route.query.status === 'string' ? route.query.status : 'pending_review',
)
const channelFilter = ref(typeof route.query.channel === 'string' ? route.query.channel : '')
const keyword = ref('')
const createdFrom = ref<string | null>(null)
const createdTo = ref<string | null>(null)
const reviewedFrom = ref<string | null>(null)
const reviewedTo = ref<string | null>(null)

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
const approveConfirmOpen = ref(false)
const reviewAction = ref<'approve' | 'reject'>('approve')
const reviewRow = ref<Withdrawal | null>(null)
const reviewNote = ref('')
const reviewBusy = ref(false)

type AccessMember = {
  id: number
  email: string | null
  phone: string | null
  nickname: string | null
  promo_code: string
  status: number
}

const accessWhitelistOnly = ref(true)
const accessMembers = ref<AccessMember[]>([])
const accessBusy = ref(false)
const accessPickerOpen = ref(false)
const accessExcludeIds = computed(() => accessMembers.value.map((m) => m.id))

function accessMemberLabel(m: AccessMember) {
  return m.email || m.phone || m.nickname || m.promo_code || `#${m.id}`
}

function addAccessMember(m: MemberPickResult) {
  if (accessMembers.value.some((x) => x.id === m.id)) {
    message.info('該會員已在白名單')
    return
  }
  accessMembers.value = [
    ...accessMembers.value,
    {
      id: m.id,
      email: m.email,
      phone: m.phone,
      nickname: m.nickname,
      promo_code: m.promo_code || '',
      status: m.status,
    },
  ]
}

function removeAccessMember(id: number) {
  accessMembers.value = accessMembers.value.filter((m) => m.id !== id)
}

async function loadAccess() {
  const data = await api<{
    mode: string
    whitelist_only: boolean
    members?: AccessMember[]
  }>('/api/v1/admin/settings/distribution-access', {}, true)
  accessWhitelistOnly.value = data.whitelist_only !== false && data.mode !== 'open'
  accessMembers.value = data.members || []
}

async function saveAccess() {
  accessBusy.value = true
  try {
    await api(
      '/api/v1/admin/settings/distribution-access',
      {
        method: 'PUT',
        body: JSON.stringify({
          mode: accessWhitelistOnly.value ? 'whitelist' : 'open',
          member_ids: accessMembers.value.map((m) => m.id),
        }),
      },
      true,
    )
    message.success(
      accessWhitelistOnly.value
        ? `已開啟白名單（${accessMembers.value.length} 人可進分銷中心）`
        : '已關閉白名單（全體會員可進分銷中心）',
    )
    await loadAccess()
  } catch (e: any) {
    showError(e?.message || '保存失敗')
  } finally {
    accessBusy.value = false
  }
}

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

function reviewerLabel(row: Withdrawal) {
  if (row.reviewer_name) return row.reviewer_name
  if (row.reviewer_email) return row.reviewer_email
  if (row.reviewed_by_admin_id) return `管理員 #${row.reviewed_by_admin_id}`
  return ''
}

function goMember(row: Withdrawal) {
  void router.push({ path: '/customers/members', query: { q: String(row.member_id) } })
}

async function loadWithdrawals() {
  const qs = new URLSearchParams()
  if (filter.value) qs.set('status', filter.value)
  if (channelFilter.value) qs.set('channel', channelFilter.value)
  if (keyword.value.trim()) qs.set('q', keyword.value.trim())
  if (createdFrom.value) qs.set('created_from', createdFrom.value)
  if (createdTo.value) qs.set('created_to', createdTo.value)
  if (reviewedFrom.value) qs.set('reviewed_from', reviewedFrom.value)
  if (reviewedTo.value) qs.set('reviewed_to', reviewedTo.value)
  qs.set('page', String(wPage.value))
  qs.set('page_size', String(wPageSize.value))
  const data = await api<{
    items: Withdrawal[]
    pending_count?: number
    total?: number
    page?: number
    page_size?: number
  }>(`/api/v1/admin/withdrawals?${qs}`, {}, true)
  withdrawals.value = data.items
  wTotal.value = data.total ?? data.items.length
  if (data.page) wPage.value = data.page
  pendingCount.value = data.pending_count ?? data.items.filter((x) => x.status === 'pending_review').length
}

function reloadWithdrawalsFromFilters() {
  if (wPage.value !== 1) {
    wPage.value = 1
    return
  }
  void loadWithdrawals()
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
    // 3-B：與駁回同風格的 Naive 二次確認，取代 window.confirm
    approveConfirmOpen.value = true
    return
  }
  await submitReview(row.id, action, note)
}

async function confirmApprovePaid() {
  if (!reviewRow.value) return
  await submitReview(reviewRow.value.id, 'approve', reviewNote.value.trim())
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
    approveConfirmOpen.value = false
    await loadWithdrawals()
  } catch (e: any) {
    showError(e?.message || '審核失敗')
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
  if (tab.value === 'access') await loadAccess()
}

watch([wPage, wPageSize], () => {
  if (tab.value === 'withdrawals') void loadWithdrawals()
})

onMounted(() => {
  void onTab(tab.value)
})
</script>

<template>
  <n-card title="分銷">
    <n-tabs :value="tab" type="line" @update:value="onTab">
      <n-tab-pane name="withdrawals" :tab="withdrawalsTabLabel">
        <n-space style="margin-bottom: 0.75rem" wrap>
          <n-select
            v-model:value="filter"
            :options="statusOptions"
            style="width: 9rem"
            @update:value="reloadWithdrawalsFromFilters"
          />
          <n-select
            v-model:value="channelFilter"
            :options="channelOptions"
            style="width: 8rem"
            @update:value="reloadWithdrawalsFromFilters"
          />
          <n-input
            v-model:value="keyword"
            clearable
            placeholder="會員 / FPS / 單號"
            style="width: 12rem"
            @keyup.enter="reloadWithdrawalsFromFilters"
          />
          <n-button @click="reloadWithdrawalsFromFilters">刷新</n-button>
        </n-space>
        <n-space style="margin-bottom: 1rem" wrap align="center">
          <span class="filter-label">申請日</span>
          <n-date-picker
            v-model:formatted-value="createdFrom"
            type="date"
            value-format="yyyy-MM-dd"
            clearable
            placeholder="起"
            style="width: 9.5rem"
            @update:formatted-value="reloadWithdrawalsFromFilters"
          />
          <n-date-picker
            v-model:formatted-value="createdTo"
            type="date"
            value-format="yyyy-MM-dd"
            clearable
            placeholder="止"
            style="width: 9.5rem"
            @update:formatted-value="reloadWithdrawalsFromFilters"
          />
          <span class="filter-label">審核日</span>
          <n-date-picker
            v-model:formatted-value="reviewedFrom"
            type="date"
            value-format="yyyy-MM-dd"
            clearable
            placeholder="起"
            style="width: 9.5rem"
            @update:formatted-value="reloadWithdrawalsFromFilters"
          />
          <n-date-picker
            v-model:formatted-value="reviewedTo"
            type="date"
            value-format="yyyy-MM-dd"
            clearable
            placeholder="止"
            style="width: 9.5rem"
            @update:formatted-value="reloadWithdrawalsFromFilters"
          />
        </n-space>
        <div v-for="row in withdrawals" :key="row.id" class="row">
          <div>
            <strong>
              #{{ row.id }} ·
              <button type="button" class="member-link" @click="goMember(row)">
                {{ memberLabel(row) }}
              </button>
            </strong>
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
            <p v-if="row.reviewed_at" class="muted">
              審核於 {{ row.reviewed_at
              }}<template v-if="reviewerLabel(row)"> · {{ reviewerLabel(row) }}</template>
            </p>
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

      <n-tab-pane name="access" tab="入口白名單">
        <p class="hint">
          開啟後，H5「分銷中心」入口與相關 API 僅白名單會員可用；直鏈
          <code>/#/distribution</code> 也會被擋回個人中心。推廣碼綁定／下單計佣不受影響。最多 200 人。
        </p>
        <n-space align="center" style="margin-bottom: 0.75rem">
          <span>僅白名單可進</span>
          <n-switch v-model:value="accessWhitelistOnly" />
          <span class="hint" style="margin: 0">
            {{
              accessWhitelistOnly
                ? '已開啟 — 僅下列會員可見入口'
                : '已關閉 — 全體登錄會員可進'
            }}
          </span>
        </n-space>
        <n-space align="center" style="margin-bottom: 0.5rem">
          <n-button type="primary" secondary :disabled="!accessWhitelistOnly" @click="accessPickerOpen = true">
            搜索加入用戶
          </n-button>
          <span class="hint" style="margin: 0">已選 {{ accessMembers.length }} 人</span>
        </n-space>
        <div v-if="accessMembers.length" class="access-list">
          <n-tag
            v-for="m in accessMembers"
            :key="m.id"
            closable
            type="info"
            :bordered="false"
            style="margin: 0 0.35rem 0.35rem 0"
            @close="removeAccessMember(m.id)"
          >
            #{{ m.id }} {{ accessMemberLabel(m) }}
          </n-tag>
        </div>
        <p v-else class="empty">尚未加入用戶</p>
        <n-button type="primary" :loading="accessBusy" style="margin-top: 0.75rem" @click="saveAccess">
          保存白名單
        </n-button>
      </n-tab-pane>
    </n-tabs>

    <MemberPickerDialog
      v-model:show="accessPickerOpen"
      title="搜索加入分銷白名單"
      :exclude-ids="accessExcludeIds"
      @select="addAccessMember"
    />

    <n-modal
      v-model:show="reviewOpen"
      preset="card"
      :title="reviewAction === 'approve' ? '通過提現' : '駁回提現'"
      style="width: 28rem; max-width: 94vw"
     :mask-closable="false" :close-on-esc="false">
      <p v-if="reviewRow" class="modal-lead">
        #{{ reviewRow.id }} · HK$ {{ reviewRow.amount.toFixed(2) }} ·
        {{ reviewRow.fps_name || '-' }} / {{ reviewRow.fps_id || '-' }}
      </p>
      <p class="hint">
        {{
          reviewAction === 'approve'
            ? '審核備註（會寫入記錄；請確認已完成線下打款）'
            : '審核備註（會寫入記錄；駁回時一併通知會員）'
        }}
      </p>
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
            :loading="reviewBusy && reviewAction === 'reject'"
            :type="reviewAction === 'approve' ? 'primary' : 'error'"
            @click="confirmReview"
          >
            {{ reviewAction === 'approve' ? '下一步' : '確認駁回' }}
          </n-button>
        </n-space>
      </template>
    </n-modal>

    <n-modal
      v-model:show="approveConfirmOpen"
      preset="card"
      title="確認已線下打款"
      style="width: 28rem; max-width: 94vw"
     :mask-closable="false" :close-on-esc="false">
      <p v-if="reviewRow" class="modal-lead">
        將標記 #{{ reviewRow.id }} · HK$ {{ reviewRow.amount.toFixed(2) }}（FPS
        {{ reviewRow.fps_id || '-' }}）為已打款。此操作不可撤銷。
      </p>
      <p v-if="reviewNote.trim()" class="hint">備註：{{ reviewNote.trim() }}</p>
      <template #footer>
        <n-space justify="end">
          <n-button :disabled="reviewBusy" @click="approveConfirmOpen = false">返回</n-button>
          <n-button type="primary" :loading="reviewBusy" @click="confirmApprovePaid">
            確認已線下打款
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
.empty,
.filter-label {
  color: #888;
  font-size: 0.8125rem;
}
.filter-label {
  white-space: nowrap;
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
.member-link {
  padding: 0;
  border: 0;
  background: none;
  color: #2080f0;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 2px;
}
.member-link:hover {
  color: #1060c9;
}
.access-list {
  min-height: 2rem;
}
</style>
