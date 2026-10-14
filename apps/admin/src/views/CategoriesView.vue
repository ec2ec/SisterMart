<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { NButton, NCard, NFormItem, NInput, NInputNumber, NSelect, NSpace, NSwitch, useMessage } from 'naive-ui'
import { api } from '../api'

type Cat = {
  id: number
  code: string
  parent_id: number | null
  icon: string | null
  sort: number
  status: number
  name: { 'zh-HK': string; en: string }
  children: Cat[]
}

const message = useMessage()
const items = ref<Cat[]>([])
const form = ref({
  id: 0,
  code: '',
  parent_id: null as number | null,
  icon: '',
  sort: 0,
  status: true,
  name_zh: '',
  name_en: '',
})

function flatten(nodes: Cat[], depth = 0): Array<Cat & { label: string }> {
  const out: Array<Cat & { label: string }> = []
  for (const n of nodes) {
    out.push({ ...n, label: `${'— '.repeat(depth)}${n.name['zh-HK']} (${n.code})` })
    out.push(...flatten(n.children || [], depth + 1))
  }
  return out
}

const flat = ref<Array<Cat & { label: string }>>([])
const parentOptions = ref<Array<{ label: string; value: number | null }>>([])

async function load() {
  const data = await api<{ items: Cat[] }>('/api/v1/admin/categories', {}, true)
  items.value = data.items
  flat.value = flatten(data.items)
  parentOptions.value = [{ label: '（一級分類）', value: null }, ...flat.value.map((c) => ({ label: c.label, value: c.id }))]
}

function edit(row: Cat) {
  form.value = {
    id: row.id,
    code: row.code,
    parent_id: row.parent_id,
    icon: row.icon || '',
    sort: row.sort,
    status: row.status === 1,
    name_zh: row.name['zh-HK'],
    name_en: row.name.en,
  }
}

function reset() {
  form.value = { id: 0, code: '', parent_id: null, icon: '', sort: 0, status: true, name_zh: '', name_en: '' }
}

async function save() {
  const body = {
    code: form.value.code,
    parent_id: form.value.parent_id,
    icon: form.value.icon,
    sort: form.value.sort,
    status: form.value.status ? 1 : 0,
    name: { 'zh-HK': form.value.name_zh, en: form.value.name_en },
  }
  if (form.value.id) {
    await api(`/api/v1/admin/categories/${form.value.id}`, { method: 'PUT', body: JSON.stringify(body) }, true)
  } else {
    await api('/api/v1/admin/categories', { method: 'POST', body: JSON.stringify(body) }, true)
  }
  message.success('分類已保存')
  reset()
  await load()
}

onMounted(load)
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="分類列表">
      <n-space vertical>
        <div v-for="row in flat" :key="row.id" class="row">
          <span>{{ row.label }}</span>
          <n-button size="tiny" @click="edit(row)">編輯</n-button>
        </div>
      </n-space>
    </n-card>
    <n-card :title="form.id ? `編輯 #${form.id}` : '新增分類'">
      <n-form-item label="代碼"><n-input v-model:value="form.code" /></n-form-item>
      <n-form-item label="上級">
        <n-select v-model:value="form.parent_id" :options="parentOptions" clearable />
      </n-form-item>
      <n-form-item label="繁中名稱"><n-input v-model:value="form.name_zh" /></n-form-item>
      <n-form-item label="英文名稱"><n-input v-model:value="form.name_en" /></n-form-item>
      <n-form-item label="圖標 URL"><n-input v-model:value="form.icon" placeholder="可留空" /></n-form-item>
      <n-form-item label="排序"><n-input-number v-model:value="form.sort" /></n-form-item>
      <n-form-item label="啟用"><n-switch v-model:value="form.status" /></n-form-item>
      <n-space>
        <n-button type="primary" @click="save">保存</n-button>
        <n-button @click="reset">清空</n-button>
      </n-space>
    </n-card>
  </n-space>
</template>

<style scoped>
.row {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.35rem 0;
  border-bottom: 1px solid #eee;
}
</style>
