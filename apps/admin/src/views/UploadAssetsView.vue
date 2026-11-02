<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { NCard } from 'naive-ui'
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

const totalCount = ref(0)
const totalBytes = ref(0)
const groups = ref<Group[]>([])
const recent = ref<Recent[]>([])

function formatBytes(n: number) {
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / (1024 * 1024)).toFixed(2)} MB`
}

function who(row: Recent) {
  const kind = row.uploader_type === 'member' ? '會員' : '管理員'
  return row.uploader_id > 0 ? `${kind} #${row.uploader_id}` : kind
}

onMounted(async () => {
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
})
</script>

<template>
  <div class="page">
    <n-card title="上傳素材概覽">
      <p class="lead">
        只統計這個索引上線之後的新上傳，按場景看張數和落盤體積。舊圖仍在原來的目錄，不會搬過來，也不在這張表裡。這裡不能刪除檔案。
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
</style>
