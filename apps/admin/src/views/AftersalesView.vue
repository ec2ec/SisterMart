<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  NButton,
  NCard,
  NImage,
  NImageGroup,
  NInput,
  NModal,
  NSelect,
  NSpace,
  NTag,
  useMessage,
  NPagination,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'
import { useTableRowSelect } from '../composables/useTableRowSelect'
import { assetUrl } from '../utils/assetUrl'

type Row = {
  id: number
  aftersale_no: string
  order_id: number
  order_no?: string
  order_status?: string
  status: string
  type: string
  amount: number
  qty?: number
  refund_to: string
  created_at: string
  description?: string
  evidence?: string[]
  evidence_count?: number
  admin_note?: string
  item?: {
    name: { 'zh-HK': string; en: string }
    image?: string
  } | null
}

const route = useRoute()
const router = useRouter()
const message = useMessage()
const rows = ref<Row[]>([])
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const { rowAttrs } = useTableRowSelect()
const status = ref(
  typeof route.query.status === 'string' ? route.query.status : 'pending_review',
)
const hasEvidence = ref<string>(
  typeof route.query.has_evidence === 'string' ? route.query.has_evidence : '',
)

const options = [
  { label: '待審核', value: 'pending_review' },
  { label: '待退貨', value: 'awaiting_return' },
  { label: '已完成', value: 'completed' },
  { label: '已駁回', value: 'rejected' },
  { label: '全部', value: '' },
]
const evidenceOptions = [
  { label: '證據不限', value: '' },
  { label: '有證據', value: '1' },
  { label: '無證據', value: '0' },
]

const show = ref(false)
const current = ref<Row | null>(null)
const note = ref('')
const busy = ref(false)

const typeLabel = (t: string) => (t === 'return_refund' ? '退貨退款' : '僅退款')
const statusLabel = (s: string) =>
  ({
    pending_review: '待審核',
    awaiting_return: '待退貨',
    pending_refund: '待退款',
    completed: '已完成',
    rejected: '已駁回',
    cancelled: '已取消',
  }[s] || s)

async function load() {
  const qs = new URLSearchParams()
  if (status.value) qs.set('status', status.value)
  if (hasEvidence.value !== '') qs.set('has_evidence', hasEvidence.value)
  const q = qs.toString() ? `?${qs}` : ''
  const data = await api<{ items: Row[] }>(`/api/v1/admin/aftersales${q}`, {}, true)
  rows.value = data.items
}

onMounted(load)

async function openDetail(row: Row) {
  const data = await api<Row>(`/api/v1/admin/aftersales/${row.id}`, {}, true)
  current.value = data
  note.value = ''
  show.value = true
}

const canReview = computed(() => current.value?.status === 'pending_review')
const canMarkReturned = computed(() => current.value?.status === 'awaiting_return')
const canForceBalance = computed(
  () =>
    current.value &&
    ['pending_review', 'awaiting_return', 'pending_refund'].includes(current.value.status) &&
    current.value.refund_to === 'original',
)

async function approve() {
  if (!current.value) return
  busy.value = true
  try {
    await api(
      `/api/v1/admin/aftersales/${current.value.id}/review`,
      { method: 'POST', body: JSON.stringify({ action: 'approve', admin_note: note.value.trim() }) },
      true,
    )
    message.success('已通過')
    show.value = false
    await load()
  } catch (e: any) {
    message.error(e?.message || '審核失敗')
  } finally {
    busy.value = false
  }
}

async function reject() {
  if (!current.value) return
  if (!note.value.trim()) {
    message.warning('駁回請填寫審核備註')
    return
  }
  busy.value = true
  try {
    await api(
      `/api/v1/admin/aftersales/${current.value.id}/review`,
      {
        method: 'POST',
        body: JSON.stringify({ action: 'reject', admin_note: note.value.trim() }),
      },
      true,
    )
    message.success('已駁回')
    show.value = false
    await load()
  } catch (e: any) {
    message.error(e?.message || '駁回失敗')
  } finally {
    busy.value = false
  }
}

async function markReturned() {
  if (!current.value) return
  busy.value = true
  try {
    await api(`/api/v1/admin/aftersales/${current.value.id}/mark-returned`, { method: 'POST', body: '{}' }, true)
    message.success('已確認退貨並退款')
    show.value = false
    await load()
  } catch (e: any) {
    message.error(e?.message || '操作失敗')
  } finally {
    busy.value = false
  }
}

async function forceBalance() {
  if (!current.value) return
  busy.value = true
  try {
    await api(
      `/api/v1/admin/aftersales/${current.value.id}/force-balance`,
      {
        method: 'POST',
        body: JSON.stringify({ admin_note: note.value.trim() || '強制退至餘額' }),
      },
      true,
    )
    message.success('已強制退至餘額')
    show.value = false
    await load()
  } catch (e: any) {
    message.error(e?.message || '操作失敗')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <n-card title="售後審核">
    <n-space style="margin-bottom: 1rem">
      <n-select v-model:value="status" :options="options" style="width: 10rem" @update:value="load" />
      <n-select v-model:value="hasEvidence" :options="evidenceOptions" style="width: 9rem" @update:value="load" />
      <n-button @click="load">刷新</n-button>
    </n-space>
    <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>單號</th>
          <th>訂單</th>
          <th>類型</th>
          <th>金額</th>
          <th>證據</th>
          <th>狀態</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in paged" :key="row.id" v-bind="rowAttrs(row.id)">
          <td>{{ row.aftersale_no }}</td>
          <td>
            <n-button text type="primary" @click="router.push(`/sales/orders/${row.order_id}`)">
              #{{ row.order_id }}
            </n-button>
          </td>
          <td>{{ typeLabel(row.type) }}</td>
          <td>{{ row.amount }}</td>
          <td>
            <button
              v-if="row.evidence?.length"
              type="button"
              class="ev"
              title="進入審核"
              @click="openDetail(row)"
            >
              <img
                v-for="(u, i) in row.evidence.slice(0, 3)"
                :key="i"
                :src="assetUrl(u)"
                alt=""
              />
              <span v-if="(row.evidence_count || row.evidence.length) > 3" class="more">
                +{{ (row.evidence_count || row.evidence.length) - 3 }}
              </span>
            </button>
            <n-tag v-else size="small" type="warning">無圖</n-tag>
          </td>
          <td><n-tag size="small">{{ statusLabel(row.status) }}</n-tag></td>
          <td>
            <n-button size="tiny" type="primary" @click="openDetail(row)">審核</n-button>
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

  <n-modal
    v-model:show="show"
    preset="card"
    :title="current ? `售後審核 ${current.aftersale_no}` : '售後審核'"
    style="width: min(40rem, 96vw)"
    :mask-closable="!busy"
    :closable="!busy"
  >
    <n-space v-if="current" vertical size="large">
      <div class="meta">
        <div>類型：{{ typeLabel(current.type) }} · 金額 {{ current.amount }} · {{ current.refund_to }}</div>
        <div>狀態：{{ statusLabel(current.status) }}</div>
        <div v-if="current.item">商品：{{ current.item.name['zh-HK'] }}</div>
        <div>
          訂單：
          <n-button text type="primary" @click="router.push(`/sales/orders/${current.order_id}`)">
            #{{ current.order_id }} {{ current.order_no || '' }}
          </n-button>
        </div>
      </div>

      <div>
        <strong>問題描述</strong>
        <p class="desc">{{ current.description || '—' }}</p>
      </div>

      <div>
        <strong>證據（{{ current.evidence?.length || 0 }}）</strong>
        <n-image-group v-if="current.evidence?.length">
          <div class="ev-large">
            <n-image
              v-for="(u, i) in current.evidence"
              :key="i"
              class="thumb"
              :src="assetUrl(u)"
              :width="72"
              :height="72"
              object-fit="cover"
              :alt="`證據 ${i + 1}`"
            />
          </div>
        </n-image-group>
        <p v-else class="warn">會員未上傳證據。退貨退款不可直接通過。</p>
      </div>

      <div v-if="current.admin_note">
        <strong>既有備註</strong>
        <p class="desc">{{ current.admin_note }}</p>
      </div>

      <div v-if="canReview || canForceBalance">
        <strong>審核備註</strong>
        <n-input
          v-model:value="note"
          type="textarea"
          :rows="3"
          placeholder="駁回必填；通過可選"
          style="margin-top: 0.5rem"
        />
      </div>

      <n-space>
        <n-button v-if="canReview" type="primary" :loading="busy" @click="approve">通過</n-button>
        <n-button v-if="canReview" type="error" :loading="busy" @click="reject">駁回</n-button>
        <n-button v-if="canMarkReturned" type="primary" :loading="busy" @click="markReturned">
          確認退貨並退款
        </n-button>
        <n-button v-if="canForceBalance" :loading="busy" @click="forceBalance">強制退至餘額</n-button>
      </n-space>
    </n-space>
  </n-modal>
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
.ev {
  display: flex;
  gap: 0.35rem;
  align-items: center;
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  font: inherit;
  color: inherit;
  cursor: pointer;
}
.ev img {
  width: 2.5rem;
  height: 2.5rem;
  object-fit: cover;
  border-radius: 0.25rem;
  pointer-events: none;
}
.more {
  font-size: 0.75rem;
  color: #6d675f;
}
.meta {
  font-size: 0.875rem;
  color: #3d3a35;
  line-height: 1.6;
}
.desc {
  margin: 0.35rem 0 0;
  white-space: pre-wrap;
  font-size: 0.875rem;
}
.warn {
  color: #b45309;
  font-size: 0.875rem;
}
.ev-large {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-top: 0.5rem;
}
.ev-large :deep(.n-image) {
  border-radius: 0.25rem;
  overflow: hidden;
  background: #f5f3ef;
}
.ev-large :deep(img) {
  cursor: zoom-in;
}

.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
</style>
