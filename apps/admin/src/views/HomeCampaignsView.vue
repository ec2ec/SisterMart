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
import WangSimpleEditor from '../components/WangSimpleEditor.vue'
import ProductPickerDialog, { type ProductPickResult } from '../components/ProductPickerDialog.vue'

type ManualProduct = {
  id?: number
  code: string
  name: { 'zh-HK': string; en: string }
}

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
  product_codes?: string[]
  manual_products?: ManualProduct[]
}

type Badge = { code: string; name: { 'zh-HK': string; en: string } }

const message = useMessage()
const items = ref<Campaign[]>([])
const badges = ref<Badge[]>([])
const form = ref(emptyForm())
const pickerShow = ref(false)
const uploading = ref(false)

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
    manual_products: [] as ManualProduct[],
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
  const manuals =
    row.manual_products?.length
      ? row.manual_products.map((p) => ({
          id: p.id,
          code: p.code,
          name: { 'zh-HK': p.name?.['zh-HK'] || '', en: p.name?.en || '' },
        }))
      : (row.product_codes || []).map((code) => ({
          code,
          name: { 'zh-HK': '', en: '' },
        }))
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
    manual_products: manuals,
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

function productLabel(p: ManualProduct) {
  const zh = p.name?.['zh-HK'] || ''
  return zh ? `${p.code} · ${zh}` : p.code
}

function onPicked(result: ProductPickResult) {
  const exists = form.value.manual_products.some((p) => p.code === result.code)
  if (exists) {
    message.info('該商品已在列表中')
    return
  }
  form.value.manual_products.push({
    id: result.db_id,
    code: result.code,
    name: { 'zh-HK': result.name['zh-HK'] || '', en: result.name.en || '' },
  })
  message.success(`已加入 ${result.code}`)
}

function removeManual(index: number) {
  form.value.manual_products.splice(index, 1)
}

async function onBannerFile(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  uploading.value = true
  try {
    const data = await uploadImage(file, { preset: 'banner' })
    form.value.banner_image_url = data.url
    message.success('橫幅已上傳')
  } catch (e: unknown) {
    showError(e instanceof Error ? e.message : '橫幅上傳失敗')
  } finally {
    uploading.value = false
    input.value = ''
  }
}

async function save() {
  if (!form.value.title_zh.trim()) {
    message.warning('請填寫繁中標題')
    return
  }
  const codes = form.value.manual_products.map((p) => p.code).filter(Boolean)
  if (form.value.product_source === 'manual' && !codes.length) {
    message.warning('手工選品請至少加入一件商品')
    return
  }
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
  if (form.value.product_source !== 'badge') {
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
      <n-empty v-if="!items.length" description="尚未建立活動。下方表單可新增新人專區等活動。" />
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
        <n-switch :value="form.audience_all" @update:value="onAudienceAll" />
      </n-form-item>
      <n-form-item v-if="!form.audience_all" label="僅註冊 N 天內可買">
        <n-input-number v-model:value="form.registered_within_days" :min="1" :max="365" />
      </n-form-item>

      <n-form-item label="選品來源"><n-select v-model:value="form.product_source" :options="sources" /></n-form-item>
      <n-form-item v-if="form.product_source !== 'manual'" label="商品角標">
        <n-select v-model:value="form.product_badge" :options="badgeOptions()" filterable />
      </n-form-item>
      <n-form-item v-if="form.product_source !== 'badge'" label="手工商品">
        <div class="manual-box">
          <n-space>
            <n-button size="small" type="primary" secondary @click="pickerShow = true">加入商品</n-button>
            <span class="hint-inline">可重複加入；保存時覆蓋手工列表</span>
          </n-space>
          <div v-if="form.manual_products.length" class="manual-list">
            <n-tag
              v-for="(p, idx) in form.manual_products"
              :key="p.code"
              closable
              size="small"
              @close="removeManual(idx)"
            >
              {{ productLabel(p) }}
            </n-tag>
          </div>
          <p v-else class="hint-inline">尚未選擇手工商品</p>
        </div>
      </n-form-item>

      <n-form-item label="落地頁橫幅">
        <n-space vertical style="width: 100%">
          <n-input v-model:value="form.banner_image_url" placeholder="圖片 URL 或下方上傳" />
          <n-space align="center">
            <input
              type="file"
              accept="image/jpeg,image/png,image/webp"
              :disabled="uploading"
              @change="onBannerFile"
            />
            <span v-if="uploading" class="hint-inline">上傳中…</span>
          </n-space>
          <img v-if="form.banner_image_url" class="banner-preview" :src="form.banner_image_url" alt="" />
        </n-space>
      </n-form-item>
      <n-form-item label="介紹（繁）">
        <WangSimpleEditor
          v-model="form.intro_html_zh"
          preset="admin"
          placeholder="活動介紹（繁中）…"
          :height="200"
        />
      </n-form-item>
      <n-form-item label="介紹（英）">
        <WangSimpleEditor
          v-model="form.intro_html_en"
          preset="admin"
          placeholder="Campaign intro (EN)…"
          :height="180"
        />
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

    <ProductPickerDialog
      v-model:show="pickerShow"
      title="選擇活動商品"
      :require-sku="false"
      @select="onPicked"
    />
  </n-space>
</template>

<style scoped>
.hint {
  margin: 0 0 0.75rem;
  color: #888;
  font-size: 0.8125rem;
}
.hint-inline {
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
.manual-box {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.manual-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.banner-preview {
  max-width: 22rem;
  max-height: 8rem;
  object-fit: contain;
  border-radius: 4px;
  border: 1px solid #eee;
  background: #fafafa;
}
</style>
