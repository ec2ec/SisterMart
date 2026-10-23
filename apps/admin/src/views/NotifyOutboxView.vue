<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NInput,
  NSelect,
  NSpace,
  NTag,
  NPagination,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'

type Row = {
  id: number
  channel: string
  purpose: string
  to_target: string
  subject: string | null
  body_preview: string | null
  status: string
  provider: string | null
  ref_type: string | null
  ref_id: string | null
  member_id: number | null
  error_message: string | null
  created_at: string
  sent_at: string | null
}

const rows = ref<Row[]>([])
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const channelFilter = ref<string>('')
const statusFilter = ref<string>('')
const q = ref('')
const loading = ref(false)

const channelOptions = [
  { label: '全部渠道', value: '' },
  { label: '郵件', value: 'email' },
  { label: '短信', value: 'sms' },
  { label: 'WhatsApp', value: 'whatsapp' },
]

const statusOptions = [
  { label: '全部狀態', value: '' },
  { label: '已發送', value: 'sent' },
  { label: '模擬', value: 'mock' },
  { label: '失敗', value: 'failed' },
  { label: '跳過', value: 'skipped' },
  { label: '排隊中', value: 'queued' },
]

const channelLabel: Record<string, string> = {
  email: '郵件',
  sms: '短信',
  whatsapp: 'WhatsApp',
}

const purposeLabel: Record<string, string> = {
  system_batch: '系統消息',
  mail: '郵件',
  otp_register: '註冊驗證碼',
  otp_login: '登錄驗證碼',
  otp_reset: '重置驗證碼',
  otp_bind_email: '綁定郵箱驗證碼',
  otp_bind_phone: '綁定手機驗證碼',
}

function statusType(s: string): 'success' | 'warning' | 'error' | 'info' | 'default' {
  if (s === 'sent') return 'success'
  if (s === 'mock') return 'info'
  if (s === 'failed') return 'error'
  if (s === 'skipped') return 'warning'
  return 'default'
}

function statusLabel(s: string) {
  const map: Record<string, string> = {
    sent: '已發送',
    mock: '模擬',
    failed: '失敗',
    skipped: '跳過',
    queued: '排隊中',
  }
  return map[s] || s
}

async function load() {
  loading.value = true
  try {
    const qs = new URLSearchParams()
    if (channelFilter.value) qs.set('channel', channelFilter.value)
    if (statusFilter.value) qs.set('status', statusFilter.value)
    if (q.value.trim()) qs.set('q', q.value.trim())
    const suffix = qs.toString() ? `?${qs}` : ''
    const data = await api<{ items: Row[] }>(`/api/v1/admin/notify-outbox${suffix}`, {}, true)
    rows.value = data.items
    page.value = 1
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <n-card title="發送流水">
    <n-space style="margin-bottom: 1rem" wrap>
      <n-select
        v-model:value="channelFilter"
        :options="channelOptions"
        style="width: 8rem"
        @update:value="load"
      />
      <n-select
        v-model:value="statusFilter"
        :options="statusOptions"
        style="width: 8rem"
        @update:value="load"
      />
      <n-input
        v-model:value="q"
        placeholder="收件人 / 主題 / 關鍵字"
        style="width: 16rem"
        @keyup.enter="load"
      />
      <n-button :loading="loading" @click="load">刷新</n-button>
    </n-space>
    <table class="table">
      <thead>
        <tr>
          <th>時間</th>
          <th>渠道</th>
          <th>用途</th>
          <th>收件人</th>
          <th>主題 / 摘要</th>
          <th>狀態</th>
          <th>會員</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in paged" :key="row.id">
          <td class="mono">{{ row.created_at }}</td>
          <td>{{ channelLabel[row.channel] || row.channel }}</td>
          <td>{{ purposeLabel[row.purpose] || row.purpose || '—' }}</td>
          <td>{{ row.to_target }}</td>
          <td class="preview">
            <div v-if="row.subject" class="sub">{{ row.subject }}</div>
            <div class="body">{{ row.body_preview || '—' }}</div>
            <div v-if="row.error_message" class="err">{{ row.error_message }}</div>
          </td>
          <td>
            <n-tag size="small" :type="statusType(row.status)">
              {{ statusLabel(row.status) }}
            </n-tag>
            <div v-if="row.provider" class="prov">{{ row.provider }}</div>
          </td>
          <td>{{ row.member_id ?? '—' }}</td>
        </tr>
      </tbody>
    </table>
    <div class="pager">
      <n-pagination
        v-model:page="page"
        v-model:page-size="pageSize"
        :item-count="total"
        :page-sizes="[30, 50, 100]"
        show-size-picker
      />
    </div>
    <p v-if="!rows.length && !loading" class="empty">暫無發送記錄</p>
  </n-card>
</template>

<style scoped>
.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}
th,
td {
  border-bottom: 1px solid #eee;
  padding: 0.5rem;
  text-align: left;
  vertical-align: top;
}
.mono {
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
.preview .sub {
  font-weight: 600;
  margin-bottom: 0.15rem;
}
.preview .body {
  color: #555;
  max-width: 22rem;
  word-break: break-word;
}
.preview .err {
  color: #c0392b;
  font-size: 0.75rem;
  margin-top: 0.2rem;
}
.prov {
  color: #999;
  font-size: 0.7rem;
  margin-top: 0.15rem;
}
.empty {
  color: #999;
  padding: 1rem 0;
}
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
</style>
