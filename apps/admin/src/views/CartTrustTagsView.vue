<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NFormItem,
  NInput,
  NInputNumber,
  NSpace,
  NSwitch,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'

type TrustTag = {
  id: number
  code: string
  sort: number
  status: number
  name: { 'zh-HK': string; en: string }
  detail: { 'zh-HK': string; en: string }
}

const message = useMessage()
const rows = ref<TrustTag[]>([])
const busy = ref(false)
const form = ref({
  id: 0,
  code: '',
  sort: 0,
  status: true,
  name_zh: '',
  name_en: '',
  detail_zh: '',
  detail_en: '',
})

async function load() {
  const data = await api<{ items: TrustTag[] }>('/api/v1/admin/cart-trust-tags', {}, true)
  rows.value = data.items
}

function resetForm() {
  form.value = {
    id: 0,
    code: '',
    sort: (rows.value[rows.value.length - 1]?.sort ?? 0) + 1,
    status: true,
    name_zh: '',
    name_en: '',
    detail_zh: '',
    detail_en: '',
  }
}

function edit(row: TrustTag) {
  form.value = {
    id: row.id,
    code: row.code,
    sort: row.sort,
    status: row.status === 1,
    name_zh: row.name['zh-HK'],
    name_en: row.name.en,
    detail_zh: row.detail['zh-HK'],
    detail_en: row.detail.en,
  }
}

async function save() {
  if (!form.value.name_zh.trim() && !form.value.name_en.trim()) {
    message.warning('請填寫標籤名稱')
    return
  }
  busy.value = true
  try {
    const body = {
      code: form.value.code,
      sort: form.value.sort,
      status: form.value.status ? 1 : 0,
      name: { 'zh-HK': form.value.name_zh, en: form.value.name_en },
      detail: { 'zh-HK': form.value.detail_zh, en: form.value.detail_en },
    }
    if (form.value.id) {
      await api(`/api/v1/admin/cart-trust-tags/${form.value.id}`, {
        method: 'PUT',
        body: JSON.stringify(body),
      }, true)
    } else {
      await api('/api/v1/admin/cart-trust-tags', {
        method: 'POST',
        body: JSON.stringify(body),
      }, true)
    }
    message.success('購物車標籤已保存')
    resetForm()
    await load()
  } catch (e) {
    message.error(e instanceof Error ? e.message : '保存失敗')
  } finally {
    busy.value = false
  }
}

async function remove(row: TrustTag) {
  if (!confirm(`刪除標籤「${row.name['zh-HK'] || row.name.en}」？`)) return
  try {
    await api(`/api/v1/admin/cart-trust-tags/${row.id}`, { method: 'DELETE' }, true)
    message.success('已刪除')
    if (form.value.id === row.id) resetForm()
    await load()
  } catch (e) {
    message.error(e instanceof Error ? e.message : '刪除失敗')
  }
}

onMounted(async () => {
  await load()
  resetForm()
})
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="購物車頂部標籤">
      <p class="hint">H5 購物車頁頂部展示；點擊標籤會彈出詳情說明。名稱與詳情均支持繁中／英文。</p>
      <table class="table">
        <thead>
          <tr>
            <th>代碼</th>
            <th>名稱</th>
            <th>詳情摘要</th>
            <th>排序</th>
            <th>狀態</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td>{{ row.code }}</td>
            <td>
              <div>{{ row.name['zh-HK'] }}</div>
              <div class="en">{{ row.name.en }}</div>
            </td>
            <td class="detail-cell">
              <div>{{ row.detail['zh-HK'] }}</div>
              <div class="en">{{ row.detail.en }}</div>
            </td>
            <td>{{ row.sort }}</td>
            <td>
              <n-tag size="small" :type="row.status === 1 ? 'success' : 'default'">
                {{ row.status === 1 ? '啟用' : '停用' }}
              </n-tag>
            </td>
            <td>
              <n-space>
                <n-button size="tiny" @click="edit(row)">編輯</n-button>
                <n-button size="tiny" type="error" quaternary @click="remove(row)">刪除</n-button>
              </n-space>
            </td>
          </tr>
        </tbody>
      </table>
    </n-card>

    <n-card :title="form.id ? `編輯標籤 #${form.id}` : '新增購物車標籤'">
      <div class="form-grid">
        <n-form-item label="代碼">
          <n-input v-model:value="form.code" placeholder="如 authentic（可留空自動生成）" />
        </n-form-item>
        <n-form-item label="排序">
          <n-input-number v-model:value="form.sort" :min="0" />
        </n-form-item>
        <n-form-item label="啟用">
          <n-switch v-model:value="form.status" />
        </n-form-item>
        <n-form-item label="繁中名稱">
          <n-input v-model:value="form.name_zh" maxlength="32" />
        </n-form-item>
        <n-form-item label="英文名稱">
          <n-input v-model:value="form.name_en" maxlength="32" />
        </n-form-item>
      </div>
      <n-form-item label="繁中詳情（彈窗內容）">
        <n-input
          v-model:value="form.detail_zh"
          type="textarea"
          :autosize="{ minRows: 3, maxRows: 8 }"
          placeholder="點擊標籤後顯示的說明"
        />
      </n-form-item>
      <n-form-item label="英文詳情（彈窗內容）">
        <n-input
          v-model:value="form.detail_en"
          type="textarea"
          :autosize="{ minRows: 3, maxRows: 8 }"
        />
      </n-form-item>
      <n-space>
        <n-button type="primary" :loading="busy" @click="save">保存</n-button>
        <n-button v-if="form.id" @click="resetForm">取消編輯</n-button>
      </n-space>
    </n-card>
  </n-space>
</template>

<style scoped>
.hint {
  margin: 0 0 0.75rem;
  font-size: 0.8125rem;
  color: #8a8378;
}
.table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
}
th,
td {
  border-bottom: 1px solid #eee;
  padding: 0.5rem;
  text-align: left;
  font-size: 0.875rem;
  vertical-align: top;
}
.en {
  margin-top: 0.15rem;
  font-size: 0.75rem;
  color: #8a8378;
}
.detail-cell {
  max-width: 18rem;
  font-size: 0.75rem;
  color: #555;
}
.detail-cell > div {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem 1rem;
  max-width: 48rem;
}
@media (max-width: 900px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
