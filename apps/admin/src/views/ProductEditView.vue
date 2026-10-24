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
import WangSimpleEditor from '../components/WangSimpleEditor.vue'
import { toStoredUrl } from '../utils/assetUrl'

type Cat = { id: number; label: string }

const route = useRoute()
const router = useRouter()
const message = useMessage()
const isNew = computed(() => route.params.id === 'new')
const categories = ref<Cat[]>([])
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

const complex = ref(false)
const original = ref<any>(null)
const form = ref({
  code: '',
  category_id: null as number | null,
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
  groupCode: 'style',
  groupNameZh: '款式',
  groupNameEn: 'Style',
  optionACode: 'classic',
  optionAZh: '經典',
  optionAEn: 'Classic',
  optionBCode: 'detail',
  optionBZh: '細節款',
  optionBEn: 'Detail',
  skuAPrice: 0,
  skuACompare: 0,
  skuAAgent: 0,
  skuACost: 0,
  skuAStock: 0,
  skuBPrice: 0,
  skuBCompare: 0,
  skuBAgent: 0,
  skuBCost: 0,
  skuBStock: 0,
  commission_type: null as string | null,
  commission_value: 0,
})

/** 含空選項，可明確置為「不計佣金」；亦支援 clearable 清空 */
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

onMounted(async () => {
  const [cats, tagData, badgeData, attrData] = await Promise.all([
    api<{ items: any[] }>('/api/v1/admin/categories', {}, true),
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
  if (!isNew.value) {
    const p = await api<any>(`/api/v1/admin/products/${route.params.id}`, {}, true)
    original.value = p
    complex.value = (p.spec_groups || []).length > 1 || (p.skus || []).length > 2
    form.value.code = p.code
    form.value.category_id = p.category_id
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
    const g = p.spec_groups?.[0]
    if (g) {
      form.value.groupCode = g.code
      form.value.groupNameZh = g.name['zh-HK']
      form.value.groupNameEn = g.name.en
      const o0 = g.options?.[0]
      const o1 = g.options?.[1]
      if (o0) {
        form.value.optionACode = o0.code
        form.value.optionAZh = o0.name['zh-HK']
        form.value.optionAEn = o0.name.en
      }
      if (o1) {
        form.value.optionBCode = o1.code
        form.value.optionBZh = o1.name['zh-HK']
        form.value.optionBEn = o1.name.en
      }
    }
    const s0 = p.skus?.[0]
    const s1 = p.skus?.[1]
    if (s0) {
      form.value.skuAPrice = s0.price
      form.value.skuACompare = s0.compare_at
      form.value.skuAAgent = Number(s0.agent_price ?? 0)
      form.value.skuACost = Number(s0.cost_price ?? 0)
      form.value.skuAStock = s0.stock
    }
    if (s1) {
      form.value.skuBPrice = s1.price
      form.value.skuBCompare = s1.compare_at
      form.value.skuBAgent = Number(s1.agent_price ?? 0)
      form.value.skuBCost = Number(s1.cost_price ?? 0)
      form.value.skuBStock = s1.stock
    }
    form.value.commission_type = p.commission_type || null
    form.value.commission_value = Number(p.commission_value ?? 0)
  }
})

async function save() {
  const media = form.value.media
    .map((m) => ({ ...m, url: toStoredUrl(m.url) }))
    .filter((m) => m.url)
  const g = form.value.groupCode
  const a = form.value.optionACode
  const b = form.value.optionBCode
  const body: any = {
    code: form.value.code,
    category_id: form.value.category_id,
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
  }
  if (complex.value && original.value) {
    body.spec_groups = (original.value.spec_groups || []).map((group: any) => ({
      code: group.code,
      name: group.name,
      options: (group.options || []).map((opt: any) => ({
        code: opt.code,
        name: opt.name,
        image: opt.image,
      })),
    }))
    body.skus = (original.value.skus || []).map((sku: any) => ({
      code: sku.code,
      price: sku.price,
      compare_at: sku.compare_at,
      agent_price: Number(sku.agent_price ?? 0),
      cost_price: Number(sku.cost_price ?? 0),
      stock: sku.stock,
      image: sku.image,
      option_refs: sku.option_refs,
    }))
  } else {
    body.spec_groups = [
      {
        code: g,
        name: { 'zh-HK': form.value.groupNameZh, en: form.value.groupNameEn },
        options: [
          { code: a, name: { 'zh-HK': form.value.optionAZh, en: form.value.optionAEn }, image: media[0]?.url },
          { code: b, name: { 'zh-HK': form.value.optionBZh, en: form.value.optionBEn }, image: media[1]?.url || media[0]?.url },
        ],
      },
    ]
    body.skus = [
      {
        code: a,
        price: form.value.skuAPrice,
        compare_at: form.value.skuACompare,
        agent_price: form.value.skuAAgent,
        cost_price: form.value.skuACost,
        stock: form.value.skuAStock,
        option_refs: [`${g}:${a}`],
      },
      {
        code: b,
        price: form.value.skuBPrice,
        compare_at: form.value.skuBCompare,
        agent_price: form.value.skuBAgent,
        cost_price: form.value.skuBCost,
        stock: form.value.skuBStock,
        option_refs: [`${g}:${b}`],
      },
    ]
  }
  if (isNew.value) {
    await api('/api/v1/admin/products', { method: 'POST', body: JSON.stringify(body) }, true)
  } else {
    await api(`/api/v1/admin/products/${route.params.id}`, { method: 'PUT', body: JSON.stringify(body) }, true)
  }
  message.success('商品已保存')
  router.push('/catalog/products')
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
      <n-form-item label="上架"><n-switch v-model:value="form.status" /></n-form-item>
      <n-form-item label="疊加數量">
        <n-input-number v-model:value="form.sold_boost" :min="0" style="width: 100%" />
      </n-form-item>
    </div>
    <div class="row-4">
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
    <p v-if="complex" style="color:#6d675f">此商品為多規格（如顏色×尺寸）。保存時會保留原有 SKU 結構，僅更新名稱、媒體、角標等。</p>
    <n-card v-if="!complex" size="small" title="規格（兩檔編輯器）" style="margin-bottom: 1rem">
      <n-space>
        <n-form-item label="規格代碼"><n-input v-model:value="form.groupCode" /></n-form-item>
        <n-form-item label="規格繁中"><n-input v-model:value="form.groupNameZh" /></n-form-item>
        <n-form-item label="規格英文"><n-input v-model:value="form.groupNameEn" /></n-form-item>
      </n-space>
      <n-space>
        <n-form-item label="選項A"><n-input v-model:value="form.optionACode" /></n-form-item>
        <n-form-item label="A 繁中"><n-input v-model:value="form.optionAZh" /></n-form-item>
        <n-form-item label="A 英文"><n-input v-model:value="form.optionAEn" /></n-form-item>
        <n-form-item label="A 售價"><n-input-number v-model:value="form.skuAPrice" /></n-form-item>
        <n-form-item label="A 劃線"><n-input-number v-model:value="form.skuACompare" /></n-form-item>
        <n-form-item label="A 代理價"><n-input-number v-model:value="form.skuAAgent" :min="0" /></n-form-item>
        <n-form-item label="A 成本價"><n-input-number v-model:value="form.skuACost" :min="0" /></n-form-item>
        <n-form-item label="A 庫存"><n-input-number v-model:value="form.skuAStock" :min="0" /></n-form-item>
      </n-space>
      <n-space>
        <n-form-item label="選項B"><n-input v-model:value="form.optionBCode" /></n-form-item>
        <n-form-item label="B 繁中"><n-input v-model:value="form.optionBZh" /></n-form-item>
        <n-form-item label="B 英文"><n-input v-model:value="form.optionBEn" /></n-form-item>
        <n-form-item label="B 售價"><n-input-number v-model:value="form.skuBPrice" /></n-form-item>
        <n-form-item label="B 劃線"><n-input-number v-model:value="form.skuBCompare" /></n-form-item>
        <n-form-item label="B 代理價"><n-input-number v-model:value="form.skuBAgent" :min="0" /></n-form-item>
        <n-form-item label="B 成本價"><n-input-number v-model:value="form.skuBCost" :min="0" /></n-form-item>
        <n-form-item label="B 庫存"><n-input-number v-model:value="form.skuBStock" :min="0" /></n-form-item>
      </n-space>
    </n-card>
    <n-space>
      <n-button type="primary" @click="save">保存</n-button>
      <n-button @click="router.push('/catalog/products')">返回</n-button>
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
