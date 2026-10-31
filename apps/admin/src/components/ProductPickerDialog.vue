<script setup lang="ts">
import { showError } from '../feedback'
import { computed, ref, watch } from 'vue'
import {
  NButton,
  NEmpty,
  NInput,
  NModal,
  NRadio,
  NRadioGroup,
  NSpace,
  NSpin,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import { assetUrl } from '../utils/assetUrl'
import { useTableRowSelect } from '../composables/useTableRowSelect'

export type ProductSku = {
  id: number
  code: string
  price: number
  compare_at: number
  agent_price?: number
  cost_price?: number
  stock: number
  image?: string | null
}

export type ProductPickResult = {
  code: string
  db_id: number
  name: { 'zh-HK': string; en: string }
  image: string
  price: number
  status: number
  skus: ProductSku[]
  sku: ProductSku | null
}

type ListRow = {
  db_id: number
  code: string
  name: { 'zh-HK': string; en: string }
  price: number
  stock: number
  status: number
  image: string
}

const props = withDefaults(
  defineProps<{
    show: boolean
    title?: string
    /** 為 true 時必須選定 SKU 後才能確認（秒殺 / 積分兌換） */
    requireSku?: boolean
  }>(),
  {
    title: '選擇商品',
    requireSku: true,
  },
)

const emit = defineEmits<{
  'update:show': [value: boolean]
  select: [value: ProductPickResult]
}>()

const message = useMessage()
const keyword = ref('')
const loading = ref(false)
const rows = ref<ListRow[]>([])
const searched = ref(false)

const step = ref<'list' | 'sku'>('list')
const picking = ref(false)
const selected = ref<ProductPickResult | null>(null)
const skuId = ref<number | null>(null)

let searchTimer: ReturnType<typeof setTimeout> | null = null

const visible = computed({
  get: () => props.show,
  set: (v: boolean) => emit('update:show', v),
})

const skuOptions = computed(() => selected.value?.skus || [])

const { rowAttrs } = useTableRowSelect()

function resetState() {
  keyword.value = ''
  rows.value = []
  searched.value = false
  step.value = 'list'
  selected.value = null
  skuId.value = null
  picking.value = false
  if (searchTimer) {
    clearTimeout(searchTimer)
    searchTimer = null
  }
}

watch(
  () => props.show,
  (v) => {
    if (v) {
      resetState()
      void load()
    }
  },
)

async function load() {
  loading.value = true
  try {
    const q = keyword.value.trim()
    const params = new URLSearchParams()
    if (q) params.set('q', q)
    params.set('limit', '50')
    const qs = params.toString()
    const data = await api<{ items: ListRow[] }>(
      `/api/v1/admin/products${qs ? `?${qs}` : ''}`,
      {},
      true,
    )
    rows.value = data.items || []
    searched.value = true
  } catch (e: unknown) {
    showError(e instanceof Error ? e.message : '搜索失敗')
  } finally {
    loading.value = false
  }
}

function scheduleSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    void load()
  }, 280)
}

function close() {
  visible.value = false
}

function confirm(result: ProductPickResult) {
  emit('select', result)
  close()
}

async function pickProduct(row: ListRow) {
  picking.value = true
  try {
    const detail = await api<{
      db_id?: number
      code: string
      name: { 'zh-HK': string; en: string }
      image?: string
      price?: number
      status?: number
      skus: ProductSku[]
    }>(`/api/v1/admin/products/${encodeURIComponent(row.code)}`, {}, true)

    const skus = detail.skus || []
    const result: ProductPickResult = {
      code: detail.code,
      db_id: detail.db_id ?? row.db_id,
      name: detail.name || row.name,
      image: detail.image || row.image || '',
      price: Number(detail.price ?? row.price ?? 0),
      status: detail.status ?? row.status,
      skus,
      sku: null,
    }

    if (!props.requireSku) {
      confirm(result)
      return
    }

    if (skus.length === 0) {
      message.warning('該商品沒有可用 SKU')
      return
    }

    if (skus.length === 1) {
      result.sku = skus[0]
      confirm(result)
      return
    }

    selected.value = result
    skuId.value = skus[0].id
    step.value = 'sku'
  } catch (e: unknown) {
    showError(e instanceof Error ? e.message : '加載商品失敗')
  } finally {
    picking.value = false
  }
}

function confirmSku() {
  if (!selected.value || skuId.value == null) {
    message.warning('請選擇 SKU')
    return
  }
  const sku = selected.value.skus.find((s) => s.id === skuId.value) || null
  if (!sku) {
    message.warning('請選擇 SKU')
    return
  }
  confirm({ ...selected.value, sku })
}

function backToList() {
  step.value = 'list'
  selected.value = null
  skuId.value = null
}

function skuLabel(s: ProductSku) {
  const agent =
    s.agent_price != null && Number(s.agent_price) > 0 ? ` · 代$${s.agent_price}` : ''
  return `${s.code} · 售$${s.price}${agent}（庫存 ${s.stock}）`
}
</script>

<template>
  <n-modal
    v-model:show="visible"
    preset="card"
    :title="title"
    style="width: min(52rem, 96vw)"
    :mask-closable="false"
   :close-on-esc="false">
    <template v-if="step === 'list'">
      <n-space style="margin-bottom: 0.75rem">
        <n-input
          v-model:value="keyword"
          clearable
          placeholder="搜索中英文名稱 / 代碼 / ID"
          style="width: 18rem"
          @keyup.enter="load"
          @update:value="scheduleSearch"
        />
        <n-button :loading="loading" @click="load">搜索</n-button>
      </n-space>

      <n-spin :show="loading || picking">
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th class="col-thumb">圖</th>
                <th>代碼</th>
                <th>名稱</th>
                <th>售價起</th>
                <th>庫存</th>
                <th>狀態</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.code" v-bind="rowAttrs(row.code)" :class="{ off: row.status !== 1 }">
                <td class="col-thumb">
                  <img
                    v-if="row.image"
                    class="thumb"
                    :src="assetUrl(row.image)"
                    :alt="row.name['zh-HK']"
                  />
                  <span v-else class="thumb placeholder" />
                </td>
                <td>{{ row.code }}</td>
                <td class="col-name">
                  <div class="name-zh">{{ row.name['zh-HK'] }}</div>
                  <div class="name-en">{{ row.name.en }}</div>
                </td>
                <td>{{ row.price }}</td>
                <td>{{ row.stock ?? 0 }}</td>
                <td>{{ row.status === 1 ? '上架' : '下架' }}</td>
                <td>
                  <n-button size="tiny" type="primary" @click="pickProduct(row)">選擇</n-button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <n-empty
          v-if="searched && !loading && !rows.length"
          description="沒有匹配商品"
          style="padding: 1.5rem 0"
        />
      </n-spin>
    </template>

    <template v-else>
      <p class="picked">
        已選商品：
        <strong>{{ selected?.code }}</strong>
        · {{ selected?.name['zh-HK'] }}
        <span v-if="selected?.name.en" class="muted"> / {{ selected.name.en }}</span>
      </p>
      <p class="hint">請選擇 SKU</p>
      <n-radio-group v-model:value="skuId" name="product-picker-sku">
        <n-space vertical>
          <n-radio v-for="s in skuOptions" :key="s.id" :value="s.id">
            {{ skuLabel(s) }}
          </n-radio>
        </n-space>
      </n-radio-group>
      <n-space style="margin-top: 1rem">
        <n-button type="primary" @click="confirmSku">確認選擇</n-button>
        <n-button @click="backToList">返回列表</n-button>
      </n-space>
    </template>
  </n-modal>
</template>

<style scoped>
.table-wrap {
  max-height: min(28rem, 60vh);
  overflow: auto;
}
.table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
}
th,
td {
  border-bottom: 1px solid #eee;
  padding: 0.5rem;
  text-align: left;
  font-size: 0.875rem;
  vertical-align: middle;
}
.col-thumb {
  width: 3rem;
}
.thumb {
  width: 2.5rem;
  height: 2.5rem;
  object-fit: cover;
  border-radius: 0.25rem;
  display: block;
  background: #f5f5f5;
}
.thumb.placeholder {
  background: #f0f0f0;
}
.col-name .name-zh {
  font-weight: 600;
}
.col-name .name-en {
  color: #888;
  font-size: 0.75rem;
}
.off {
  opacity: 0.55;
}
.picked {
  margin: 0 0 0.75rem;
  font-size: 0.875rem;
}
.hint {
  margin: 0 0 0.5rem;
  color: #6d675f;
  font-size: 0.8125rem;
}
.muted {
  color: #888;
  font-weight: 400;
}
</style>
