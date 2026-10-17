<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { NButton, NCard, NFormItem, NInput, NInputNumber, NSpace, NSwitch, useMessage } from 'naive-ui'
import { api } from '../api'

type Article = {
  id: number
  code: string
  status: number
  show_in_footer: boolean
  sort: number
  title: { 'zh-HK': string; en: string }
  body: { 'zh-HK': string; en: string }
}

const message = useMessage()
const items = ref<Article[]>([])
const form = ref({
  id: 0,
  code: '',
  status: true,
  show_in_footer: true,
  sort: 0,
  title_zh: '',
  title_en: '',
  body_zh: '',
  body_en: '',
})

async function load() {
  const data = await api<{ items: Article[] }>('/api/v1/admin/articles', {}, true)
  items.value = data.items
}

function edit(row: Article) {
  form.value = {
    id: row.id,
    code: row.code,
    status: row.status === 1,
    show_in_footer: row.show_in_footer,
    sort: row.sort,
    title_zh: row.title['zh-HK'],
    title_en: row.title.en,
    body_zh: row.body['zh-HK'],
    body_en: row.body.en,
  }
}

async function save() {
  const body = {
    code: form.value.code,
    status: form.value.status ? 1 : 0,
    show_in_footer: form.value.show_in_footer,
    sort: form.value.sort,
    title: { 'zh-HK': form.value.title_zh, en: form.value.title_en },
    body: { 'zh-HK': form.value.body_zh, en: form.value.body_en },
  }
  if (form.value.id) {
    await api(`/api/v1/admin/articles/${form.value.id}`, { method: 'PUT', body: JSON.stringify(body) }, true)
  } else {
    await api('/api/v1/admin/articles', { method: 'POST', body: JSON.stringify(body) }, true)
  }
  message.success('文章已保存')
  await load()
}

onMounted(load)
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="文章列表">
      <div v-for="row in items" :key="row.id" class="row">
        <span>{{ row.code }} · {{ row.title['zh-HK'] }}</span>
        <n-button size="tiny" @click="edit(row)">編輯</n-button>
      </div>
    </n-card>
    <n-card :title="form.id ? `編輯 #${form.id}` : '新增文章'">
      <n-form-item label="代碼"><n-input v-model:value="form.code" /></n-form-item>
      <n-form-item label="繁中標題"><n-input v-model:value="form.title_zh" /></n-form-item>
      <n-form-item label="英文標題"><n-input v-model:value="form.title_en" /></n-form-item>
      <n-form-item label="繁中正文"><n-input v-model:value="form.body_zh" type="textarea" :rows="4" /></n-form-item>
      <n-form-item label="英文正文"><n-input v-model:value="form.body_en" type="textarea" :rows="4" /></n-form-item>
      <n-form-item label="排序"><n-input-number v-model:value="form.sort" /></n-form-item>
      <n-form-item label="發布"><n-switch v-model:value="form.status" /></n-form-item>
      <n-form-item label="頁腳展示"><n-switch v-model:value="form.show_in_footer" /></n-form-item>
      <n-button type="primary" @click="save">保存</n-button>
    </n-card>
  </n-space>
</template>

<style scoped>
.row {
  display: flex;
  justify-content: space-between;
  padding: 0.4rem 0;
  border-bottom: 1px solid #eee;
}
</style>
