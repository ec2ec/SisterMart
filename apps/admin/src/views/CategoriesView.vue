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
  parent_id: 0,
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
const parentOptions = ref<Array<{ label: string; value: number }>>([])

async function load() {
  const data = await api<{ items: Cat[] }>('/api/v1/admin/categories', {}, true)
  items.value = data.items
  flat.value = flatten(data.items)
  parentOptions.value = [{ label: '（一級分類）', value: 0 }, ...flat.value.map((c) => ({ label: c.label, value: c.id }))]
}

function edit(row: Cat) {
  form.value = {
    id: row.id,
    code: row.code,
    parent_id: row.parent_id ?? 0,
    icon: row.icon || '',
    sort: row.sort,
    status: row.status === 1,
    name_zh: row.name['zh-HK'],
    name_en: row.name.en,
  }
}

function reset() {
  form.value = { id: 0, code: '', parent_id: 0, icon: '', sort: 0, status: true, name_zh: '', name_en: '' }
}

/** 標題旁「新增分類」：清空右側表單進入新建 */
function startCreate() {
  reset()
}

async function save() {
  const body = {
    code: form.value.code,
    parent_id: form.value.parent_id || null,
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
  <div class="split">
    <n-card title="分類列表" class="pane">
      <template #header-extra>
        <n-button type="primary" size="small" @click="startCreate">新增分類</n-button>
      </template>
      <div class="list-head">
        <span class="col-name">分類</span>
        <span class="col-status">狀態</span>
        <span class="col-action">操作</span>
      </div>
      <n-space vertical :size="0">
        <div v-for="row in flat" :key="row.id" class="row" :class="{ off: row.status !== 1 }">
          <span class="col-name">{{ row.label }}</span>
          <span class="col-status" :class="row.status === 1 ? 'on' : 'off-tag'">
            {{ row.status === 1 ? '啟用' : '未啟用' }}
          </span>
          <span class="col-action">
            <n-button size="tiny" @click="edit(row)">編輯</n-button>
          </span>
        </div>
      </n-space>
    </n-card>
    <n-card :title="form.id ? `編輯 #${form.id}` : '新增分類'" class="pane">
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
  </div>
</template>

<style scoped>
.split {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  align-items: start;
}
.pane {
  min-width: 0;
}
.list-head,
.row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 4.5rem 3.5rem;
  gap: 0.75rem;
  align-items: center;
  padding: 0.4rem 0;
  border-bottom: 1px solid #eee;
}
.list-head {
  font-size: 0.75rem;
  color: #8a8378;
  border-bottom-color: #e5e0d8;
}
.col-name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.col-status {
  text-align: center;
  font-size: 0.8125rem;
}
.col-status.on {
  color: #18a058;
}
.col-status.off-tag {
  color: #999;
}
.col-action {
  text-align: right;
}
.row.off,
.row.off .col-name,
.row.off .col-status {
  color: #b0aaa0;
}
.row.off .col-status.off-tag {
  color: #b0aaa0;
}
@media (max-width: 900px) {
  .split {
    grid-template-columns: 1fr;
  }
}
</style>
