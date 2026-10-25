<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NCard, NInput, NPagination, NSelect, NSpace, NTag } from 'naive-ui'
import { api } from '../api'
import { assetUrl } from '../utils/assetUrl'
import { useClientPager } from '../composables/useClientPager'

type CatNode = {
  id: number
  code: string
  name: { 'zh-HK': string; en: string }
  children?: CatNode[]
}

type BadgeItem = { code: string; color: string; name: { 'zh-HK': string; en: string } }

type Row = {
  db_id: number
  code: string
  name: { 'zh-HK': string; en: string }
  price: number
  sold: string
  sold_count: number
  sold_boost?: number
  stock: number
  status: number
  badges: string[]
  badge_items?: BadgeItem[]
  image: string
  category: string
  category_id: number
  category_name: { 'zh-HK': string; en: string }
}

const router = useRouter()
const rows = ref<Row[]>([])
const keyword = ref('')
const categoryId = ref<number | null>(null)
const categoryOptions = ref<Array<{ label: string; value: number }>>([])
const loading = ref(false)

const { page, pageSize, total, paged } = useClientPager(rows, 30)

function flattenCats(nodes: CatNode[], depth = 0): Array<{ label: string; value: number }> {
  const out: Array<{ label: string; value: number }> = []
  for (const n of nodes) {
    out.push({ label: `${'— '.repeat(depth)}${n.name['zh-HK']}`, value: n.id })
    out.push(...flattenCats(n.children || [], depth + 1))
  }
  return out
}

function badgeItems(row: Row): BadgeItem[] {
  if (row.badge_items?.length) return row.badge_items
  return (row.badges || []).map((code) => ({
    code,
    color: '#16162b',
    name: { 'zh-HK': code, en: code },
  }))
}

function categoryLabel(row: Row) {
  return row.category_name?.['zh-HK'] || row.category || '—'
}

async function load() {
  loading.value = true
  try {
    const q = keyword.value.trim()
    const params = new URLSearchParams()
    if (q) params.set('q', q)
    if (categoryId.value != null) params.set('category', String(categoryId.value))
    const qs = params.toString()
    const data = await api<{ items: Row[] }>(`/api/v1/admin/products${qs ? `?${qs}` : ''}`, {}, true)
    rows.value = data.items
  } finally {
    loading.value = false
  }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
function scheduleSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    void load()
  }, 280)
}

watch(keyword, scheduleSearch)
watch(categoryId, () => {
  void load()
})

onMounted(async () => {
  const cats = await api<{ items: CatNode[] }>('/api/v1/admin/categories', {}, true)
  categoryOptions.value = flattenCats(cats.items)
  await load()
})
</script>

<template>
  <n-card title="商品">
    <div class="toolbar">
      <n-space wrap>
        <n-input
          v-model:value="keyword"
          clearable
          placeholder="搜索名稱 / 關鍵字 / ID / 代碼"
          style="width: 16rem"
          @keyup.enter="load"
        />
        <n-select
          v-model:value="categoryId"
          clearable
          placeholder="商品分類"
          :options="categoryOptions"
          style="width: 12rem"
        />
        <n-button :loading="loading" @click="load">搜索</n-button>
        <n-button type="primary" @click="router.push('/catalog/products/new')">新增商品</n-button>
      </n-space>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th class="col-thumb">圖</th>
            <th>代碼</th>
            <th>名稱</th>
            <th>分類</th>
            <th>售價起</th>
            <th>銷售數量</th>
            <th>疊加數量</th>
            <th>庫存</th>
            <th>角標</th>
            <th>狀態</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in paged" :key="row.code" :class="{ off: row.status !== 1 }">
            <td class="col-thumb">
              <img v-if="row.image" class="thumb" :src="assetUrl(row.image)" :alt="row.name['zh-HK']" />
              <span v-else class="thumb placeholder" />
            </td>
            <td>{{ row.code }}</td>
            <td class="col-name">
              <div class="name-zh">{{ row.name['zh-HK'] }}</div>
              <div class="name-en">{{ row.name.en }}</div>
            </td>
            <td>{{ categoryLabel(row) }}</td>
            <td>{{ row.price }}</td>
            <td>{{ row.sold_count ?? 0 }}</td>
            <td>{{ row.sold_boost ?? 0 }}</td>
            <td>{{ row.stock ?? 0 }}</td>
            <td>
              <n-tag
                v-for="b in badgeItems(row)"
                :key="b.code"
                size="small"
                :color="{ color: b.color, textColor: '#fff', borderColor: b.color }"
                style="margin-right: 0.25rem"
              >
                {{ b.name['zh-HK'] }}
              </n-tag>
              <span v-if="!badgeItems(row).length" class="muted">—</span>
            </td>
            <td>{{ row.status === 1 ? '上架' : '下架' }}</td>
            <td>
              <n-button size="tiny" @click="router.push(`/catalog/products/${row.db_id}`)">編輯</n-button>
            </td>
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
</template>

<style scoped>
.toolbar {
  margin-bottom: 1rem;
}
.table-wrap {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.table {
  width: 100%;
  min-width: 1000px;
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
  width: 2.5rem;
}
.thumb {
  width: 2rem;
  height: 2rem;
  border-radius: 0.25rem;
  object-fit: cover;
  display: block;
  background: #f3f0ea;
}
.thumb.placeholder {
  border: 1px dashed #d5cfc4;
  box-sizing: border-box;
}
.col-name {
  max-width: 25%;
  width: 25%;
  overflow: hidden;
}
.col-name .name-zh,
.col-name .name-en {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.col-name .name-zh {
  font-weight: 600;
  color: #1f1f1f;
}
.col-name .name-en {
  margin-top: 0.15rem;
  font-size: 0.75rem;
  color: #8a8378;
}
.muted {
  color: #bbb;
}
tr.off td {
  color: #b0aaa0;
}
tr.off .name-zh,
tr.off .name-en {
  color: #b0aaa0;
}
tr.off .thumb {
  opacity: 0.55;
}
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
</style>
