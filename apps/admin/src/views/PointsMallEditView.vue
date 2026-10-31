<script setup lang="ts">
import { showError } from '../feedback'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  NButton,
  NCard,
  NFormItem,
  NInput,
  NInputNumber,
  NSpace,
  NSpin,
  NSwitch,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import ProductPickerDialog, { type ProductPickResult } from '../components/ProductPickerDialog.vue'

type Item = {
  points_mall_item_id: number
  id: string
  product_id: string
  sku_id: number
  name: { 'zh-HK': string; en: string }
  image: string
  points_cost: number
  stock: number
  sold: number
  status: number
  detail: { 'zh-HK': string; en: string }
}

const route = useRoute()
const router = useRouter()
const message = useMessage()
const loading = ref(false)
const saving = ref(false)
const pickerShow = ref(false)

const isNew = computed(() => String(route.params.id) === 'new')
const pageTitle = computed(() =>
  isNew.value ? '新增積分商品' : `編輯積分商品 #${route.params.id}`,
)

const form = ref({
  id: 0,
  code: '',
  product_code: '',
  sku_id: 0,
  sku_label: '',
  product_label: '',
  points_cost: 100,
  stock: 10,
  status: true,
  name_zh: '',
  name_en: '',
  image_url: '',
  detail_zh: '',
  detail_en: '',
})

const pickedSummary = computed(() => {
  if (!form.value.product_code) return ''
  const base = form.value.product_label || form.value.product_code
  const sku = form.value.sku_label || (form.value.sku_id ? `SKU #${form.value.sku_id}` : '')
  return sku ? `${base} · ${sku}` : base
})

function blankForm() {
  form.value = {
    id: 0,
    code: '',
    product_code: '',
    sku_id: 0,
    sku_label: '',
    product_label: '',
    points_cost: 100,
    stock: 10,
    status: true,
    name_zh: '',
    name_en: '',
    image_url: '',
    detail_zh: '',
    detail_en: '',
  }
}

function fillFromItem(row: Item) {
  form.value = {
    id: row.points_mall_item_id,
    code: row.id,
    product_code: row.product_id,
    sku_id: row.sku_id,
    sku_label: `SKU #${row.sku_id}`,
    product_label: `${row.product_id} · ${row.name['zh-HK']}`,
    points_cost: row.points_cost,
    stock: row.stock,
    status: row.status === 1,
    name_zh: row.name['zh-HK'],
    name_en: row.name.en,
    image_url: row.image || '',
    detail_zh: row.detail?.['zh-HK'] || '',
    detail_en: row.detail?.en || '',
  }
}

async function load() {
  if (isNew.value) {
    blankForm()
    return
  }
  loading.value = true
  try {
    const id = Number(route.params.id)
    const data = await api<{ items: Item[] }>('/api/v1/admin/points-mall/items', {}, true)
    const row = (data.items || []).find((it) => it.points_mall_item_id === id)
    if (!row) {
      showError('積分商品不存在')
      router.replace({ path: '/marketing/points', query: { tab: 'mall' } })
      return
    }
    fillFromItem(row)
  } catch (e: unknown) {
    showError(e instanceof Error ? e.message : '加載失敗')
  } finally {
    loading.value = false
  }
}

function onPicked(result: ProductPickResult) {
  if (!result.sku) {
    message.warning('請選擇 SKU')
    return
  }
  form.value.product_code = result.code
  form.value.sku_id = result.sku.id
  form.value.product_label =
    `${result.code} · ${result.name['zh-HK']}` + (result.name.en ? ` / ${result.name.en}` : '')
  form.value.sku_label =
    `${result.sku.code} · 售$${result.sku.price}` +
    (result.sku.agent_price != null && Number(result.sku.agent_price) > 0
      ? ` · 代$${result.sku.agent_price}`
      : '')
  if (!form.value.name_zh.trim()) form.value.name_zh = result.name['zh-HK'] || ''
  if (!form.value.name_en.trim()) form.value.name_en = result.name.en || ''
  if (!form.value.image_url.trim() && result.image) {
    form.value.image_url = result.sku.image || result.image
  }
  if (!form.value.stock || form.value.stock <= 0) {
    form.value.stock = Math.max(0, Number(result.sku.stock) || 0)
  }
}

async function save() {
  if (!form.value.product_code || !form.value.sku_id) {
    message.warning('請先選擇商品與 SKU')
    return
  }
  saving.value = true
  try {
    const body = {
      code: form.value.code || undefined,
      product_code: form.value.product_code,
      sku_id: form.value.sku_id,
      points_cost: form.value.points_cost,
      stock: form.value.stock,
      status: form.value.status ? 1 : 0,
      name: { 'zh-HK': form.value.name_zh, en: form.value.name_en },
      image_url: form.value.image_url,
      detail: { 'zh-HK': form.value.detail_zh, en: form.value.detail_en },
    }
    if (form.value.id) {
      await api(`/api/v1/admin/points-mall/items/${form.value.id}`, {
        method: 'PUT',
        body: JSON.stringify(body),
      }, true)
    } else {
      await api('/api/v1/admin/points-mall/items', {
        method: 'POST',
        body: JSON.stringify(body),
      }, true)
    }
    message.success('積分商品已保存')
    router.push({ path: '/marketing/points', query: { tab: 'mall' } })
  } catch (e: unknown) {
    showError(e instanceof Error ? e.message : '保存失敗')
  } finally {
    saving.value = false
  }
}

function goBack() {
  router.push({ path: '/marketing/points', query: { tab: 'mall' } })
}

watch(
  () => route.params.id,
  () => {
    void load()
  },
)

onMounted(load)
</script>

<template>
  <n-card :title="pageTitle">
    <template #header-extra>
      <n-button quaternary @click="goBack">返回列表</n-button>
    </template>

    <n-spin :show="loading">
      <div class="form-grid">
        <n-form-item label="code" :show-feedback="false">
          <n-input v-model:value="form.code" placeholder="可空自動生成" />
        </n-form-item>
        <n-form-item label="關聯商品" :show-feedback="false">
          <div class="pick-cell">
            <n-button size="small" @click="pickerShow = true">
              {{ form.product_code ? '更換' : '選擇商品' }}
            </n-button>
            <span v-if="pickedSummary" class="picked" :title="pickedSummary">{{ pickedSummary }}</span>
            <span v-else class="hint">搜索中英文名/代碼</span>
          </div>
        </n-form-item>
        <n-form-item label="上架" :show-feedback="false">
          <n-switch v-model:value="form.status" />
        </n-form-item>

        <n-form-item label="繁中名" :show-feedback="false">
          <n-input v-model:value="form.name_zh" />
        </n-form-item>
        <n-form-item label="英文名" :show-feedback="false">
          <n-input v-model:value="form.name_en" />
        </n-form-item>
        <n-form-item label="圖片 URL" :show-feedback="false">
          <n-input v-model:value="form.image_url" placeholder="/storage/..." />
        </n-form-item>

        <n-form-item label="積分價" :show-feedback="false">
          <n-input-number v-model:value="form.points_cost" :min="1" style="width: 100%" />
        </n-form-item>
        <n-form-item label="庫存" :show-feedback="false">
          <n-input-number v-model:value="form.stock" :min="0" style="width: 100%" />
        </n-form-item>
        <div class="grid-spacer" />

        <n-form-item class="span-3" label="繁中詳情" :show-feedback="false">
          <n-input v-model:value="form.detail_zh" type="textarea" :autosize="{ minRows: 3, maxRows: 8 }" />
        </n-form-item>
        <n-form-item class="span-3" label="英文詳情" :show-feedback="false">
          <n-input v-model:value="form.detail_en" type="textarea" :autosize="{ minRows: 3, maxRows: 8 }" />
        </n-form-item>
      </div>

      <n-space style="margin-top: 1rem">
        <n-button type="primary" :loading="saving" @click="save">保存</n-button>
        <n-button @click="goBack">取消</n-button>
      </n-space>
    </n-spin>

    <ProductPickerDialog
      v-model:show="pickerShow"
      title="選擇積分兌換商品"
      :require-sku="true"
      @select="onPicked"
    />
  </n-card>
</template>

<style scoped>
.form-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem 0.75rem;
  align-items: start;
}

.form-grid :deep(.n-form-item) {
  margin-bottom: 0;
}

.form-grid :deep(.n-form-item-label) {
  font-size: 0.8125rem;
}

.span-3 {
  grid-column: 1 / -1;
}

.grid-spacer {
  min-height: 1px;
}

.pick-cell {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
  width: 100%;
}

.picked {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8125rem;
  color: #333;
}

.hint {
  font-size: 0.75rem;
  color: #888;
}

@media (max-width: 900px) {
  .form-grid {
    grid-template-columns: 1fr 1fr;
  }
  .span-3 {
    grid-column: 1 / -1;
  }
}

@media (max-width: 560px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
