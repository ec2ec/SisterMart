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

type Campaign = {
  id: number
  code: string
  title: { 'zh-HK': string; en: string }
  status: number
  starts_at: string | null
  ends_at: string | null
  audience: Record<string, unknown>
  product_source: string
  product_badge: string
  banner_image_url: string
  intro_html: { 'zh-HK': string; en: string }
  show_on_home: number
  home_rail_limit: number
  sort_order: number
  product_ids: number[]
}

type Badge = { code: string; name: { 'zh-HK': string; en: string } }

const message = useMessage()
const items = ref<Campaign[]>([])
const badges = ref<Badge[]>([])
const form = ref(emptyForm())

const sources = [
  { label: '按角標自動', value: 'badge' },
  { label: '手工選品', value: 'manual' },
  { label: '角標 + 手工置頂', value: 'badge_and_manual' },
]

function emptyForm() {
  return {
    id: 0,
    code: '',
    title_zh: '',
    title_en: '',
    status: true,
    starts_at: '',
    ends_at: '',
    registered_within_days: 0 as number,
    audience_all: true,
    product_source: 'badge',
    product_badge: 'newcomer',
    banner_image_url: '',
    intro_html_zh: '',
    intro_html_en: '',
    show_on_home: true,
    home_rail_limit: 10,
    sort_order: 0,
    product_codes_text: '',
  }
}

async function load() {
  const [camps, badgeRes] = await Promise.all([
    api<{ items: Campaign[] }>('/api/v1/admin/campaigns', {}, true),
    api<{ items: Badge[] }>('/api/v1/admin/badges?active_only=1', {}, true),
  ])
  items.value = camps.items
  badges.value = badgeRes.items
}

function reset() {
  form.value = emptyForm()
}

function edit(row: Campaign) {
  const aud = row.audience || {}
  const days = typeof aud.registered_within_days === 'number' ? aud.registered_within_days : 0
  form.value = {
    id: row.id,
    code: row.code,
    title_zh: row.title['zh-HK'],
    title_en: row.title.en,
    status: row.status === 1,
    starts_at: row.starts_at || '',
    ends_at: row.ends_at || '',
    registered_within_days: days,
    audience_all: !!aud.all || days <= 0,
    product_source: row.product_source || 'badge',
    product_badge: row.product_badge || '',
    banner_image_url: row.banner_image_url || '',
    intro_html_zh: row.intro_html?.['zh-HK'] || '',
    intro_html_en: row.intro_html?.en || '',
    show_on_home: row.show_on_home === 1,
    home_rail_limit: row.home_rail_limit || 10,
    sort_order: row.sort_order || 0,
    product_codes_text: '',
  }
}

function buildAudience() {
  if (form.value.audience_all) {
    return { all: true }
  }
  const days = form.value.registered_within_days > 0 ? form.value.registered_within_days : 30
  return { registered_within_days: days }
}

function onAudienceAll(v: boolean) {
  form.value.audience_all = v
  if (!v && form.value.registered_within_days <= 0) {
    form.value.registered_within_days = 30
  }
}

async function save() {
  const codes = form.value.product_codes_text
    .split(/[,，\s]+/)
    .map((s) => s.trim())
    .filter(Boolean)
  const body: Record<string, unknown> = {
    code: form.value.code || undefined,
    title: { 'zh-HK': form.value.title_zh, en: form.value.title_en },
    status: form.value.status ? 1 : 0,
    starts_at: form.value.starts_at || null,
    ends_at: form.value.ends_at || null,
    audience: buildAudience(),
    product_source: form.value.product_source,
    product_badge: form.value.product_badge,
    banner_image_url: form.value.banner_image_url,
    intro_html: { 'zh-HK': form.value.intro_html_zh, en: form.value.intro_html_en },
    show_on_home: form.value.show_on_home ? 1 : 0,
    home_rail_limit: form.value.home_rail_limit,
    sort_order: form.value.sort_order,
  }
  if (codes.length || form.value.product_source !== 'badge') {
    body.product_codes = codes
  }
  if (form.value.id) {
    await api(`/api/v1/admin/campaigns/${form.value.id}`, { method: 'PUT', body: JSON.stringify(body) }, true)
  } else {
    await api('/api/v1/admin/campaigns', { method: 'POST', body: JSON.stringify(body) }, true)
  }
  message.success('活動已保存')
  reset()
  await load()
}

async function remove(id: number) {
  await api(`/api/v1/admin/campaigns/${id}`, { method: 'DELETE' }, true)
  message.success('已刪除活動')
  if (form.value.id === id) reset()
  await load()
}

const badgeOptions = () =>
  badges.value.map((b) => ({
    label: `${b.name?.['zh-HK'] || b.code} (${b.code})`,
    value: b.code,
  }))

onMounted(load)
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="活動專區">
      <p class="hint">勾選「展示於首頁」後會出現在「首頁佈局」列表，可再拖放排序。</p>
      <div v-for="row in items" :key="row.id" class="row">
        <div class="meta">
          <strong>#{{ row.id }} · {{ row.title['zh-HK'] || row.code }}</strong>
          <p>
            {{ row.code }} · {{ row.product_source }}
            <template v-if="row.product_badge"> · 角標 {{ row.product_badge }}</template>
            · {{ row.show_on_home ? '上首頁' : '不上首頁' }}
            · {{ row.status === 1 ? '發佈' : '草稿' }}
          </p>
        </div>
        <n-space>
          <n-button size="tiny" @click="edit(row)">編輯</n-button>
          <n-button size="tiny" type="error" secondary @click="remove(row.id)">刪除</n-button>
        </n-space>
      </div>
    </n-card>

    <n-card :title="form.id ? `編輯活動 #${form.id}` : '新增活動'">
      <n-form-item label="code"><n-input v-model:value="form.code" placeholder="可空自動生成" /></n-form-item>
      <n-form-item label="繁中標題"><n-input v-model:value="form.title_zh" /></n-form-item>
      <n-form-item label="英文標題"><n-input v-model:value="form.title_en" /></n-form-item>
      <n-form-item label="發佈"><n-switch v-model:value="form.status" /></n-form-item>
      <n-form-item label="開始時間"><n-input v-model:value="form.starts_at" placeholder="可空 YYYY-MM-DD HH:mm:ss" /></n-form-item>
      <n-form-item label="結束時間"><n-input v-model:value="form.ends_at" placeholder="可空" /></n-form-item>

      <n-form-item label="受眾：全員可買">
        <n-switch v-model:value="form.audience_all" />
      </n-form-item>
      <n-form-item v-if="!form.audience_all" label="僅註冊 N 天內可買">
        <n-input-number v-model:value="form.registered_within_days" :min="1" :max="365" />
      </n-form-item>

      <n-form-item label="選品來源"><n-select v-model:value="form.product_source" :options="sources" /></n-form-item>
      <n-form-item v-if="form.product_source !== 'manual'" label="商品角標">
        <n-select v-model:value="form.product_badge" :options="badgeOptions()" filterable />
      </n-form-item>
      <n-form-item v-if="form.product_source !== 'badge'" label="手工商品 codes">
        <n-input v-model:value="form.product_codes_text" placeholder="p1,p2（保存時覆蓋手工列表）" />
      </n-form-item>

      <n-form-item label="落地頁橫幅 URL"><n-input v-model:value="form.banner_image_url" /></n-form-item>
      <n-form-item label="介紹 HTML（繁）">
        <n-input v-model:value="form.intro_html_zh" type="textarea" :rows="3" />
      </n-form-item>
      <n-form-item label="介紹 HTML（英）">
        <n-input v-model:value="form.intro_html_en" type="textarea" :rows="3" />
      </n-form-item>

      <n-form-item label="展示於首頁"><n-switch v-model:value="form.show_on_home" /></n-form-item>
      <n-form-item label="首頁橫滑最多件數">
        <n-input-number v-model:value="form.home_rail_limit" :min="1" :max="30" />
      </n-form-item>
      <n-form-item label="排序"><n-input-number v-model:value="form.sort_order" /></n-form-item>

      <n-space>
        <n-button type="primary" @click="save">保存活動</n-button>
        <n-button v-if="form.id" @click="reset">新建</n-button>
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
