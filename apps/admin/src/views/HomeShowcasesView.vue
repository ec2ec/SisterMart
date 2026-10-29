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
} from 'naive-ui'
import { api } from '../api'

type Section = {
  id: number
  code: string
  title: { 'zh-HK': string; en: string }
  view_all_type: string
  view_all_value: string
  sort_order: number
  status: number
  product_codes: string[]
  items: Array<{ code: string }>
}

const message = useMessage()
const sections = ref<Section[]>([])
const secForm = ref({
  id: 0,
  code: '',
  title_zh: '',
  title_en: '',
  view_all_type: 'list',
  view_all_value: '',
  sort_order: 0,
  status: true,
  product_codes_text: '',
})
const viewAllTypes = [
  { label: '展示區商品列表', value: 'list' },
  { label: '分類 code', value: 'category' },
  { label: 'URL / 路徑', value: 'url' },
  { label: '不顯示查看全部', value: 'none' },
]

async function load() {
  const secs = await api<{ items: Section[] }>('/api/v1/admin/home-sections', {}, true)
  sections.value = secs.items
}

function resetSec() {
  secForm.value = {
    id: 0,
    code: '',
    title_zh: '',
    title_en: '',
    view_all_type: 'list',
    view_all_value: '',
    sort_order: 0,
    status: true,
    product_codes_text: '',
  }
}

function editSec(row: Section) {
  secForm.value = {
    id: row.id,
    code: row.code,
    title_zh: row.title['zh-HK'],
    title_en: row.title.en,
    view_all_type: row.view_all_type || 'list',
    view_all_value: row.view_all_value || '',
    sort_order: row.sort_order,
    status: row.status === 1,
    product_codes_text: (row.product_codes || []).join(','),
  }
}

async function saveSec() {
  const codes = secForm.value.product_codes_text
    .split(/[,，\s]+/)
    .map((s) => s.trim())
    .filter(Boolean)
  const body = {
    code: secForm.value.code || undefined,
    title: { 'zh-HK': secForm.value.title_zh, en: secForm.value.title_en },
    view_all_type: secForm.value.view_all_type,
    view_all_value: secForm.value.view_all_value,
    sort_order: secForm.value.sort_order,
    status: secForm.value.status ? 1 : 0,
    product_codes: codes,
  }
  if (secForm.value.id) {
    await api(`/api/v1/admin/home-sections/${secForm.value.id}`, { method: 'PUT', body: JSON.stringify(body) }, true)
  } else {
    await api('/api/v1/admin/home-sections', { method: 'POST', body: JSON.stringify(body) }, true)
  }
  message.success('櫥窗已保存')
  resetSec()
  await load()
}

async function removeSec(id: number) {
  await api(`/api/v1/admin/home-sections/${id}`, { method: 'DELETE' }, true)
  message.success('已刪除櫥窗')
  if (secForm.value.id === id) resetSec()
  await load()
}

onMounted(load)
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="商品櫥窗">
      <p class="hint">對應首頁「商品展示區」模組；在「首頁佈局」中可調整出現順序與開關。</p>
      <div v-for="row in sections" :key="row.id" class="row">
        <div class="meta">
          <div>
            <strong>#{{ row.id }} · {{ row.title['zh-HK'] || row.code }}</strong>
            <p>
              {{ row.code }} · {{ row.product_codes?.length || 0 }} 件 · sort {{ row.sort_order }} ·
              {{ row.status === 1 ? '啟用' : '停用' }}
            </p>
          </div>
        </div>
        <n-space>
          <n-button size="tiny" @click="editSec(row)">編輯</n-button>
          <n-button size="tiny" type="error" secondary @click="removeSec(row.id)">刪除</n-button>
        </n-space>
      </div>
    </n-card>
    <n-card :title="secForm.id ? `編輯櫥窗 #${secForm.id}` : '新增櫥窗'">
      <n-form-item label="code"><n-input v-model:value="secForm.code" placeholder="可空自動生成" /></n-form-item>
      <n-form-item label="繁中標題"><n-input v-model:value="secForm.title_zh" /></n-form-item>
      <n-form-item label="英文標題"><n-input v-model:value="secForm.title_en" /></n-form-item>
      <n-form-item label="商品 codes（逗號分隔）">
        <n-input v-model:value="secForm.product_codes_text" placeholder="p1,p2,p3" />
      </n-form-item>
      <n-form-item label="查看全部"><n-select v-model:value="secForm.view_all_type" :options="viewAllTypes" /></n-form-item>
      <n-form-item v-if="secForm.view_all_type === 'category' || secForm.view_all_type === 'url'" label="目標">
        <n-input v-model:value="secForm.view_all_value" />
      </n-form-item>
      <n-form-item label="排序"><n-input-number v-model:value="secForm.sort_order" /></n-form-item>
      <n-form-item label="啟用"><n-switch v-model:value="secForm.status" /></n-form-item>
      <n-space>
        <n-button type="primary" @click="saveSec">保存櫥窗</n-button>
        <n-button v-if="secForm.id" @click="resetSec">新建</n-button>
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
.meta p {
  margin: 0.15rem 0 0;
  color: #888;
  font-size: 0.8125rem;
}
</style>
