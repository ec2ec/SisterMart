<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  NButton,
  NCard,
  NCheckbox,
  NCheckboxGroup,
  NFormItem,
  NInput,
  NInputNumber,
  NSelect,
  NSpace,
  NSwitch,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import ProductMediaGallery, { type MediaItem } from '../components/ProductMediaGallery.vue'
import ProductSkuEditor, {
  type SpecGroupDraft,
  type SkuDraft,
} from '../components/ProductSkuEditor.vue'
import WangSimpleEditor from '../components/WangSimpleEditor.vue'
import { toStoredUrl } from '../utils/assetUrl'

type Cat = { id: number; label: string }
type SupplierOpt = {
  id: number
  code: string
  name: string
  status: number
  type_label?: string
}

const route = useRoute()
const router = useRouter()
const message = useMessage()
const isNew = computed(() => route.params.id === 'new')
const categories = ref<Cat[]>([])
const suppliers = ref<SupplierOpt[]>([])
const tags = ref<Array<{ label: string; value: number }>>([])
const badgeOptions = ref<Array<{ label: string; value: string; color: string }>>([])
const attrDefs = ref<
  Array<{
    id: number
    code: string
    name: { 'zh-HK': string; en: string }
    values: Array<{ id: number; code: string; name: { 'zh-HK': string; en: string } }>
  }>
>([])

const skuEditor = ref<InstanceType<typeof ProductSkuEditor> | null>(null)
const specGroups = ref<SpecGroupDraft[]>([])
const skus = ref<SkuDraft[]>([])

const form = ref({
  code: '',
  category_id: null as number | null,
  supplier_id: null as number | null,
  status: true,
  sold_count: 0,
  sold_boost: 0,
  name_zh: '',
  name_en: '',
  detail_zh: '',
  detail_en: '',
  badges: [] as string[],
  service_tag_ids: [] as number[],
  attr_value_ids: [] as number[],
  media: [] as MediaItem[],
  commission_type: null as string | null,
  commission_value: 0,
})

const commissionTypeOptions = [
  { label: '不計佣金', value: '' },
  { label: '百分比 percent', value: 'percent' },
  { label: '固定金額 fixed', value: 'fixed' },
]

function onCommissionType(v: string | null) {
  form.value.commission_type = v ? v : null
  if (!form.value.commission_type) form.value.commission_value = 0
}

function flattenCats(nodes: any[], depth = 0): Cat[] {
  const out: Cat[] = []
  for (const n of nodes) {
    out.push({ id: n.id, label: `${'— '.repeat(depth)}${n.name['zh-HK']}` })
    out.push(...flattenCats(n.children || [], depth + 1))
  }
  return out
}

function defaultSpecs() {
  specGroups.value = [
    {
      code: 'default',
      name: { 'zh-HK': '款式', en: 'Style' },
      options: [{ code: 'default', name: { 'zh-HK': '默認', en: 'Default' }, image: null }],
    },
  ]
  skus.value = [
    {
      code: 'default',
      price: 0,
      compare_at: 0,
      agent_price: 0,
      cost_price: 0,
      stock: 0,
      image: null,
      status: 1,
      option_refs: ['default:default'],
    },
  ]
}

const supplierOptions = computed(() => {
  const opts = suppliers.value.map((s) => ({
    label: `${s.code} · ${s.name}${s.status !== 1 ? '（停用）' : ''}`,
    value: s.id,
    disabled: isNew.value && s.status !== 1,
  }))
  // 編輯時確保當前綁定仍可顯示（即使不在列表）
  const cur = form.value.supplier_id
  if (cur && !opts.some((o) => o.value === cur)) {
    opts.unshift({ label: `#${cur}`, value: cur, disabled: false })
  }
  return opts
})

onMounted(async () => {
  const [cats, supplierData, tagData, badgeData, attrData] = await Promise.all([
    api<{ items: any[] }>('/api/v1/admin/categories', {}, true),
    api<{ items: SupplierOpt[] }>('/api/v1/admin/suppliers', {}, true),
    api<{ items: Array<{ id: number; name: { 'zh-HK': string }; status: number }> }>(
      '/api/v1/admin/service-tags?active_only=1',
      {},
      true,
    ),
    api<{ items: Array<{ code: string; color: string; name: { 'zh-HK': string }; status: number }> }>(
      '/api/v1/admin/badges?active_only=1',
      {},
      true,
    ),
    api<{
      items: Array<{
        id: number
        code: string
        status: number
        name: { 'zh-HK': string; en: string }
        values: Array<{ id: number; code: string; status: number; name: { 'zh-HK': string; en: string } }>
      }>
    }>('/api/v1/admin/attrs?active_only=1', {}, true),
  ])
  categories.value = flattenCats(cats.items)
  suppliers.value = supplierData.items || []
  tags.value = tagData.items.filter((t) => t.status !== 0).map((t) => ({ label: t.name['zh-HK'], value: t.id }))
  badgeOptions.value = badgeData.items
    .filter((b) => b.status !== 0)
    .map((b) => ({ label: b.name['zh-HK'], value: b.code, color: b.color }))
  attrDefs.value = attrData.items
    .filter((a) => a.status !== 0)
    .map((a) => ({
      id: a.id,
      code: a.code,
      name: a.name,
      values: (a.values || []).filter((v) => v.status !== 0),
    }))

  if (isNew.value) {
    const firstActive = suppliers.value.find((s) => s.status === 1)
    form.value.supplier_id = firstActive?.id ?? null
    defaultSpecs()
    return
  }

  const p = await api<any>(`/api/v1/admin/products/${route.params.id}`, {}, true)
  form.value.code = p.code
  form.value.category_id = p.category_id
  form.value.supplier_id = p.supplier_id ?? null
  form.value.status = p.status === 1
  form.value.sold_count = Number(p.sold_count ?? 0)
  form.value.sold_boost = Number(p.sold_boost ?? 0)
  form.value.name_zh = p.name['zh-HK']
  form.value.name_en = p.name.en
  form.value.detail_zh = p.detail['zh-HK']
  form.value.detail_en = p.detail.en
  form.value.badges = p.badges || []
  form.value.service_tag_ids = p.service_tag_ids || []
  form.value.attr_value_ids = p.attr_value_ids || []
  form.value.media = (p.media || [])
    .map((m: { url?: string; media_type?: string }) => ({
      url: toStoredUrl(String(m.url || '').trim()),
      media_type: m.media_type || (/\.(mp4|webm)(\?|$)/i.test(String(m.url || '')) ? 'video' : 'image'),
    }))
    .filter((m: MediaItem) => !!m.url)
  form.value.commission_type = p.commission_type || null
  form.value.commission_value = Number(p.commission_value ?? 0)

  specGroups.value = (p.spec_groups || []).map((g: any) => ({
    code: g.code,
    name: { 'zh-HK': g.name?.['zh-HK'] || g.code, en: g.name?.en || g.code },
    options: (g.options || []).map((o: any) => ({
      code: o.code,
      name: { 'zh-HK': o.name?.['zh-HK'] || o.code, en: o.name?.en || o.code },
      image: o.image || null,
    })),
  }))
  skus.value = (p.skus || []).map((s: any) => ({
    code: s.code,
    price: Number(s.price || 0),
    compare_at: Number(s.compare_at || 0),
    agent_price: Number(s.agent_price ?? 0),
    cost_price: Number(s.cost_price ?? 0),
    stock: Number(s.stock || 0),
    image: s.image || null,
    status: s.status === 0 ? 0 : 1,
    option_refs: [...(s.option_refs || [])],
  }))
  if (!specGroups.value.length || !skus.value.length) defaultSpecs()
})

async function save() {
  const prepared = skuEditor.value?.prepareForSave()
  if (!prepared) return

  const media = form.value.media
    .map((m) => ({ ...m, url: toStoredUrl(m.url) }))
    .filter((m) => m.url)
  if (!form.value.supplier_id) {
    message.error('請選擇供應商')
    return
  }
  const body: Record<string, unknown> = {
    code: form.value.code,
    category_id: form.value.category_id,
    supplier_id: form.value.supplier_id,
    status: form.value.status ? 1 : 0,
    sold_boost: form.value.sold_boost,
    name: { 'zh-HK': form.value.name_zh, en: form.value.name_en },
    detail: { 'zh-HK': form.value.detail_zh, en: form.value.detail_en },
    badges: form.value.badges,
    service_tag_ids: form.value.service_tag_ids,
    attr_value_ids: form.value.attr_value_ids,
    media,
    commission_type: form.value.commission_type,
    commission_value: form.value.commission_type ? form.value.commission_value : null,
    spec_groups: prepared.groups,
    skus: prepared.skus,
  }
  if (isNew.value) {
    const saved = await api<{ db_id: number }>(
      '/api/v1/admin/products',
      { method: 'POST', body: JSON.stringify(body) },
      true,
    )
    message.success('商品已保存')
    // 新建後留在編輯頁（切到真實 id）
    if (saved?.db_id) {
      await router.replace(`/catalog/products/${saved.db_id}`)
    }
    return
  }
  await api(`/api/v1/admin/products/${route.params.id}`, { method: 'PUT', body: JSON.stringify(body) }, true)
  message.success('商品已保存')
}
</script>

<template>
  <n-card :title="isNew ? '新增商品' : '編輯商品'">
    <div class="row-4">
      <n-form-item label="代碼"><n-input v-model:value="form.code" /></n-form-item>
      <n-form-item label="分類">
        <n-select
          v-model:value="form.category_id"
          :options="categories.map((c) => ({ label: c.label, value: c.id }))"
        />
      </n-form-item>
      <n-form-item label="供應商">
        <n-select
          v-model:value="form.supplier_id"
          :options="supplierOptions"
          :disabled="!isNew"
          filterable
          placeholder="必選（永久綁定）"
        />
        <span v-if="!isNew" class="field-hint">永久綁定，不可更換</span>
      </n-form-item>
      <n-form-item label="上架"><n-switch v-model:value="form.status" /></n-form-item>
    </div>
    <div class="row-4">
      <n-form-item label="疊加數量">
        <n-input-number v-model:value="form.sold_boost" :min="0" style="width: 100%" />
      </n-form-item>
      <n-form-item label="銷售數量">
        <n-input-number :value="form.sold_count" :min="0" disabled style="width: 100%" />
        <span class="field-hint">真實銷售，下單＋／取消退款－，不可改</span>
      </n-form-item>
      <n-form-item label="H5 顯示基數">
        <n-input-number :value="form.sold_count + form.sold_boost" disabled style="width: 100%" />
        <span class="field-hint">銷售＋疊加，再套 50+ 規則</span>
      </n-form-item>
    </div>

    <n-form-item label="繁中名稱"><n-input v-model:value="form.name_zh" /></n-form-item>
    <n-form-item label="英文名稱"><n-input v-model:value="form.name_en" /></n-form-item>
    <n-form-item label="商品主圖">
      <ProductMediaGallery v-model="form.media" />
    </n-form-item>
    <n-form-item label="繁體中文詳情">
      <WangSimpleEditor v-model="form.detail_zh" preset="product" placeholder="繁體中文詳情，可上傳圖片" :height="240" />
    </n-form-item>
    <n-form-item label="英文詳情">
      <WangSimpleEditor v-model="form.detail_en" preset="product" placeholder="English detail" :height="240" />
    </n-form-item>

    <div class="row-2">
      <n-form-item label="角標">
        <n-checkbox-group v-model:value="form.badges">
          <n-checkbox v-for="b in badgeOptions" :key="b.value" :value="b.value" :label="b.label" />
        </n-checkbox-group>
      </n-form-item>
      <n-form-item label="服務標籤">
        <n-checkbox-group v-model:value="form.service_tag_ids">
          <n-checkbox v-for="tag in tags" :key="tag.value" :value="tag.value" :label="tag.label" />
        </n-checkbox-group>
      </n-form-item>
    </div>

    <n-form-item v-for="attr in attrDefs" :key="attr.id" :label="attr.name['zh-HK']">
      <n-checkbox-group v-model:value="form.attr_value_ids">
        <n-checkbox
          v-for="v in attr.values"
          :key="v.id"
          :value="v.id"
          :label="v.name['zh-HK']"
        />
      </n-checkbox-group>
      <span v-if="!attr.values.length" class="muted">暫無取值，請先在「商品參數」維護</span>
    </n-form-item>

    <n-form-item label="佣金類型">
      <n-select
        :value="form.commission_type ?? ''"
        clearable
        placeholder="不計佣金"
        :options="commissionTypeOptions"
        @update:value="onCommissionType"
      />
    </n-form-item>
    <n-form-item v-if="form.commission_type" label="佣金值">
      <n-input-number v-model:value="form.commission_value" :min="0" :precision="2" />
      <span style="margin-left: 0.5rem; color: #888">
        {{ form.commission_type === 'percent' ? '%（按商品實付）' : 'HK$（按件）' }}
      </span>
    </n-form-item>

    <ProductSkuEditor ref="skuEditor" v-model:groups="specGroups" v-model:skus="skus" />

    <n-space style="margin-top: 1rem">
      <n-button type="primary" @click="save">保存</n-button>
      <n-button @click="router.push('/catalog/products')">返回列表</n-button>
    </n-space>
  </n-card>
</template>

<style scoped>
.row-4 {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.75rem 1rem;
  align-items: start;
}
.row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem 1rem;
  align-items: start;
}
.field-hint {
  display: block;
  margin-top: 0.25rem;
  font-size: 0.75rem;
  color: #888;
}
.muted {
  color: #8a8378;
  font-size: 0.8125rem;
}
@media (max-width: 900px) {
  .row-4 {
    grid-template-columns: 1fr 1fr;
  }
  .row-2 {
    grid-template-columns: 1fr;
  }
}
</style>
