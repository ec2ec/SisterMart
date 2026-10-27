<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NCheckbox,
  NCheckboxGroup,
  NDatePicker,
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
import { showWarningDialog } from '../feedback'
import ProductMediaGallery, { type MediaItem } from '../components/ProductMediaGallery.vue'
import { assetUrl } from '../utils/assetUrl'

type Popup = {
  id: number
  image_url: string
  title: { 'zh-HK': string; en: string }
  title_zh?: string
  title_en?: string
  link_type: string
  link_value: string
  frequency: string
  targets: string[]
  image_scale: number
  starts_at: string | null
  ends_at: string | null
  sort_order: number
  status: number
}

const message = useMessage()
const items = ref<Popup[]>([])
const media = ref<MediaItem[]>([])
const form = ref({
  id: 0,
  title_zh: '',
  title_en: '',
  link_type: 'none',
  link_value: '',
  frequency: 'once',
  targets: ['all'] as string[],
  image_scale: 100,
  starts_at: null as string | null,
  ends_at: null as string | null,
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

const frequencies = [
  { label: '只顯示一次', value: 'once' },
  { label: '每天一次', value: 'daily' },
  { label: '每次進首頁', value: 'always' },
]

const targetOptions = [
  { label: '全部用戶', value: 'all' },
  { label: '訪客（未登錄）', value: 'guest' },
  { label: '已登錄會員', value: 'member' },
  { label: '新會員（註冊 7 天內）', value: 'new_member' },
  { label: '從未下單', value: 'no_order' },
  { label: '已下過單', value: 'has_order' },
]

const bgHint = '遮罩圖單張不超過 3M，僅 1 張'
const bgDeleteTitle = '確認刪除圖片'
const bgDeleteContent = '確定移除此遮罩圖？保存後才會生效。'

function freqLabel(v: string) {
  return frequencies.find((f) => f.value === v)?.label || v
}

function targetLabel(v: string) {
  return targetOptions.find((t) => t.value === v)?.label || v
}

function targetsText(row: Popup) {
  const list = row.targets?.length ? row.targets : ['all']
  return list.map(targetLabel).join('、')
}

function windowLabel(row: Popup) {
  const a = row.starts_at ? String(row.starts_at).slice(0, 16) : '不限'
  const b = row.ends_at ? String(row.ends_at).slice(0, 16) : '不限'
  return `${a} ~ ${b}`
}

function asDateTime(v: unknown): string | null {
  if (v == null || v === '') return null
  if (typeof v === 'number' && Number.isFinite(v)) {
    const d = new Date(v > 1e12 ? v : v * 1000)
    if (Number.isNaN(d.getTime())) return null
    const pad = (n: number) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`
  }
  if (typeof v !== 'string') return null
  const s = v.trim().replace('T', ' ')
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return `${s} 00:00:00`
  const m = s.match(/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})/)
  return m ? `${m[1]}:00` : null
}

function onTargetsChange(vals: (string | number)[]) {
  const next = vals.map(String)
  if (next.includes('all') && next.length > 1) {
    // 勾選「全部」時清掉其他；從全部改勾其他時去掉 all
    const last = next[next.length - 1]
    form.value.targets = last === 'all' ? ['all'] : next.filter((v) => v !== 'all')
    return
  }
  form.value.targets = next.length ? next : ['all']
}

async function load() {
  const data = await api<{ items: Popup[] }>('/api/v1/admin/home-popups', {}, true)
  items.value = data.items
}

function reset() {
  form.value = {
    id: 0,
    title_zh: '',
    title_en: '',
    link_type: 'none',
    link_value: '',
    frequency: 'once',
    targets: ['all'],
    image_scale: 100,
    starts_at: null,
    ends_at: null,
    sort_order: 0,
    status: true,
  }
  media.value = []
}

function edit(row: Popup) {
  form.value = {
    id: row.id,
    title_zh: row.title_zh || row.title?.['zh-HK'] || '',
    title_en: row.title_en || row.title?.en || '',
    link_type: row.link_type || 'none',
    link_value: row.link_value || '',
    frequency: row.frequency || 'once',
    targets: row.targets?.length ? [...row.targets] : ['all'],
    image_scale: Number(row.image_scale ?? 100) || 100,
    starts_at: row.starts_at ? String(row.starts_at).slice(0, 16) : null,
    ends_at: row.ends_at ? String(row.ends_at).slice(0, 16) : null,
    sort_order: row.sort_order,
    status: row.status === 1,
  }
  media.value = row.image_url ? [{ media_type: 'image', url: row.image_url }] : []
}

async function save() {
  const image = (media.value[0]?.url || '').trim()
  if (!image) {
    message.warning('請上傳遮罩圖片')
    return
  }
  if (form.value.link_type !== 'none' && !form.value.link_value.trim()) {
    message.warning('請填寫跳轉目標')
    return
  }
  if (!form.value.targets.length) {
    message.warning('請至少選擇一個定向')
    return
  }
  const body = {
    image_url: image,
    title_zh: form.value.title_zh,
    title_en: form.value.title_en,
    link_type: form.value.link_type,
    link_value: form.value.link_value,
    frequency: form.value.frequency,
    targets: form.value.targets,
    image_scale: form.value.image_scale,
    starts_at: asDateTime(form.value.starts_at),
    ends_at: asDateTime(form.value.ends_at),
    sort_order: form.value.sort_order,
    status: form.value.status ? 1 : 0,
  }
  if (form.value.id) {
    await api(`/api/v1/admin/home-popups/${form.value.id}`, { method: 'PUT', body: JSON.stringify(body) }, true)
    message.success('已更新')
  } else {
    await api('/api/v1/admin/home-popups', { method: 'POST', body: JSON.stringify(body) }, true)
    message.success('已創建')
  }
  reset()
  await load()
}

function remove(row: Popup) {
  showWarningDialog({
    title: '刪除遮罩廣告',
    content: `確定刪除 #${row.id}？會員關閉／曝光記錄也會一併清除。`,
    positiveText: '刪除',
    negativeText: '取消',
    onPositiveClick: async () => {
      await api(`/api/v1/admin/home-popups/${row.id}`, { method: 'DELETE' }, true)
      message.success('已刪除')
      if (form.value.id === row.id) reset()
      await load()
    },
  })
}

onMounted(load)
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="首頁遮罩廣告">
      <p class="hint">
        圖片 + 跳轉；訪客／會員關閉狀態分開；每人每天最多展示 2 張。定向可複選（命中任一即展示）。
      </p>
      <div v-for="row in items" :key="row.id" class="row">
        <div class="meta">
          <img v-if="row.image_url" :src="assetUrl(row.image_url)" alt="" />
          <div>
            <strong>#{{ row.id }} · {{ row.title['zh-HK'] || '(無標題)' }}</strong>
            <p>
              {{ freqLabel(row.frequency) }} · 縮放 {{ row.image_scale ?? 100 }}% · {{ row.link_type }}
              {{ row.link_value }} · sort {{ row.sort_order }}
            </p>
            <p>定向：{{ targetsText(row) }}</p>
            <p>{{ windowLabel(row) }}</p>
          </div>
        </div>
        <n-space align="center">
          <n-tag size="small" :type="row.status === 1 ? 'success' : 'default'">
            {{ row.status === 1 ? '啟用' : '停用' }}
          </n-tag>
          <n-button size="tiny" @click="edit(row)">編輯</n-button>
          <n-button size="tiny" type="error" secondary @click="remove(row)">刪除</n-button>
        </n-space>
      </div>
      <p v-if="!items.length" class="empty">暫無遮罩廣告</p>
    </n-card>

    <n-card :title="form.id ? `編輯 #${form.id}` : '新增遮罩廣告'">
      <n-form-item label="圖片">
        <ProductMediaGallery
          v-model="media"
          :max="1"
          :hint="bgHint"
          :delete-title="bgDeleteTitle"
          :delete-content="bgDeleteContent"
        />
      </n-form-item>
      <n-form-item label="繁中標題（後台識別用）"><n-input v-model:value="form.title_zh" /></n-form-item>
      <n-form-item label="英文標題"><n-input v-model:value="form.title_en" /></n-form-item>
      <n-form-item label="跳轉類型"><n-select v-model:value="form.link_type" :options="linkTypes" /></n-form-item>
      <n-form-item v-if="form.link_type !== 'none'" label="跳轉目標">
        <n-input v-model:value="form.link_value" placeholder="商品/分類/文章 code 或 URL" />
      </n-form-item>
      <n-form-item label="顯示頻率"><n-select v-model:value="form.frequency" :options="frequencies" /></n-form-item>
      <n-form-item label="圖片縮放比例">
        <n-space align="center">
          <n-input-number v-model:value="form.image_scale" :min="40" :max="150" :step="5" style="width: 8rem" />
          <span>%</span>
          <span class="field-hint">相對默認寬度，40–150，默認 100</span>
        </n-space>
      </n-form-item>
      <n-form-item label="定向（可複選，命中任一）">
        <n-checkbox-group :value="form.targets" @update:value="onTargetsChange">
          <n-space vertical :size="6">
            <n-checkbox v-for="opt in targetOptions" :key="opt.value" :value="opt.value" :label="opt.label" />
          </n-space>
        </n-checkbox-group>
      </n-form-item>
      <n-form-item label="排期（可空＝不限）">
        <n-space wrap>
          <n-date-picker
            v-model:formatted-value="form.starts_at"
            type="datetime"
            value-format="yyyy-MM-dd HH:mm"
            clearable
            placeholder="開始"
            style="width: 12rem"
          />
          <span>至</span>
          <n-date-picker
            v-model:formatted-value="form.ends_at"
            type="datetime"
            value-format="yyyy-MM-dd HH:mm"
            clearable
            placeholder="結束"
            style="width: 12rem"
          />
        </n-space>
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
.hint {
  margin: 0 0 0.75rem;
  color: #8a8378;
  font-size: 0.8125rem;
}
.empty {
  color: #999;
  font-size: 0.875rem;
}
.row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.75rem 0;
  border-bottom: 1px solid #f0f0f0;
}
.meta {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  min-width: 0;
}
.meta img {
  width: 3.5rem;
  height: 3.5rem;
  object-fit: cover;
  border-radius: 0.375rem;
  border: 1px solid #eee;
  flex: none;
}
.meta strong {
  font-size: 0.875rem;
}
.meta p {
  margin: 0.15rem 0 0;
  color: #888;
  font-size: 0.75rem;
}
.field-hint {
  color: #8a8378;
  font-size: 0.75rem;
}
</style>
