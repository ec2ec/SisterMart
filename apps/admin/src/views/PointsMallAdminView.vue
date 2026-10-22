<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NFormItem,
  NInput,
  NInputNumber,
  NSpace,
  NSwitch,
  useMessage,
  NPagination,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'
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

const message = useMessage()
const items = ref<Item[]>([])
const { page, pageSize, total, paged } = useClientPager(items, 30)
const pickerShow = ref(false)
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

async function load() {
  const data = await api<{ items: Item[] }>('/api/v1/admin/points-mall/items', {}, true)
  items.value = data.items
}

function reset() {
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

function edit(row: Item) {
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
    await api('/api/v1/admin/points-mall/items', { method: 'POST', body: JSON.stringify(body) }, true)
  }
  message.success('積分商品已保存')
  reset()
  await load()
}

async function remove(id: number) {
  await api(`/api/v1/admin/points-mall/items/${id}`, { method: 'DELETE' }, true)
  message.success('已刪除')
  if (form.value.id === id) reset()
  await load()
}

onMounted(load)
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="積分商城商品">
      <div v-for="row in paged" :key="row.points_mall_item_id" class="row">
        <div>
          <strong>{{ row.name['zh-HK'] }}</strong>
          <p>
            {{ row.id }} · {{ row.product_id }} / sku {{ row.sku_id }} · {{ row.points_cost }} 積分 · 庫存
            {{ row.stock }} · 已兌 {{ row.sold }} · {{ row.status === 1 ? '上架' : '下架' }}
          </p>
        </div>
        <n-space>
          <n-button size="tiny" @click="edit(row)">編輯</n-button>
          <n-button size="tiny" type="error" secondary @click="remove(row.points_mall_item_id)">刪除</n-button>
        </n-space>
      </div>
    
    <div class="pager">
      <n-pagination
        v-model:page="page"
        v-model:page-size="pageSize"
        :item-count="total"
        :page-sizes="[30, 50, 100]"
        show-size-picker
      />
    </div>
    </n-card>
    <n-card :title="form.id ? `編輯 #${form.id}` : '新增積分商品'">
      <n-form-item label="code"><n-input v-model:value="form.code" placeholder="可空自動生成" /></n-form-item>
      <n-form-item label="關聯商品">
        <n-space vertical style="width: 100%">
          <n-space>
            <n-button @click="pickerShow = true">{{ form.product_code ? '更換商品' : '選擇商品' }}</n-button>
            <span v-if="pickedSummary" class="picked">{{ pickedSummary }}</span>
            <span v-else class="hint">通過彈框搜索中英文名稱或代碼選擇</span>
          </n-space>
        </n-space>
      </n-form-item>
      <n-form-item label="積分價"><n-input-number v-model:value="form.points_cost" :min="1" /></n-form-item>
      <n-form-item label="庫存"><n-input-number v-model:value="form.stock" :min="0" /></n-form-item>
      <n-form-item label="繁中名"><n-input v-model:value="form.name_zh" /></n-form-item>
      <n-form-item label="英文名"><n-input v-model:value="form.name_en" /></n-form-item>
      <n-form-item label="圖片 URL"><n-input v-model:value="form.image_url" /></n-form-item>
      <n-form-item label="繁中詳情"><n-input v-model:value="form.detail_zh" type="textarea" /></n-form-item>
      <n-form-item label="英文詳情"><n-input v-model:value="form.detail_en" type="textarea" /></n-form-item>
      <n-form-item label="上架"><n-switch v-model:value="form.status" /></n-form-item>
      <n-space>
        <n-button type="primary" @click="save">保存</n-button>
        <n-button v-if="form.id" @click="reset">新建</n-button>
      </n-space>
    </n-card>

    <ProductPickerDialog
      v-model:show="pickerShow"
      title="選擇積分兌換商品"
      :require-sku="true"
      @select="onPicked"
    />
  </n-space>
</template>

<style scoped>
.row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid #eee;
}
.row p {
  margin: 0.15rem 0 0;
  color: #888;
  font-size: 0.8125rem;
}

.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}

.picked {
  font-size: 0.8125rem;
  color: #333;
  max-width: 28rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hint {
  font-size: 0.8125rem;
  color: #888;
}
</style>
