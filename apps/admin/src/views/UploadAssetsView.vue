<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { NButton, NCard, NSpace, useDialog, useMessage } from 'naive-ui'
import { api } from '../api'
import { assetUrl } from '../utils/assetUrl'

type Group = {
  domain: string
  asset_type: string
  scene: string
  label: string
  group: string
  count: number
  bytes: number
}

type Recent = {
  id: number
  path: string
  scene: string
  label: string
  uploader_type: string
  uploader_id: number
  bytes: number
  width: number | null
  height: number | null
  created_at: string
}

type Orphan = {
  id: number
  path: string
  label: string
  bytes: number
  orphaned_at: string
  eligible: boolean
  days_left: number
}

type ScanResult = {
  grace_days: number
  newly_orphaned: number
  restored: number
  orphaned_count: number
  eligible_count: number
  eligible_bytes: number
  items: Orphan[]
}

const message = useMessage()
const dialog = useDialog()
const totalCount = ref(0)
const totalBytes = ref(0)
const groups = ref<Group[]>([])
const recent = ref<Recent[]>([])
const scanning = ref(false)
const purging = ref(false)
const migrating = ref(false)
const scanResult = ref<ScanResult | null>(null)

type MigrateResult = {
  migrated: number
  rewritten: number
  skipped_missing: number
  skipped: number
  removed_old: number
  bytes_copied: number
  has_more: boolean
}

function formatBytes(n: number) {
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / (1024 * 1024)).toFixed(2)} MB`
}

function who(row: Recent) {
  const kind = row.uploader_type === 'member' ? '會員' : '管理員'
  return row.uploader_id > 0 ? `${kind} #${row.uploader_id}` : kind
}

async function load() {
  const data = await api<{
    total_count: number
    total_bytes: number
    groups: Group[]
    recent: Recent[]
  }>('/api/v1/admin/settings/upload-assets', {}, true)
  totalCount.value = data.total_count
  totalBytes.value = data.total_bytes
  groups.value = data.groups
  recent.value = data.recent
}

async function scan() {
  scanning.value = true
  try {
    scanResult.value = await api<ScanResult>('/api/v1/admin/settings/upload-assets/scan', { method: 'POST' }, true)
    const row = scanResult.value
    message.success(`掃描完成：新標未引用 ${row.newly_orphaned}，恢復使用中 ${row.restored}`)
    await load()
  } finally {
    scanning.value = false
  }
}

function confirmMigrate() {
  if (migrating.value) return
  dialog.warning({
    title: '整理仍在使用的舊圖？',
    content: '把仍被引用的舊目錄圖片原樣複製到對應場景目錄，並改寫地址。不會重新壓縮。沒人再用的舊檔才會刪掉。',
    positiveText: '開始整理',
    negativeText: '取消',
    onPositiveClick: () => {
      void runMigrate()
    },
  })
}

async function runMigrate() {
  migrating.value = true
  const sum = { migrated: 0, rewritten: 0, removed_old: 0, bytes_copied: 0, skipped: 0, skipped_missing: 0 }
  try {
    for (let round = 0; round < 500; round++) {
      const res = await api<MigrateResult>(
        '/api/v1/admin/settings/upload-assets/migrate',
        { method: 'POST', body: JSON.stringify({ confirm: true }) },
        true,
      )
      sum.migrated += res.migrated
      sum.rewritten += res.rewritten
      sum.removed_old += res.removed_old
      sum.bytes_copied += res.bytes_copied
      sum.skipped += res.skipped
      sum.skipped_missing = res.skipped_missing
      if (!res.has_more || res.migrated < 1) break
    }
    const extra = [
      sum.removed_old > 0 ? `刪除已無引用的舊檔 ${sum.removed_old}` : '',
      sum.skipped_missing > 0 ? `找不到檔案 ${sum.skipped_missing}` : '',
      sum.skipped > 0 ? `略過 ${sum.skipped}` : '',
    ].filter(Boolean)
    message.success(
      `已整理 ${sum.migrated} 張（${formatBytes(sum.bytes_copied)}）${extra.length ? '，' + extra.join('，') : ''}`,
    )
    await load()
  } finally {
    migrating.value = false
  }
}

function confirmPurge() {
  const row = scanResult.value
  if (!row || row.eligible_count < 1 || purging.value) return
  dialog.warning({
    title: '刪除已滿 30 天的未引用圖片？',
    content: `將刪除 ${row.eligible_count} 張、約 ${formatBytes(row.eligible_bytes)}。仍被使用的圖不會刪。`,
    positiveText: '刪除',
    negativeText: '取消',
    onPositiveClick: async () => {
      purging.value = true
      try {
        const res = await api<{ deleted_count: number; deleted_bytes: number }>(
          '/api/v1/admin/settings/upload-assets/purge',
          { method: 'POST', body: JSON.stringify({ confirm: true }) },
          true,
        )
        message.success(`已刪除 ${res.deleted_count} 張（${formatBytes(res.deleted_bytes)}）`)
        scanResult.value = null
        await load()
      } finally {
        purging.value = false
      }
    },
  })
}

onMounted(load)
</script>

<template>
  <div class="page">
    <n-card title="上傳素材概覽">
      <p class="lead">
        只統計這個索引上線之後的新上傳，以及從舊目錄整理過來的圖。未引用的新圖要滿 30 天，並在下方確認後才會刪。
      </p>
      <div class="stats">
        <div>
          <span class="stat-label">新上傳</span>
          <strong>{{ totalCount }} 張</strong>
        </div>
        <div>
          <span class="stat-label">合計體積</span>
          <strong>{{ formatBytes(totalBytes) }}</strong>
        </div>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>場景</th>
              <th>張數</th>
              <th>體積</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!groups.length">
              <td colspan="3" class="muted">尚無新上傳</td>
            </tr>
            <tr v-for="row in groups" :key="row.scene">
              <td>
                <div class="name">{{ row.label }}</div>
                <div class="muted">{{ row.group }} · {{ row.scene }}</div>
              </td>
              <td>{{ row.count }}</td>
              <td>{{ formatBytes(row.bytes) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </n-card>

    <n-card title="舊圖整理">
      <p class="lead">
        仍被商品、分類、廣告、文章或會員資料引用、但還放在舊目錄的圖片，會原樣複製到對應場景目錄並改寫地址。不會重新壓縮。沒人引用的舊檔不會複製；舊檔要等所有引用都改完才刪。
      </p>
      <n-button :loading="migrating" @click="confirmMigrate">整理仍在使用的舊圖</n-button>
    </n-card>

    <n-card title="未引用圖片">
      <p class="lead">
        掃描商品、分類、廣告、文章、會員等資料裡仍出現的圖片地址。索引中沒被用到的新圖會標成未引用，滿 {{ scanResult?.grace_days ?? 30 }} 天後才能刪。
      </p>
      <n-space>
        <n-button :loading="scanning" @click="scan">掃描未引用</n-button>
        <n-button
          type="error"
          secondary
          :loading="purging"
          :disabled="!scanResult || scanResult.eligible_count < 1"
          @click="confirmPurge"
        >
          刪除已滿 30 天的未引用圖片
        </n-button>
      </n-space>
      <p v-if="scanResult" class="scan-note">
        未引用 {{ scanResult.orphaned_count }} 張，其中可刪除 {{ scanResult.eligible_count }} 張（{{ formatBytes(scanResult.eligible_bytes) }}）。
      </p>
      <div v-if="scanResult" class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>場景</th>
              <th>路徑</th>
              <th>體積</th>
              <th>狀態</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!scanResult.items.length">
              <td colspan="4" class="muted">沒有未引用的新圖</td>
            </tr>
            <tr v-for="row in scanResult.items" :key="row.id">
              <td>{{ row.label }}</td>
              <td class="mono">{{ row.path }}</td>
              <td>{{ formatBytes(row.bytes) }}</td>
              <td>{{ row.eligible ? '可刪除' : `還需 ${row.days_left} 天` }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </n-card>

    <n-card title="最近上傳">
      <div class="table-wrap">
        <table class="table recent">
          <thead>
            <tr>
              <th>預覽</th>
              <th>場景</th>
              <th>路徑</th>
              <th>體積</th>
              <th>上傳者</th>
              <th>時間</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!recent.length">
              <td colspan="6" class="muted">尚無新上傳</td>
            </tr>
            <tr v-for="row in recent" :key="row.id">
              <td>
                <img class="thumb" :src="assetUrl(row.path)" alt="" />
              </td>
              <td>{{ row.label }}</td>
              <td class="mono">{{ row.path }}</td>
              <td>{{ formatBytes(row.bytes) }}</td>
              <td>{{ who(row) }}</td>
              <td>{{ row.created_at }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </n-card>
  </div>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.lead {
  margin: 0 0 1rem;
  color: #666;
  font-size: 0.875rem;
  line-height: 1.5;
}
.stats {
  display: flex;
  gap: 1.5rem;
  margin-bottom: 1rem;
}
.stat-label {
  display: block;
  color: #888;
  font-size: 0.75rem;
}
.table-wrap {
  width: 100%;
  overflow-x: auto;
}
.table {
  width: 100%;
  min-width: 900px;
  border-collapse: collapse;
  background: #fff;
}
th,
td {
  border-bottom: 1px solid #eee;
  padding: 0.45rem;
  text-align: left;
  font-size: 0.8125rem;
  vertical-align: middle;
}
.name {
  font-weight: 600;
}
.muted {
  color: #888;
  font-size: 0.75rem;
}
.mono {
  font-family: ui-monospace, monospace;
  font-size: 0.75rem;
  color: #555;
  word-break: break-all;
}
.thumb {
  width: 2.5rem;
  height: 2.5rem;
  object-fit: cover;
  border-radius: 0.25rem;
  background: #f3f0ea;
  display: block;
}
.scan-note {
  margin: 0.75rem 0 0;
  color: #666;
  font-size: 0.8125rem;
}
.table-wrap + .table-wrap,
.scan-note + .table-wrap {
  margin-top: 0.75rem;
}
</style>
