<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NCheckbox,
  NModal,
  NProgress,
  NSpace,
  NTag,
  NText,
  useDialog,
  useMessage,
} from 'naive-ui'
import { api, ApiError } from '../api'

type ReleaseRow = {
  id: string
  status: string
  filename?: string
  total_bytes?: number
  title?: string
  description?: string
  version?: string
  has_migrate?: boolean
  created_at?: string
  updated_at?: string
  published_at?: string
  snapshot_id?: string
  error?: string | null
  chunks_received?: number | null
  chunks_expected?: number | null
}

type State = {
  enabled: boolean
  site_root?: string
  current_release_id?: string | null
  previous_snapshot_id?: string | null
  lock?: { admin_id?: number; release_id?: string; at?: string } | null
  max_bytes?: number
}

type AuditRow = {
  at: string
  action: string
  admin_id: number
  target: string
  details?: Record<string, unknown>
}

const message = useMessage()
const dialog = useDialog()
const loading = ref(false)
const uploading = ref(false)
const uploadPct = ref(0)
const items = ref<ReleaseRow[]>([])
const state = ref<State | null>(null)
const audit = ref<AuditRow[]>([])
const disabledHint = ref('')
const fileInput = ref<HTMLInputElement | null>(null)
const detailOpen = ref(false)
const detailLog = ref('')
const detailRow = ref<ReleaseRow | null>(null)
const rollbackDb = ref(false)

const CHUNK = 1024 * 1024

const statusType = (s: string) => {
  switch (s) {
    case 'ready':
      return 'success'
    case 'published':
      return 'info'
    case 'failed':
      return 'error'
    case 'publishing':
    case 'uploading':
      return 'warning'
    default:
      return 'default'
  }
}

const canRollback = computed(() => !!state.value?.previous_snapshot_id && !state.value?.lock)

async function load() {
  loading.value = true
  disabledHint.value = ''
  try {
    const data = await api<{ items: ReleaseRow[]; state: State }>('/api/v1/admin/releases', {}, true)
    items.value = data.items
    state.value = data.state
    const aud = await api<{ items: AuditRow[] }>('/api/v1/admin/releases/audit?limit=30', {}, true)
    audit.value = aud.items
  } catch (e) {
    if (e instanceof ApiError && (e.code === 'DISABLED' || e.businessCode === 50301 || e.businessCode === 50302)) {
      disabledHint.value = e.message
      items.value = []
      state.value = null
    } else {
      message.error(e instanceof Error ? e.message : '加載失敗')
    }
  } finally {
    loading.value = false
  }
}

function pickFile() {
  fileInput.value?.click()
}

async function onFile(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  if (!/\.(tar|tar\.gz|tgz)$/i.test(file.name)) {
    message.error('請上傳 pnpm release pack 產出的 .tar 包')
    return
  }
  uploading.value = true
  uploadPct.value = 0
  try {
    const buf = await file.arrayBuffer()
    const bytes = new Uint8Array(buf)
    const sha256 = await sha256Hex(bytes)
    const init = await api<{ id: string; chunk_size: number; chunks_expected: number }>(
      '/api/v1/admin/releases',
      {
        method: 'POST',
        body: JSON.stringify({
          filename: file.name,
          total_bytes: bytes.byteLength,
          chunk_size: CHUNK,
          sha256,
        }),
      },
      true,
    )
    const chunkSize = init.chunk_size || CHUNK
    const total = init.chunks_expected
    const token = localStorage.getItem('sm_admin_token') || ''
    const base = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'
    for (let n = 0; n < total; n++) {
      const slice = bytes.subarray(n * chunkSize, Math.min(bytes.byteLength, (n + 1) * chunkSize))
      const res = await fetch(`${base}/api/v1/admin/releases/${init.id}/chunks/${n}`, {
        method: 'PUT',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/octet-stream',
        },
        body: slice,
      })
      const body = await res.json().catch(() => ({}))
      if (!res.ok || body.code !== 0) {
        throw new Error(body.message || `分片 ${n} 上傳失敗`)
      }
      uploadPct.value = Math.round(((n + 1) / total) * 90)
    }
    await api(`/api/v1/admin/releases/${init.id}/complete`, { method: 'POST', body: '{}' }, true)
    uploadPct.value = 100
    message.success('上傳完成，簽名校驗通過')
    await load()
  } catch (e) {
    message.error(e instanceof Error ? e.message : '上傳失敗')
  } finally {
    uploading.value = false
  }
}

async function sha256Hex(data: Uint8Array): Promise<string> {
  const hash = await crypto.subtle.digest('SHA-256', data)
  return [...new Uint8Array(hash)].map((b) => b.toString(16).padStart(2, '0')).join('')
}

function publish(row: ReleaseRow) {
  dialog.warning({
    title: '確認發佈',
    content: `將備份當前站點後覆蓋白名單路徑${row.has_migrate ? '，並執行 migrate.sql' : ''}。\n版本：${row.version || row.id}\n${row.title || ''}`,
    positiveText: '發佈',
    negativeText: '取消',
    onPositiveClick: async () => {
      try {
        await api(`/api/v1/admin/releases/${row.id}/publish`, { method: 'POST', body: '{}' }, true)
        message.success('發佈完成')
        await load()
      } catch (e) {
        message.error(e instanceof Error ? e.message : '發佈失敗')
        await load()
      }
    },
  })
}

function rollback() {
  const snap = state.value?.previous_snapshot_id
  if (!snap) return
  const withDb = rollbackDb.value
  dialog.error({
    title: '確認回退',
    content: withDb
      ? `將還原快照 ${snap} 的文件與數據庫。數據庫還原會覆蓋發佈後的新數據。請確認輸入已理解風險。`
      : `將還原快照 ${snap} 的文件（不還原數據庫）。回退=快照還原，不是反向 SQL。`,
    positiveText: withDb ? '還原文件+數據庫' : '僅還原文件',
    negativeText: '取消',
    onPositiveClick: async () => {
      try {
        await api(
          '/api/v1/admin/releases/rollback',
          {
            method: 'POST',
            body: JSON.stringify({
              restore_db: withDb,
              confirm: withDb ? 'ROLLBACK_DB' : '',
            }),
          },
          true,
        )
        message.success('已回退')
        await load()
      } catch (e) {
        message.error(e instanceof Error ? e.message : '回退失敗')
        await load()
      }
    },
  })
}

async function remove(row: ReleaseRow) {
  dialog.warning({
    title: '刪除待發佈包',
    content: row.id,
    positiveText: '刪除',
    negativeText: '取消',
    onPositiveClick: async () => {
      await api(`/api/v1/admin/releases/${row.id}`, { method: 'DELETE' }, true)
      message.success('已刪除')
      await load()
    },
  })
}

async function openDetail(row: ReleaseRow) {
  detailRow.value = row
  detailLog.value = ''
  detailOpen.value = true
  try {
    const data = await api<{ log: string }>(`/api/v1/admin/releases/${row.id}`, {}, true)
    detailLog.value = data.log || '（無日誌）'
  } catch (e) {
    detailLog.value = e instanceof Error ? e.message : '讀取失敗'
  }
}

function fmtBytes(n?: number) {
  if (!n && n !== 0) return '—'
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / 1024 / 1024).toFixed(1)} MB`
}

onMounted(load)
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="發佈通道">
      <p class="hint">
        窄版 Release Channel：僅接受 <code>pnpm release pack</code> 簽名包。上傳後點發佈＝快照 →
        白名單覆蓋 → 可選 SQL。回退＝還原上一快照（非反向 migration）。
      </p>
      <div v-if="disabledHint" class="warn">{{ disabledHint }}</div>
      <template v-else-if="state">
        <div class="meta">
          <div><n-text depth="3">網站根</n-text> {{ state.site_root }}</div>
          <div><n-text depth="3">當前版本</n-text> {{ state.current_release_id || '—' }}</div>
          <div><n-text depth="3">上一快照</n-text> {{ state.previous_snapshot_id || '—' }}</div>
          <div v-if="state.lock"><n-tag type="warning" size="small">鎖定中 {{ state.lock.release_id }}</n-tag></div>
        </div>
        <n-space align="center" style="margin-top: 12px">
          <n-button type="primary" :loading="uploading" :disabled="!!state.lock" @click="pickFile">
            上傳發佈包
          </n-button>
          <input ref="fileInput" type="file" accept=".tar,.tar.gz,.tgz,application/x-tar" hidden @change="onFile" />
          <n-checkbox v-model:checked="rollbackDb">回退時同時還原數據庫</n-checkbox>
          <n-button type="error" secondary :disabled="!canRollback" @click="rollback">回退上一快照</n-button>
          <n-button quaternary :loading="loading" @click="load">刷新</n-button>
        </n-space>
        <n-progress v-if="uploading" type="line" :percentage="uploadPct" style="margin-top: 12px" />
      </template>
    </n-card>

    <n-card title="待發佈 / 歷史">
      <table class="tbl" v-if="items.length">
        <thead>
          <tr>
            <th>版本</th>
            <th>狀態</th>
            <th>說明</th>
            <th>大小</th>
            <th>SQL</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in items" :key="row.id">
            <td>
              <div class="ver">{{ row.version || row.id }}</div>
              <div class="sub">{{ row.id }}</div>
            </td>
            <td><n-tag size="small" :type="statusType(row.status)">{{ row.status }}</n-tag></td>
            <td>
              <div>{{ row.title || '—' }}</div>
              <div class="sub">{{ row.description }}</div>
              <div v-if="row.error" class="err">{{ row.error }}</div>
            </td>
            <td>{{ fmtBytes(row.total_bytes) }}</td>
            <td>{{ row.has_migrate ? '有' : '—' }}</td>
            <td class="acts">
              <n-button size="tiny" quaternary @click="openDetail(row)">日誌</n-button>
              <n-button
                v-if="row.status === 'ready'"
                size="tiny"
                type="primary"
                :disabled="!!state?.lock"
                @click="publish(row)"
              >
                發佈
              </n-button>
              <n-button
                v-if="row.status !== 'publishing'"
                size="tiny"
                quaternary
                type="error"
                @click="remove(row)"
              >
                刪除
              </n-button>
            </td>
          </tr>
        </tbody>
      </table>
      <n-text v-else depth="3">暫無發佈包</n-text>
    </n-card>

    <n-card title="操作審計">
      <table class="tbl" v-if="audit.length">
        <thead>
          <tr>
            <th>時間</th>
            <th>動作</th>
            <th>管理員</th>
            <th>目標</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(a, i) in audit" :key="i">
            <td>{{ a.at }}</td>
            <td>{{ a.action }}</td>
            <td>{{ a.admin_id }}</td>
            <td>{{ a.target }}</td>
          </tr>
        </tbody>
      </table>
      <n-text v-else depth="3">暫無記錄</n-text>
    </n-card>

    <n-modal v-model:show="detailOpen" preset="card" style="max-width: 640px" :title="detailRow?.id || '日誌'">
      <pre class="log">{{ detailLog }}</pre>
    </n-modal>
  </n-space>
</template>

<style scoped>
.hint {
  color: #666;
  margin: 0 0 12px;
  line-height: 1.5;
  font-size: 13px;
}
.warn {
  background: #fff7e6;
  border: 1px solid #ffd591;
  padding: 10px 12px;
  border-radius: 6px;
  color: #ad6800;
}
.meta {
  display: grid;
  gap: 4px;
  font-size: 13px;
}
.tbl {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.tbl th,
.tbl td {
  text-align: left;
  padding: 8px 6px;
  border-bottom: 1px solid #eee;
  vertical-align: top;
}
.ver {
  font-weight: 600;
}
.sub {
  color: #999;
  font-size: 12px;
  margin-top: 2px;
}
.err {
  color: #d03050;
  font-size: 12px;
  margin-top: 4px;
}
.acts {
  white-space: nowrap;
}
.log {
  max-height: 420px;
  overflow: auto;
  font-size: 12px;
  background: #f7f7f7;
  padding: 12px;
  border-radius: 6px;
  white-space: pre-wrap;
  word-break: break-all;
}
code {
  font-size: 12px;
}
</style>
