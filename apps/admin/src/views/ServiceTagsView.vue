<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NFormItem,
  NInput,
  NInputNumber,
  NPagination,
  NSpace,
  NSwitch,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'
import { useTableRowSelect } from '../composables/useTableRowSelect'

type Tag = {
  id: number
  code: string
  sort: number
  status: number
  name: { 'zh-HK': string; en: string }
}

const message = useMessage()
const rows = ref<Tag[]>([])
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const { rowAttrs } = useTableRowSelect()
const form = ref({
  id: 0,
  code: '',
  sort: 0,
  status: true,
  name_zh: '',
  name_en: '',
})

async function load() {
  const data = await api<{ items: Tag[] }>('/api/v1/admin/service-tags', {}, true)
  rows.value = data.items
}

function resetForm() {
  form.value = {
    id: 0,
    code: '',
    sort: rows.value.length + 1,
    status: true,
    name_zh: '',
    name_en: '',
  }
}

function edit(row: Tag) {
  form.value = {
    id: row.id,
    code: row.code,
    sort: row.sort,
    status: row.status === 1,
    name_zh: row.name['zh-HK'],
    name_en: row.name.en,
  }
}

async function save() {
  const body = {
    code: form.value.code,
    sort: form.value.sort,
    status: form.value.status ? 1 : 0,
    name: { 'zh-HK': form.value.name_zh, en: form.value.name_en },
  }
  if (form.value.id) {
    await api(`/api/v1/admin/service-tags/${form.value.id}`, { method: 'PUT', body: JSON.stringify(body) }, true)
  } else {
    await api('/api/v1/admin/service-tags', { method: 'POST', body: JSON.stringify(body) }, true)
  }
  message.success('服務標籤已保存')
  resetForm()
  await load()
}

onMounted(load)
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="服務標籤">
      <table class="table">
        <thead>
          <tr>
            <th>代碼</th>
            <th>名稱</th>
            <th>排序</th>
            <th>狀態</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in paged" :key="row.id" v-bind="rowAttrs(row.id)">
            <td>{{ row.code }}</td>
            <td>
              <div>{{ row.name['zh-HK'] }}</div>
              <div class="en">{{ row.name.en }}</div>
            </td>
            <td>{{ row.sort }}</td>
            <td>
              <n-tag size="small" :type="row.status === 1 ? 'success' : 'default'">
                {{ row.status === 1 ? '啟用' : '停用' }}
              </n-tag>
            </td>
            <td><n-button size="tiny" @click="edit(row)">編輯</n-button></td>
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
    </n-card>

    <n-card :title="form.id ? `編輯標籤 #${form.id}` : '新增服務標籤'">
      <div class="form-grid">
        <n-form-item label="代碼"><n-input v-model:value="form.code" placeholder="如 authentic" /></n-form-item>
        <n-form-item label="繁中名稱"><n-input v-model:value="form.name_zh" /></n-form-item>
        <n-form-item label="英文名稱"><n-input v-model:value="form.name_en" /></n-form-item>
        <n-form-item label="排序"><n-input-number v-model:value="form.sort" :min="0" /></n-form-item>
        <n-form-item label="啟用"><n-switch v-model:value="form.status" /></n-form-item>
      </div>
      <n-space>
        <n-button type="primary" @click="save">保存</n-button>
        <n-button v-if="form.id" @click="resetForm">取消編輯</n-button>
      </n-space>
    </n-card>
  </n-space>
</template>

<style scoped>
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
  vertical-align: middle;
}
.en {
  margin-top: 0.15rem;
  font-size: 0.75rem;
  color: #8a8378;
}
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
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
