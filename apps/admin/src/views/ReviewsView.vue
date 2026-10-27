<script setup lang="ts">
import { showError } from '../feedback'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import {
  NButton,
  NCard,
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

type Row = {
  id: number
  product_code: string
  stars: number
  author: string
  body: string
  images: string[]
  follow?: string | null
  reply?: string | null
  admin_reply?: string | null
  replied_at?: string | null
  is_hidden?: number
  moderation_status?: string
  created_at: string
  order_id?: number
  re_review_requested_at?: string | null
  re_reviewed_at?: string | null
  can_request_re_review?: boolean
  within_re_review_window?: boolean
  re_review_days?: number
}

const route = useRoute()
const message = useMessage()
const rows = ref<Row[]>([])
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const { rowAttrs } = useTableRowSelect()
const hiddenFilter = ref<string>(
  typeof route.query.is_hidden === 'string' ? route.query.is_hidden : '',
)
const modFilter = ref<string>(
  typeof route.query.moderation_status === 'string' ? route.query.moderation_status : 'pending',
)
const productCode = ref(typeof route.query.product_code === 'string' ? route.query.product_code : '')
const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'
function assetUrl(u: string) {
  return u.startsWith('http') ? u : `${apiBase}${u}`
}

const hiddenOptions = [
  { label: '全部可見性', value: '' },
  { label: '顯示中', value: '0' },
  { label: '已隱藏', value: '1' },
]

const modOptions = [
  { label: '全部審核', value: '' },
  { label: '待審核', value: 'pending' },
  { label: '已通過', value: 'approved' },
  { label: '已拒絕', value: 'rejected' },
]

const show = ref(false)
const current = ref<Row | null>(null)
const replyText = ref('')
const busy = ref(false)

const isHidden = computed(() => Number(current.value?.is_hidden || 0) === 1)
const modStatus = computed(() => current.value?.moderation_status || 'pending')

function modTagType(s?: string): 'warning' | 'success' | 'error' | 'default' {
  if (s === 'approved') return 'success'
  if (s === 'rejected') return 'error'
  if (s === 'pending') return 'warning'
  return 'default'
}

function modLabel(s?: string) {
  if (s === 'approved') return '已通過'
  if (s === 'rejected') return '已拒絕'
  return '待審核'
}

async function load() {
  const qs = new URLSearchParams()
  if (hiddenFilter.value !== '') qs.set('is_hidden', hiddenFilter.value)
  if (modFilter.value !== '') qs.set('moderation_status', modFilter.value)
  if (productCode.value.trim()) qs.set('product_code', productCode.value.trim())
  const q = qs.toString() ? `?${qs}` : ''
  const data = await api<{ items: Row[] }>(`/api/v1/admin/reviews${q}`, {}, true)
  rows.value = data.items
}

onMounted(load)

async function openDetail(row: Row) {
  const data = await api<Row>(`/api/v1/admin/reviews/${row.id}`, {}, true)
  current.value = data
  replyText.value = data.admin_reply || data.reply || ''
  show.value = true
}

async function toggleHidden() {
  if (!current.value) return
  busy.value = true
  try {
    const next = isHidden.value ? 0 : 1
    current.value = await api<Row>(
      `/api/v1/admin/reviews/${current.value.id}/visibility`,
      { method: 'POST', body: JSON.stringify({ is_hidden: next }) },
      true,
    )
    message.success(next ? '已隱藏（前台不顯示）' : '已恢復顯示')
    await load()
  } catch (e: any) {
    showError(e?.message || '操作失敗')
  } finally {
    busy.value = false
  }
}

async function setModeration(status: 'approved' | 'rejected') {
  if (!current.value) return
  busy.value = true
  try {
    current.value = await api<Row>(
      `/api/v1/admin/reviews/${current.value.id}/moderate`,
      { method: 'POST', body: JSON.stringify({ status }) },
      true,
    )
    message.success(status === 'approved' ? '已通過審核' : '已拒絕')
    await load()
  } catch (e: any) {
    showError(e?.message || '審核失敗')
  } finally {
    busy.value = false
  }
}

async function saveReply() {
  if (!current.value) return
  if (!replyText.value.trim()) {
    message.warning('請填寫商家回覆')
    return
  }
  busy.value = true
  try {
    current.value = await api<Row>(
      `/api/v1/admin/reviews/${current.value.id}/reply`,
      { method: 'POST', body: JSON.stringify({ reply: replyText.value.trim() }) },
      true,
    )
    message.success('回覆已保存')
    await load()
  } catch (e: any) {
    showError(e?.message || '保存失敗')
  } finally {
    busy.value = false
  }
}

async function requestReReview() {
  if (!current.value) return
  busy.value = true
  try {
    current.value = await api<Row>(
      `/api/v1/admin/reviews/${current.value.id}/request-re-review`,
      { method: 'POST', body: '{}' },
      true,
    )
    message.success('已發起重評，用戶可在訂單中重新評價')
    await load()
  } catch (e: any) {
    showError(e?.message || '發起重評失敗')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <n-card title="評價管理">
    <n-space style="margin-bottom: 1rem">
      <n-select v-model:value="modFilter" :options="modOptions" style="width: 9rem" @update:value="load" />
      <n-select v-model:value="hiddenFilter" :options="hiddenOptions" style="width: 9rem" @update:value="load" />
      <n-input
        v-model:value="productCode"
        placeholder="商品代碼"
        style="width: 10rem"
        @keyup.enter="load"
      />
      <n-button @click="load">刷新</n-button>
    </n-space>

    <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>商品</th>
          <th>作者</th>
          <th>星級</th>
          <th>內容</th>
          <th>審核</th>
          <th>可見性</th>
          <th>回覆</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in paged" :key="row.id" v-bind="rowAttrs(row.id)">
          <td>{{ row.product_code }}</td>
          <td>{{ row.author }}</td>
          <td>{{ row.stars }}★</td>
          <td class="snip">{{ row.body }}</td>
          <td>
            <n-tag size="small" :type="modTagType(row.moderation_status)">
              {{ modLabel(row.moderation_status) }}
            </n-tag>
          </td>
          <td>
            <n-tag size="small" :type="row.is_hidden ? 'warning' : 'success'">
              {{ row.is_hidden ? '已隱藏' : '顯示中' }}
            </n-tag>
          </td>
          <td>{{ row.reply || row.admin_reply ? '有' : '—' }}</td>
          <td><n-button size="tiny" type="primary" @click="openDetail(row)">審核</n-button></td>
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
    title="評價管理"
    style="width: min(36rem, 96vw)"
    :mask-closable="false"
    :closable="!busy"
    :auto-focus="false"
   :close-on-esc="false">
    <n-space v-if="current" vertical size="large">
      <div class="meta">
        <div>{{ current.product_code }} · {{ current.author }} · {{ current.stars }}★</div>
        <div>{{ current.created_at }}</div>
        <n-space>
          <n-tag size="small" :type="modTagType(modStatus)">{{ modLabel(modStatus) }}</n-tag>
          <n-tag size="small" :type="isHidden ? 'warning' : 'success'">
            {{ isHidden ? '已隱藏' : '顯示中' }}
          </n-tag>
          <n-tag v-if="current.re_review_requested_at" size="small" type="info">待用戶重評</n-tag>
          <n-tag v-else-if="current.re_reviewed_at" size="small">已重評</n-tag>
        </n-space>
        <p v-if="current.re_review_requested_at" class="hint">
          已於 {{ current.re_review_requested_at }} 發起重評（首評起 {{ current.re_review_days || 30 }} 天內有效）
        </p>
        <p v-else-if="current.re_reviewed_at" class="hint">用戶已於 {{ current.re_reviewed_at }} 完成重評</p>
      </div>
      <div>
        <strong>主評</strong>
        <p class="body">{{ current.body }}</p>
        <div v-if="current.images?.length" class="imgs">
          <a v-for="(u, i) in current.images" :key="i" :href="assetUrl(u)" target="_blank" rel="noreferrer">
            <img :src="assetUrl(u)" alt="" />
          </a>
        </div>
      </div>
      <div v-if="current.follow">
        <strong>追評</strong>
        <p class="body">{{ current.follow }}</p>
      </div>
      <div>
        <strong>商家回覆</strong>
        <n-input
          v-model:value="replyText"
          type="textarea"
          :rows="3"
          placeholder="回覆將顯示在商品詳情評價區（審核通過後）"
          style="margin-top: 0.5rem"
        />
      </div>
      <n-space>
        <n-button
          v-if="modStatus !== 'approved'"
          type="primary"
          :loading="busy"
          @click="setModeration('approved')"
        >
          通過審核
        </n-button>
        <n-button
          v-if="modStatus !== 'rejected'"
          type="warning"
          :loading="busy"
          @click="setModeration('rejected')"
        >
          拒絕
        </n-button>
        <n-button type="primary" secondary :loading="busy" @click="saveReply">保存回覆</n-button>
        <n-button :loading="busy" @click="toggleHidden">{{ isHidden ? '恢復顯示' : '隱藏評價' }}</n-button>
        <n-button
          v-if="current.can_request_re_review"
          type="info"
          secondary
          :loading="busy"
          @click="requestReReview"
        >
          發起重評
        </n-button>
      </n-space>
      <p v-if="!current.can_request_re_review && !current.re_review_requested_at && current.within_re_review_window === false" class="hint">
        已超過 {{ current.re_review_days || 30 }} 天，無法再發起重評
      </p>
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
.snip {
  max-width: 14rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.meta {
  font-size: 0.875rem;
  line-height: 1.6;
}
.body {
  margin: 0.35rem 0 0;
  white-space: pre-wrap;
  font-size: 0.875rem;
}
.imgs {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
}
.imgs img {
  width: 4rem;
  height: 4rem;
  object-fit: cover;
  border-radius: 0.25rem;
}

.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
.hint {
  margin: 0.35rem 0 0;
  color: #8a8378;
  font-size: 0.75rem;
}
</style>
