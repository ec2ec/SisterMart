<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import {
  NButton,
  NCard,
  NForm,
  NFormItem,
  NInput,
  NInputNumber,
  NSelect,
  NSpace,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'

type Sku = { id: number; code: string; price: number; compare_at: number; stock: number; image?: string }
type ProductOpt = { label: string; value: string; skus: Sku[] }

type SeckillItem = {
  id?: number
  product_code: string
  sku_id: number
  seckill_price: number
  origin_price: number
  stock: number
  sold?: number
  name?: { 'zh-HK': string; en: string }
}

type Session = {
  id: number
  code: string
  name: { 'zh-HK': string; en: string }
  status: string
  starts_at: string
  ends_at: string
  items: SeckillItem[]
}

const message = useMessage()
const rows = ref<Session[]>([])
const products = ref<ProductOpt[]>([])
const editingId = ref<number | null>(null)
const saving = ref(false)

const form = reactive({
  code: '',
  name_zh: '',
  name_en: '',
  starts_at: '',
  ends_at: '',
  items: [] as SeckillItem[],
})

const statusType = (s: string) =>
  s === 'live' ? 'success' : s === 'upcoming' ? 'warning' : 'default'

const statusLabel = (s: string) =>
  s === 'live' ? '進行中' : s === 'upcoming' ? '未開始' : '已結束'

const formTitle = computed(() => (editingId.value ? `編輯場次 #${editingId.value}` : '新建場次'))

function pad(n: number) {
  return String(n).padStart(2, '0')
}
function toLocalInput(d: Date) {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}
function fromLocalInput(v: string) {
  if (!v) return ''
  return v.replace('T', ' ') + ':00'
}
function toLocalFromApi(v: string) {
  if (!v) return ''
  return v.slice(0, 16).replace(' ', 'T')
}

function resetForm() {
  editingId.value = null
  const start = new Date(Date.now() + 3600_000)
  const end = new Date(Date.now() + 86400_000 * 2)
  form.code = `sk-${Date.now().toString(36).slice(-6)}`
  form.name_zh = ''
  form.name_en = ''
  form.starts_at = toLocalInput(start)
  form.ends_at = toLocalInput(end)
  form.items = []
}

function addItem() {
  const first = products.value[0]
  const sku = first?.skus[0]
  form.items.push({
    product_code: first?.value || '',
    sku_id: sku?.id || 0,
    seckill_price: sku ? Number(sku.price) : 0,
    origin_price: sku ? Number(sku.compare_at || sku.price) : 0,
    stock: 10,
  })
}

function removeItem(idx: number) {
  form.items.splice(idx, 1)
}

function onProductChange(item: SeckillItem) {
  const p = products.value.find((x) => x.value === item.product_code)
  const sku = p?.skus[0]
  if (sku) {
    item.sku_id = sku.id
    item.seckill_price = Number(sku.price)
    item.origin_price = Number(sku.compare_at || sku.price)
  } else {
    item.sku_id = 0
  }
}

function skuOptions(productCode: string) {
  const p = products.value.find((x) => x.value === productCode)
  return (p?.skus || []).map((s) => ({
    label: `${s.code} · $${s.price}（庫存 ${s.stock}）`,
    value: s.id,
  }))
}

async function loadProducts() {
  const data = await api<{ items: Array<{ code: string; name: { 'zh-HK': string }; db_id?: number }> }>(
    '/api/v1/admin/products',
    {},
    true,
  )
  const opts: ProductOpt[] = []
  for (const row of data.items || []) {
    const detail = await api<{
      code: string
      name: { 'zh-HK': string }
      skus: Sku[]
    }>(`/api/v1/admin/products/${row.code}`, {}, true)
    opts.push({
      label: `${detail.code} · ${detail.name['zh-HK']}`,
      value: detail.code,
      skus: detail.skus || [],
    })
  }
  products.value = opts
}

async function load() {
  const data = await api<{ items: Session[] }>('/api/v1/admin/seckill/sessions', {}, true)
  rows.value = data.items
}

function edit(row: Session) {
  editingId.value = row.id
  form.code = row.code
  form.name_zh = row.name['zh-HK']
  form.name_en = row.name.en
  form.starts_at = toLocalFromApi(row.starts_at)
  form.ends_at = toLocalFromApi(row.ends_at)
  form.items = (row.items || []).map((it) => ({
    id: it.id,
    product_code: it.product_code,
    sku_id: it.sku_id,
    seckill_price: Number(it.seckill_price),
    origin_price: Number(it.origin_price),
    stock: Number(it.stock),
    sold: it.sold,
  }))
}

async function save() {
  if (!form.name_zh.trim()) {
    message.warning('請填寫中文名')
    return
  }
  if (!form.items.length) {
    message.warning('請至少加一個秒殺商品')
    return
  }
  saving.value = true
  try {
    const body = {
      code: form.code.trim(),
      name_zh: form.name_zh.trim(),
      name_en: form.name_en.trim() || form.name_zh.trim(),
      starts_at: fromLocalInput(form.starts_at),
      ends_at: fromLocalInput(form.ends_at),
      items: form.items.map((it) => ({
        id: it.id,
        product_code: it.product_code,
        sku_id: it.sku_id,
        seckill_price: it.seckill_price,
        origin_price: it.origin_price,
        stock: it.stock,
      })),
    }
    if (editingId.value) {
      await api(`/api/v1/admin/seckill/sessions/${editingId.value}`, {
        method: 'PUT',
        body: JSON.stringify(body),
      }, true)
      message.success('已更新')
    } else {
      await api('/api/v1/admin/seckill/sessions', {
        method: 'POST',
        body: JSON.stringify(body),
      }, true)
      message.success('已創建')
    }
    resetForm()
    await load()
  } catch (e: any) {
    message.error(e?.message || '保存失敗')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  resetForm()
  await Promise.all([load(), loadProducts()])
})
</script>

<template>
  <n-space vertical size="large">
    <n-card :title="formTitle">
      <n-form label-placement="left" label-width="6rem" style="max-width: 44rem">
        <n-form-item label="場次代碼">
          <n-input v-model:value="form.code" :disabled="!!editingId" placeholder="如 sk-spring" />
        </n-form-item>
        <n-form-item label="中文名"><n-input v-model:value="form.name_zh" /></n-form-item>
        <n-form-item label="英文名"><n-input v-model:value="form.name_en" /></n-form-item>
        <n-form-item label="開始">
          <input v-model="form.starts_at" type="datetime-local" class="dt" />
        </n-form-item>
        <n-form-item label="結束">
          <input v-model="form.ends_at" type="datetime-local" class="dt" />
        </n-form-item>
      </n-form>

      <n-space vertical style="margin-bottom: 1rem">
        <n-space align="center">
          <strong>秒殺商品</strong>
          <n-button size="small" @click="addItem">加一行</n-button>
        </n-space>
        <div v-for="(item, idx) in form.items" :key="idx" class="item-row">
          <n-select
            v-model:value="item.product_code"
            :options="products"
            filterable
            placeholder="商品"
            style="min-width: 12rem"
            @update:value="onProductChange(item)"
          />
          <n-select
            v-model:value="item.sku_id"
            :options="skuOptions(item.product_code)"
            placeholder="SKU"
            style="min-width: 12rem"
          />
          <n-input-number v-model:value="item.seckill_price" :min="0.01" :step="1" placeholder="秒殺價" />
          <n-input-number v-model:value="item.origin_price" :min="0" :step="1" placeholder="原價" />
          <n-input-number v-model:value="item.stock" :min="0" placeholder="庫存" />
          <span v-if="item.sold != null" class="sold">已售 {{ item.sold }}</span>
          <n-button size="tiny" @click="removeItem(idx)">移除</n-button>
        </div>
      </n-space>

      <n-space>
        <n-button type="primary" :loading="saving" @click="save">{{ editingId ? '保存修改' : '新建場次' }}</n-button>
        <n-button v-if="editingId" @click="resetForm">取消編輯</n-button>
      </n-space>
    </n-card>

    <n-card title="場次列表">
      <table class="table">
        <thead>
          <tr>
            <th>代碼</th>
            <th>名稱</th>
            <th>狀態</th>
            <th>時間窗</th>
            <th>商品數</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td>{{ row.code }}</td>
            <td>{{ row.name['zh-HK'] }}</td>
            <td>
              <n-tag size="small" :type="statusType(row.status)">{{ statusLabel(row.status) }}</n-tag>
            </td>
            <td class="muted">{{ row.starts_at }} → {{ row.ends_at }}</td>
            <td>{{ row.items?.length || 0 }}</td>
            <td><n-button size="tiny" @click="edit(row)">編輯</n-button></td>
          </tr>
        </tbody>
      </table>
    </n-card>
  </n-space>
</template>

<style scoped>
.table {
  width: 100%;
  border-collapse: collapse;
}
th,
td {
  border-bottom: 1px solid #eee;
  padding: 0.5rem;
  text-align: left;
  font-size: 0.875rem;
}
.item-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 0.5rem;
}
.sold {
  font-size: 0.75rem;
  color: #6d675f;
}
.muted {
  font-size: 0.75rem;
  color: #6d675f;
}
.dt {
  min-width: 14rem;
  padding: 0.35rem 0.5rem;
  border: 1px solid #d0d0d0;
  border-radius: 0.25rem;
  font: inherit;
}
</style>
