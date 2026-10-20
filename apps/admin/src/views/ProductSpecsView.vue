<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  NButton,
  NCard,
  NFormItem,
  NInput,
  NInputNumber,
  NSpace,
  useMessage,
} from 'naive-ui'
import { api } from '../api'

type Localized = { 'zh-HK': string; en: string }

type SpecOption = { code: string; name: Localized; image?: string | null }
type SpecGroup = { code: string; name: Localized; options: SpecOption[] }
type SkuRow = {
  code: string
  price: number
  compare_at: number
  agent_price: number
  cost_price: number
  stock: number
  image?: string | null
  option_refs: string[]
}

const route = useRoute()
const router = useRouter()
const message = useMessage()
const loading = ref(true)
const productName = ref('')
const productCode = ref('')
const basePayload = ref<Record<string, unknown> | null>(null)
const groups = ref<SpecGroup[]>([])
const skus = ref<SkuRow[]>([])

const title = computed(() => (productName.value ? `規格 · ${productName.value}` : '規格編輯'))

function emptyGroup(): SpecGroup {
  return {
    code: '',
    name: { 'zh-HK': '', en: '' },
    options: [
      { code: '', name: { 'zh-HK': '', en: '' }, image: null },
      { code: '', name: { 'zh-HK': '', en: '' }, image: null },
    ],
  }
}

function addGroup() {
  groups.value.push(emptyGroup())
}

function removeGroup(idx: number) {
  groups.value.splice(idx, 1)
  if (!groups.value.length) groups.value.push(emptyGroup())
}

function addOption(gIdx: number) {
  groups.value[gIdx].options.push({ code: '', name: { 'zh-HK': '', en: '' }, image: null })
}

function removeOption(gIdx: number, oIdx: number) {
  groups.value[gIdx].options.splice(oIdx, 1)
  if (!groups.value[gIdx].options.length) {
    groups.value[gIdx].options.push({ code: '', name: { 'zh-HK': '', en: '' }, image: null })
  }
}

function addSku() {
  skus.value.push({
    code: '',
    price: 0,
    compare_at: 0,
    agent_price: 0,
    cost_price: 0,
    stock: 0,
    image: null,
    option_refs: [],
  })
}

function removeSku(idx: number) {
  skus.value.splice(idx, 1)
  if (!skus.value.length) addSku()
}

function refsText(sku: SkuRow) {
  return (sku.option_refs || []).join(', ')
}

function setRefs(sku: SkuRow, text: string) {
  sku.option_refs = text
    .split(/[,，\s]+/)
    .map((s) => s.trim())
    .filter(Boolean)
}

onMounted(async () => {
  try {
    const p = await api<any>(`/api/v1/admin/products/${route.params.id}`, {}, true)
    productName.value = p.name?.['zh-HK'] || p.code
    productCode.value = p.code
    basePayload.value = {
      code: p.code,
      category_id: p.category_id,
      status: p.status,
      sold_count: p.sold_count,
      sold_boost: p.sold_boost ?? 0,
      name: p.name,
      detail: p.detail,
      summary: p.summary,
      badges: p.badges || [],
      service_tag_ids: p.service_tag_ids || [],
      attr_value_ids: p.attr_value_ids || [],
      media: p.media || [],
      commission_type: p.commission_type,
      commission_value: p.commission_value,
    }
    groups.value = (p.spec_groups || []).map((g: any) => ({
      code: g.code,
      name: { 'zh-HK': g.name['zh-HK'], en: g.name.en },
      options: (g.options || []).map((o: any) => ({
        code: o.code,
        name: { 'zh-HK': o.name['zh-HK'], en: o.name.en },
        image: o.image || null,
      })),
    }))
    if (!groups.value.length) groups.value = [emptyGroup()]
    skus.value = (p.skus || []).map((s: any) => ({
      code: s.code,
      price: Number(s.price || 0),
      compare_at: Number(s.compare_at || 0),
      agent_price: Number(s.agent_price || 0),
      cost_price: Number(s.cost_price || 0),
      stock: Number(s.stock || 0),
      image: s.image || null,
      option_refs: [...(s.option_refs || [])],
    }))
    if (!skus.value.length) addSku()
  } finally {
    loading.value = false
  }
})

async function save() {
  if (!basePayload.value) return
  const body = {
    ...basePayload.value,
    spec_groups: groups.value.map((g) => ({
      code: g.code,
      name: g.name,
      options: g.options.map((o) => ({
        code: o.code,
        name: o.name,
        image: o.image,
      })),
    })),
    skus: skus.value.map((s) => ({
      code: s.code,
      price: s.price,
      compare_at: s.compare_at,
      agent_price: s.agent_price,
      cost_price: s.cost_price,
      stock: s.stock,
      image: s.image,
      option_refs: s.option_refs,
    })),
  }
  await api(`/api/v1/admin/products/${route.params.id}`, { method: 'PUT', body: JSON.stringify(body) }, true)
  message.success('規格已保存')
  router.push('/catalog/specs')
}
</script>

<template>
  <n-card :title="title">
    <p v-if="loading" class="hint">載入中…</p>
    <template v-else>
      <p class="hint">商品代碼 {{ productCode }} · option_refs 格式如 style:classic（多個用逗號分隔）</p>

      <div v-for="(g, gIdx) in groups" :key="gIdx" class="group-block">
        <div class="group-head">
          <strong>規格組 {{ gIdx + 1 }}</strong>
          <n-button size="tiny" @click="removeGroup(gIdx)">刪除組</n-button>
        </div>
        <n-space>
          <n-form-item label="組代碼"><n-input v-model:value="g.code" style="width: 8rem" /></n-form-item>
          <n-form-item label="組繁中"><n-input v-model:value="g.name['zh-HK']" style="width: 8rem" /></n-form-item>
          <n-form-item label="組英文"><n-input v-model:value="g.name.en" style="width: 8rem" /></n-form-item>
        </n-space>
        <div v-for="(o, oIdx) in g.options" :key="oIdx" class="opt-row">
          <n-input v-model:value="o.code" placeholder="選項代碼" style="width: 7rem" />
          <n-input v-model:value="o.name['zh-HK']" placeholder="繁中" style="width: 7rem" />
          <n-input v-model:value="o.name.en" placeholder="英文" style="width: 7rem" />
          <n-button size="tiny" @click="removeOption(gIdx, oIdx)">刪</n-button>
        </div>
        <n-button size="tiny" style="margin-top: 0.5rem" @click="addOption(gIdx)">加選項</n-button>
      </div>
      <n-button style="margin-bottom: 1rem" @click="addGroup">加規格組</n-button>

      <h4 class="sub">SKU</h4>
      <div v-for="(s, sIdx) in skus" :key="sIdx" class="sku-row">
        <n-input v-model:value="s.code" placeholder="SKU 代碼" style="width: 7rem" />
        <n-input-number v-model:value="s.price" placeholder="售價" style="width: 6.5rem" />
        <n-input-number v-model:value="s.compare_at" placeholder="劃線" style="width: 6.5rem" />
        <n-input-number v-model:value="s.agent_price" placeholder="代理價" style="width: 6.5rem" />
        <n-input-number v-model:value="s.cost_price" placeholder="成本價" style="width: 6.5rem" />
        <n-input-number v-model:value="s.stock" :min="0" placeholder="庫存" style="width: 5.5rem" />
        <n-input
          :value="refsText(s)"
          placeholder="option_refs"
          style="width: 14rem"
          @update:value="(v) => setRefs(s, v)"
        />
        <n-button size="tiny" @click="removeSku(sIdx)">刪</n-button>
      </div>
      <n-button style="margin: 0.5rem 0 1rem" @click="addSku">加 SKU</n-button>

      <n-space>
        <n-button type="primary" @click="save">保存規格</n-button>
        <n-button @click="router.push('/catalog/specs')">返回</n-button>
      </n-space>
    </template>
  </n-card>
</template>

<style scoped>
.hint {
  color: #6d675f;
  margin: 0 0 1rem;
  font-size: 0.875rem;
}
.group-block {
  border: 1px solid #eee;
  border-radius: 0.375rem;
  padding: 0.75rem 1rem;
  margin-bottom: 0.75rem;
  background: #fafaf8;
}
.group-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}
.opt-row,
.sku-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 0.4rem;
}
.sub {
  margin: 0 0 0.75rem;
}
</style>
