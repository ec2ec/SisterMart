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
  NPagination,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'
import { useTableRowSelect } from '../composables/useTableRowSelect'
import ProductPickerDialog, {
  type ProductPickResult,
  type ProductSku,
} from '../components/ProductPickerDialog.vue'

type Sku = ProductSku

type SeckillItem = {
  id?: number
  product_code: string
  sku_id: number
  seckill_price: number
  origin_price: number
  stock: number
  sold?: number
  name?: { 'zh-HK': string; en: string }
  product_label?: string
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
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const { rowAttrs } = useTableRowSelect()
const skuCache = ref<Record<string, Sku[]>>({})
const labelCache = ref<Record<string, string>>({})
const editingId = ref<number | null>(null)
const saving = ref(false)
const pickerShow = ref(false)
/** null = 新增；number = 替換該行商品 */
const pickerTarget = ref<number | null>(null)

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

const formTitle = computed(() => (editingId.value ? `編輯秒殺活動 #${editingId.value}` : '新建秒殺活動'))

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

function productLabel(item: SeckillItem) {
  return item.product_label || labelCache.value[item.product_code] || item.product_code || '未選商品'
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

function openAddPicker() {
  pickerTarget.value = null
  pickerShow.value = true
}

function openReplacePicker(idx: number) {
  pickerTarget.value = idx
  pickerShow.value = true
}

function cacheFromPick(result: ProductPickResult) {
  skuCache.value[result.code] = result.skus
  labelCache.value[result.code] =
    `${result.code} · ${result.name['zh-HK']}` + (result.name.en ? ` / ${result.name.en}` : '')
}

function onPicked(result: ProductPickResult) {
  if (!result.sku) {
    message.warning('請選擇 SKU')
    return
  }
  cacheFromPick(result)
  const next: SeckillItem = {
    product_code: result.code,
    sku_id: result.sku.id,
    seckill_price: Number(result.sku.price),
    origin_price: Number(result.sku.compare_at || result.sku.price),
    stock: 10,
    product_label: labelCache.value[result.code],
  }
  if (pickerTarget.value == null) {
    form.items.push(next)
  } else {
    const prev = form.items[pickerTarget.value]
    form.items[pickerTarget.value] = {
      ...next,
      id: prev?.id,
      sold: prev?.sold,
      stock: prev?.stock ?? 10,
      seckill_price: prev?.seckill_price ?? next.seckill_price,
      origin_price: prev?.origin_price ?? next.origin_price,
      sku_id: result.sku.id,
      product_code: result.code,
      product_label: next.product_label,
    }
  }
  pickerTarget.value = null
}

function removeItem(idx: number) {
  form.items.splice(idx, 1)
}

function onSkuChange(item: SeckillItem) {
  const sku = (skuCache.value[item.product_code] || []).find((s) => s.id === item.sku_id)
  if (!sku) return
  item.seckill_price = Number(sku.price)
  item.origin_price = Number(sku.compare_at || sku.price)
}

function skuOptions(productCode: string) {
  return (skuCache.value[productCode] || []).map((s) => ({
    label:
      `${s.code} · 售$${s.price}` +
      (s.agent_price != null && Number(s.agent_price) > 0 ? ` · 代$${s.agent_price}` : '') +
      `（庫存 ${s.stock}）`,
    value: s.id,
  }))
}

async function ensureProductMeta(code: string) {
  if (!code || skuCache.value[code]) return
  try {
    const detail = await api<{
      code: string
      name: { 'zh-HK': string; en: string }
      skus: Sku[]
    }>(`/api/v1/admin/products/${encodeURIComponent(code)}`, {}, true)
    skuCache.value[code] = detail.skus || []
    labelCache.value[code] =
      `${detail.code} · ${detail.name['zh-HK']}` +
      (detail.name.en ? ` / ${detail.name.en}` : '')
  } catch {
    labelCache.value[code] = code
    skuCache.value[code] = []
  }
}

async function load() {
  const data = await api<{ items: Session[] }>('/api/v1/admin/seckill/sessions', {}, true)
  rows.value = data.items
}

async function edit(row: Session) {
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
    name: it.name,
    product_label: it.name?.['zh-HK']
      ? `${it.product_code} · ${it.name['zh-HK']}`
      : it.product_code,
  }))
  await Promise.all(
    [...new Set(form.items.map((it) => it.product_code).filter(Boolean))].map((code) =>
      ensureProductMeta(code),
    ),
  )
  form.items.forEach((it) => {
    if (labelCache.value[it.product_code]) {
      it.product_label = labelCache.value[it.product_code]
    }
  })
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
  if (form.items.some((it) => !it.product_code || !it.sku_id)) {
    message.warning('請為每個商品選擇商品與 SKU')
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
  await load()
})
</script>

<template>
  <n-space vertical size="large">
    <n-card :title="formTitle">
      <n-form label-placement="left" label-width="6rem" style="max-width: 44rem">
        <n-form-item label="活動代碼">
          <n-input v-model:value="form.code" :disabled="!!editingId" placeholder="如 sk-spring" />
        </n-form-item>
        <n-form-item label="活動名稱"><n-input v-model:value="form.name_zh" placeholder="中文名" /></n-form-item>
        <n-form-item label="英文名"><n-input v-model:value="form.name_en" /></n-form-item>
        <n-form-item label="開始時間">
          <input v-model="form.starts_at" type="datetime-local" class="dt" />
        </n-form-item>
        <n-form-item label="結束時間">
          <input v-model="form.ends_at" type="datetime-local" class="dt" />
        </n-form-item>
      </n-form>

      <n-space vertical style="margin-bottom: 1rem">
        <n-space align="center">
          <strong>活動商品</strong>
          <n-button size="small" type="primary" secondary @click="openAddPicker">加入商品</n-button>
        </n-space>
        <div v-for="(item, idx) in form.items" :key="idx" class="item-row">
          <div class="picked">
            <span class="picked-label" :title="productLabel(item)">{{ productLabel(item) }}</span>
            <n-button size="tiny" quaternary @click="openReplacePicker(idx)">更換</n-button>
          </div>
          <n-select
            v-model:value="item.sku_id"
            :options="skuOptions(item.product_code)"
            placeholder="SKU"
            style="min-width: 12rem"
            @update:value="onSkuChange(item)"
          />
          <n-input-number v-model:value="item.seckill_price" :min="0.01" :step="1" placeholder="秒殺價" />
          <n-input-number v-model:value="item.origin_price" :min="0" :step="1" placeholder="原價" />
          <n-input-number v-model:value="item.stock" :min="0" placeholder="上架數量" />
          <span v-if="item.sold != null" class="sold">秒殺已售 {{ item.sold }}（只讀）</span>
          <n-button size="tiny" @click="removeItem(idx)">移除</n-button>
        </div>
      </n-space>

      <n-space>
        <n-button type="primary" :loading="saving" @click="save">{{ editingId ? '保存修改' : '新建活動' }}</n-button>
        <n-button v-if="editingId" @click="resetForm">取消編輯</n-button>
      </n-space>
    </n-card>

    <n-card title="秒殺活動列表">
      <div class="table-wrap">
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
          <tr v-for="row in paged" :key="row.id" v-bind="rowAttrs(row.id)">
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

    <ProductPickerDialog
      v-model:show="pickerShow"
      title="選擇秒殺商品"
      :require-sku="true"
      @select="onPicked"
    />
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
.picked {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  min-width: 12rem;
  max-width: 18rem;
  padding: 0.25rem 0.5rem;
  border: 1px solid #e8e8e8;
  border-radius: 0.25rem;
  background: #fafafa;
}
.picked-label {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.8125rem;
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

.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
</style>
