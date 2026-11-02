<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { NButton, NCard, NInputNumber, NSelect, useMessage } from 'naive-ui'
import { api } from '../api'

type Row = {
  key: string
  label: string
  group: string
  bucket: string
  format: 'keep' | 'jpg' | 'png'
  max_edge: number
  max_upload_mb: number
  max_kb: number
  quality: number
}

const message = useMessage()
const rows = ref<Row[]>([])
const saving = ref(false)
const formatOptions = [
  { label: '保持原格式', value: 'keep' },
  { label: 'JPG', value: 'jpg' },
  { label: 'PNG', value: 'png' },
]

onMounted(async () => {
  const data = await api<{ items: Row[] }>('/api/v1/admin/settings/image-upload', {}, true)
  rows.value = data.items
})

async function save() {
  saving.value = true
  try {
    const data = await api<{ items: Row[] }>(
      '/api/v1/admin/settings/image-upload',
      { method: 'PUT', body: JSON.stringify({ items: rows.value }) },
      true,
    )
    rows.value = data.items
    message.success('圖片上傳配置已保存')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <n-card title="圖片上傳配置">
    <p class="lead">
      各場景獨立：輸出格式、最長邊（等比縮小、不裁切）、上傳上限、落盤體積、質量。H5
      與後台上傳都不能在請求裡覆蓋。選「保持原格式」時 PNG／WebP 保留原類型，其餘轉 JPG。舊圖不重壓、不搬遷。
    </p>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>場景</th>
            <th>目錄</th>
            <th>格式</th>
            <th>最長邊</th>
            <th>上傳 MB</th>
            <th>落盤 KB</th>
            <th>質量</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.key">
            <td>
              <div class="name">{{ row.label }}</div>
              <div class="muted">{{ row.group }}</div>
            </td>
            <td class="mono">{{ row.bucket }}</td>
            <td>
              <n-select v-model:value="row.format" :options="formatOptions" size="small" style="width: 8.5rem" />
            </td>
            <td>
              <n-input-number v-model:value="row.max_edge" :min="200" :max="4096" size="small" style="width: 7rem" />
            </td>
            <td>
              <n-input-number
                v-model:value="row.max_upload_mb"
                :min="0.1"
                :max="20"
                :step="0.5"
                size="small"
                style="width: 6.5rem"
              />
            </td>
            <td>
              <n-input-number v-model:value="row.max_kb" :min="50" :max="8192" size="small" style="width: 6.5rem" />
            </td>
            <td>
              <n-input-number v-model:value="row.quality" :min="40" :max="95" size="small" style="width: 5.5rem" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <n-button type="primary" :loading="saving" style="margin-top: 1rem" @click="save">保存</n-button>
  </n-card>
</template>

<style scoped>
.lead {
  margin: 0 0 1rem;
  color: #666;
  font-size: 0.875rem;
  line-height: 1.5;
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
}
</style>
