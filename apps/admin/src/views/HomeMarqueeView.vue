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
import { api } from '../api'

type MarqueeItem = {
  code: string
  title: { 'zh-HK': string; en: string }
  text: { 'zh-HK': string; en: string }
  link_type: string
  link_value: string
  status: number
  enabled: boolean
  show_on_home: number
  sort_order: number
}

const message = useMessage()
const items = ref<MarqueeItem[]>([])
const form = ref(emptyForm())
const saving = ref(false)

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
    text_zh: '',
    text_en: '',
    link_type: 'none',
    link_value: '',
    status: true,
    show_on_home: true,
    sort_order: 0,
    editingCode: '' as string,
  }
}

async function load() {
  const ops = await api<{ marquees?: MarqueeItem[]; marquee?: MarqueeItem }>(
    '/api/v1/admin/home-ops',
    {},
    true,
  )
  items.value = ops.marquees?.length
    ? ops.marquees
    : ops.marquee
      ? [ops.marquee]
      : []
}

function reset() {
  form.value = emptyForm()
  if (items.value.length) {
    form.value.sort_order = (items.value[items.value.length - 1]?.sort_order || 0) + 10
  }
}

function edit(row: MarqueeItem) {
  form.value = {
    code: row.code,
    title_zh: row.title?.['zh-HK'] || '',
    title_en: row.title?.en || '',
    text_zh: row.text?.['zh-HK'] || '',
    text_en: row.text?.en || '',
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
    text_zh: form.value.text_zh.trim(),
    text_en: form.value.text_en.trim(),
    link_type: form.value.link_type,
    link_value: form.value.link_value.trim(),
    status: form.value.status ? 1 : 0,
    show_on_home: form.value.show_on_home ? 1 : 0,
    sort_order: form.value.sort_order,
  }
}

async function save() {
  if (!form.value.title_zh.trim() && !form.value.title_en.trim()) {
    message.warning('請填寫標題（用於區分放置位置）')
    return
  }
  if (!form.value.text_zh.trim() && !form.value.text_en.trim()) {
    message.warning('請填寫字幕文案')
    return
  }
  saving.value = true
  try {
    const next = items.value.map((row) => ({
      code: row.code,
      title_zh: row.title?.['zh-HK'] || '',
      title_en: row.title?.en || '',
      text_zh: row.text?.['zh-HK'] || '',
      text_en: row.text?.en || '',
      link_type: row.link_type,
      link_value: row.link_value,
      status: row.status,
      show_on_home: row.show_on_home,
      sort_order: row.sort_order,
    }))
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
      next.push({ ...payload, code: payload.code || `m${next.length + 1}` })
    }
    await api(
      '/api/v1/admin/home-ops',
      { method: 'PUT', body: JSON.stringify({ marquees: next }) },
      true,
    )
    message.success('字幕已保存；勾選「展示於首頁」的條目會出現在首頁佈局')
    reset()
    await load()
  } finally {
    saving.value = false
  }
}

async function remove(code: string) {
  if (items.value.length <= 1) {
    message.warning('至少保留一條字幕')
    return
  }
  const next = items.value
    .filter((r) => r.code !== code)
    .map((row) => ({
      code: row.code,
      title_zh: row.title?.['zh-HK'] || '',
      title_en: row.title?.en || '',
      text_zh: row.text?.['zh-HK'] || '',
      text_en: row.text?.en || '',
      link_type: row.link_type,
      link_value: row.link_value,
      status: row.status,
      show_on_home: row.show_on_home,
      sort_order: row.sort_order,
    }))
  await api(
    '/api/v1/admin/home-ops',
    { method: 'PUT', body: JSON.stringify({ marquees: next }) },
    true,
  )
  message.success('已刪除')
  if (form.value.editingCode === code) reset()
  await load()
}

onMounted(load)
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="廣告字幕">
      <p class="hint">
        可同時啟用多條。標題用來區分放置位置（如「輪播下方」「分類上方」）；勾選「展示於首頁」後會出現在「首頁佈局」，可再拖放排序。
      </p>
      <n-empty v-if="!items.length" description="尚未建立字幕" />
      <div v-for="row in items" :key="row.code" class="row">
        <div class="meta">
          <strong>{{ row.title?.['zh-HK'] || row.code }}</strong>
          <p>
            <n-tag size="small" :bordered="false">{{ row.code }}</n-tag>
            · {{ row.status === 1 ? '啟用' : '停用' }}
            · {{ row.show_on_home ? '上首頁' : '不上首頁' }}
            · {{ row.text?.['zh-HK'] || row.text?.en || '（無文案）' }}
          </p>
        </div>
        <n-space>
          <n-button size="tiny" @click="edit(row)">編輯</n-button>
          <n-button size="tiny" type="error" secondary :disabled="items.length <= 1" @click="remove(row.code)">
            刪除
          </n-button>
        </n-space>
      </div>
    </n-card>

    <n-card :title="form.editingCode ? `編輯字幕 · ${form.editingCode}` : '新增字幕'">
      <n-form-item label="標題（放置位置）">
        <n-input v-model:value="form.title_zh" placeholder="如：輪播下方、分類上方" />
      </n-form-item>
      <n-form-item label="英文標題"><n-input v-model:value="form.title_en" /></n-form-item>
      <n-form-item label="code">
        <n-input
          v-model:value="form.code"
          :disabled="!!form.editingCode"
          placeholder="可空自動生成；保存後不可改"
        />
      </n-form-item>
      <n-form-item label="繁中文案"><n-input v-model:value="form.text_zh" type="textarea" :rows="2" /></n-form-item>
      <n-form-item label="英文文案"><n-input v-model:value="form.text_en" type="textarea" :rows="2" /></n-form-item>
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
