<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NEmpty,
  NFormItem,
  NInput,
  NInputNumber,
  NSelect,
  NSpace,
  NSwitch,
  NTag,
  useMessage,
} from 'naive-ui'
import { api, uploadImage } from '../api'
import { showError } from '../feedback'
import { assetUrl, toStoredUrl } from '../utils/assetUrl'

type ImageAdItem = {
  code: string
  title: { 'zh-HK': string; en: string }
  image_url: string
  link_type: string
  link_value: string
  status: number
  enabled: boolean
  show_on_home: number
  sort_order: number
}

const message = useMessage()
const items = ref<ImageAdItem[]>([])
const form = ref(emptyForm())
const saving = ref(false)
const uploading = ref(false)

const linkTypes = [
  { label: '無跳轉', value: 'none' },
  { label: '商品 code', value: 'product' },
  { label: '分類 code', value: 'category' },
  { label: '文章 code', value: 'article' },
  { label: 'URL / 路徑', value: 'url' },
]

function emptyForm() {
  return {
    code: '',
    title_zh: '',
    title_en: '',
    image_url: '',
    link_type: 'none',
    link_value: '',
    status: true,
    show_on_home: true,
    sort_order: 0,
    editingCode: '' as string,
  }
}

async function load() {
  const ops = await api<{ image_ads?: ImageAdItem[] }>('/api/v1/admin/home-ops', {}, true)
  items.value = ops.image_ads || []
}

function reset() {
  form.value = emptyForm()
  if (items.value.length) {
    form.value.sort_order = (items.value[items.value.length - 1]?.sort_order || 0) + 10
  }
}

function edit(row: ImageAdItem) {
  form.value = {
    code: row.code,
    title_zh: row.title?.['zh-HK'] || '',
    title_en: row.title?.en || '',
    image_url: row.image_url || '',
    link_type: row.link_type || 'none',
    link_value: row.link_value || '',
    status: row.status === 1,
    show_on_home: row.show_on_home === 1,
    sort_order: row.sort_order || 0,
    editingCode: row.code,
  }
}

function buildPayloadFromForm() {
  return {
    code: form.value.code.trim() || undefined,
    title_zh: form.value.title_zh.trim(),
    title_en: form.value.title_en.trim(),
    image_url: toStoredUrl(form.value.image_url.trim()),
    link_type: form.value.link_type,
    link_value: form.value.link_value.trim(),
    status: form.value.status ? 1 : 0,
    show_on_home: form.value.show_on_home ? 1 : 0,
    sort_order: form.value.sort_order,
  }
}

function toPersistRow(row: ImageAdItem) {
  return {
    code: row.code,
    title_zh: row.title?.['zh-HK'] || '',
    title_en: row.title?.en || '',
    image_url: toStoredUrl(row.image_url),
    link_type: row.link_type,
    link_value: row.link_value,
    status: row.status,
    show_on_home: row.show_on_home,
    sort_order: row.sort_order,
  }
}

async function save() {
  if (!form.value.title_zh.trim() && !form.value.title_en.trim()) {
    message.warning('請填寫中英文標題')
    return
  }
  if (!form.value.image_url.trim()) {
    message.warning('請上傳圖片')
    return
  }
  saving.value = true
  try {
    const next = items.value.map(toPersistRow)
    const payload = buildPayloadFromForm()
    const editing = form.value.editingCode
    if (editing) {
      const idx = next.findIndex((r) => r.code === editing)
      if (idx >= 0) {
        next[idx] = { ...payload, code: payload.code || editing }
      } else {
        next.push({ ...payload, code: payload.code || editing })
      }
    } else {
      next.push({ ...payload, code: payload.code || `ad${next.length + 1}` })
    }
    await api(
      '/api/v1/admin/home-ops',
      { method: 'PUT', body: JSON.stringify({ image_ads: next }) },
      true,
    )
    message.success('圖片廣告已保存；勾選「展示於首頁」的條目會出現在首頁佈局')
    reset()
    await load()
  } finally {
    saving.value = false
  }
}

async function remove(code: string) {
  const next = items.value.filter((r) => r.code !== code).map(toPersistRow)
  await api(
    '/api/v1/admin/home-ops',
    { method: 'PUT', body: JSON.stringify({ image_ads: next }) },
    true,
  )
  message.success('已刪除')
  if (form.value.editingCode === code) reset()
  await load()
}

async function onFile(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  uploading.value = true
  try {
    const data = await uploadImage(file, { preset: 'banner' })
    form.value.image_url = toStoredUrl(data.url)
    message.success('圖片已上傳')
  } catch (e: unknown) {
    showError(e instanceof Error ? e.message : '圖片上傳失敗')
  } finally {
    uploading.value = false
    input.value = ''
  }
}

onMounted(load)
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="圖片廣告">
      <p class="hint">
        可同時啟用多條。標題用來區分放置位置；勾選「展示於首頁」後會出現在「首頁佈局」，可再拖放排序。
      </p>
      <n-empty v-if="!items.length" description="尚未建立圖片廣告" />
      <div v-for="row in items" :key="row.code" class="row">
        <div class="meta">
          <img v-if="row.image_url" :src="assetUrl(row.image_url)" alt="" />
          <div class="meta-text">
            <strong>{{ row.title?.['zh-HK'] || row.title?.en || row.code }}</strong>
            <p>
              <n-tag size="small" :bordered="false">{{ row.code }}</n-tag>
              · {{ row.status === 1 ? '啟用' : '停用' }}
              · {{ row.show_on_home ? '上首頁' : '不上首頁' }}
              · sort {{ row.sort_order }}
            </p>
          </div>
        </div>
        <n-space>
          <n-button size="tiny" @click="edit(row)">編輯</n-button>
          <n-button size="tiny" type="error" secondary @click="remove(row.code)">刪除</n-button>
        </n-space>
      </div>
    </n-card>

    <n-card :title="form.editingCode ? `編輯圖片廣告 · ${form.editingCode}` : '新增圖片廣告'">
      <n-form-item label="繁中標題">
        <n-input v-model:value="form.title_zh" placeholder="如：分類下方橫幅" />
      </n-form-item>
      <n-form-item label="英文標題"><n-input v-model:value="form.title_en" /></n-form-item>
      <n-form-item label="code">
        <n-input
          v-model:value="form.code"
          :disabled="!!form.editingCode"
          placeholder="可空自動生成；保存後不可改"
        />
      </n-form-item>
      <n-form-item label="圖片">
        <n-space vertical style="width: 100%">
          <n-input v-model:value="form.image_url" placeholder="上傳後自動填入，或手動貼 URL" />
          <input type="file" accept="image/jpeg,image/png,image/webp" :disabled="uploading" @change="onFile" />
          <img v-if="form.image_url" class="preview" :src="assetUrl(form.image_url)" alt="預覽" />
        </n-space>
      </n-form-item>
      <n-form-item label="跳轉類型"><n-select v-model:value="form.link_type" :options="linkTypes" /></n-form-item>
      <n-form-item v-if="form.link_type !== 'none'" label="跳轉目標">
        <n-input v-model:value="form.link_value" placeholder="商品/分類/文章 code 或 URL" />
      </n-form-item>
      <n-form-item label="啟用"><n-switch v-model:value="form.status" /></n-form-item>
      <n-form-item label="展示於首頁"><n-switch v-model:value="form.show_on_home" /></n-form-item>
      <n-form-item label="排序"><n-input-number v-model:value="form.sort_order" /></n-form-item>
      <n-space>
        <n-button type="primary" :loading="saving" @click="save">保存</n-button>
        <n-button v-if="form.editingCode" :disabled="saving" @click="reset">新建</n-button>
      </n-space>
    </n-card>
  </n-space>
</template>

<style scoped>
.hint {
  margin: 0 0 0.75rem;
  color: #888;
  font-size: 0.8125rem;
}
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
  width: 6rem;
  height: 3.25rem;
  object-fit: cover;
  border-radius: 0.25rem;
  background: #f3f3f3;
  flex-shrink: 0;
}
.preview {
  display: block;
  max-width: min(100%, 22rem);
  max-height: 10rem;
  width: auto;
  height: auto;
  object-fit: contain;
  border-radius: 0.25rem;
  border: 1px solid #eee;
  background: #fafafa;
}
.meta-text {
  min-width: 0;
}
.meta p {
  margin: 0.15rem 0 0;
  color: #888;
  font-size: 0.8125rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
