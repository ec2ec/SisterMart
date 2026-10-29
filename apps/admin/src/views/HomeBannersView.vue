<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NFormItem,
  NInput,
  NInputNumber,
  NSelect,
  NSpace,
  NSwitch,
  useMessage,
  NPagination,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'

type Banner = {
  id: number
  image_url: string
  title: { 'zh-HK': string; en: string }
  link_type: string
  link_value: string
  sort_order: number
  status: number
}

const message = useMessage()
const items = ref<Banner[]>([])
const { page, pageSize, total, paged } = useClientPager(items, 30)
const form = ref({
  id: 0,
  image_url: '',
  title_zh: '',
  title_en: '',
  link_type: 'none',
  link_value: '',
  sort_order: 0,
  status: true,
})

const linkTypes = [
  { label: '無跳轉', value: 'none' },
  { label: '商品 code', value: 'product' },
  { label: '分類 code', value: 'category' },
  { label: '文章 code', value: 'article' },
  { label: 'URL / 路徑', value: 'url' },
]

async function load() {
  const banners = await api<{ items: Banner[] }>('/api/v1/admin/home-banners', {}, true)
  items.value = banners.items
}

function reset() {
  form.value = {
    id: 0,
    image_url: '',
    title_zh: '',
    title_en: '',
    link_type: 'none',
    link_value: '',
    sort_order: 0,
    status: true,
  }
}

function edit(row: Banner) {
  form.value = {
    id: row.id,
    image_url: row.image_url,
    title_zh: row.title['zh-HK'],
    title_en: row.title.en,
    link_type: row.link_type,
    link_value: row.link_value || '',
    sort_order: row.sort_order,
    status: row.status === 1,
  }
}

async function save() {
  const body = {
    image_url: form.value.image_url,
    title: { 'zh-HK': form.value.title_zh, en: form.value.title_en },
    link_type: form.value.link_type,
    link_value: form.value.link_value,
    sort_order: form.value.sort_order,
    status: form.value.status ? 1 : 0,
  }
  if (form.value.id) {
    await api(`/api/v1/admin/home-banners/${form.value.id}`, { method: 'PUT', body: JSON.stringify(body) }, true)
  } else {
    await api('/api/v1/admin/home-banners', { method: 'POST', body: JSON.stringify(body) }, true)
  }
  message.success('輪播已保存')
  reset()
  await load()
}

async function remove(id: number) {
  await api(`/api/v1/admin/home-banners/${id}`, { method: 'DELETE' }, true)
  message.success('已刪除')
  if (form.value.id === id) reset()
  await load()
}

async function onFile(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  const fd = new FormData()
  fd.append('file', file)
  const data = await api<{ url: string }>('/api/v1/admin/uploads/banner', { method: 'POST', body: fd }, true)
  form.value.image_url = data.url
  message.success('圖片已上傳')
  input.value = ''
}

onMounted(load)
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="首頁輪播">
      <div v-for="row in paged" :key="row.id" class="row">
        <div class="meta">
          <img v-if="row.image_url" :src="row.image_url" alt="" />
          <div>
            <strong>#{{ row.id }} · {{ row.title['zh-HK'] || '(無標題)' }}</strong>
            <p>{{ row.link_type }} {{ row.link_value }} · sort {{ row.sort_order }} · {{ row.status === 1 ? '啟用' : '停用' }}</p>
          </div>
        </div>
        <n-space>
          <n-button size="tiny" @click="edit(row)">編輯</n-button>
          <n-button size="tiny" type="error" secondary @click="remove(row.id)">刪除</n-button>
        </n-space>
      </div>
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
    <n-card :title="form.id ? `編輯 #${form.id}` : '新增輪播'">
      <n-form-item label="圖片 URL">
        <n-input v-model:value="form.image_url" placeholder="/mock/home-banner-1.png" />
      </n-form-item>
      <n-form-item label="上傳">
        <input type="file" accept="image/jpeg,image/png,image/webp" @change="onFile" />
      </n-form-item>
      <n-form-item label="繁中標題"><n-input v-model:value="form.title_zh" /></n-form-item>
      <n-form-item label="英文標題"><n-input v-model:value="form.title_en" /></n-form-item>
      <n-form-item label="跳轉類型"><n-select v-model:value="form.link_type" :options="linkTypes" /></n-form-item>
      <n-form-item v-if="form.link_type !== 'none'" label="跳轉目標">
        <n-input v-model:value="form.link_value" placeholder="商品/分類/文章 code 或 URL" />
      </n-form-item>
      <n-form-item label="排序"><n-input-number v-model:value="form.sort_order" /></n-form-item>
      <n-form-item label="啟用"><n-switch v-model:value="form.status" /></n-form-item>
      <n-space>
        <n-button type="primary" @click="save">保存</n-button>
        <n-button v-if="form.id" @click="reset">新建</n-button>
      </n-space>
    </n-card>
  </n-space>
</template>

<style scoped>
.row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid #eee;
}
.meta {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  min-width: 0;
}
.meta img {
  width: 4.5rem;
  height: 2.5rem;
  object-fit: cover;
  border-radius: 0.25rem;
  background: #f3f3f3;
}
.meta p {
  margin: 0.15rem 0 0;
  color: #888;
  font-size: 0.8125rem;
}
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
</style>
