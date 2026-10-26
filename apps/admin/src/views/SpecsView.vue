<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NCard, NInput, NPagination, NSpace } from 'naive-ui'
import { api } from '../api'
import { assetUrl } from '../utils/assetUrl'
import { useClientPager } from '../composables/useClientPager'
import { useTableRowSelect } from '../composables/useTableRowSelect'

type Row = {
  db_id: number
  code: string
  name: { 'zh-HK': string; en: string }
  price: number
  stock: number
  status: number
  image: string
}

const router = useRouter()
const rows = ref<Row[]>([])
const keyword = ref('')
const loading = ref(false)
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const { rowAttrs } = useTableRowSelect()

async function load() {
  loading.value = true
  try {
    const q = keyword.value.trim()
    const qs = q ? `?q=${encodeURIComponent(q)}` : ''
    const data = await api<{ items: Row[] }>(`/api/v1/admin/products${qs}`, {}, true)
    rows.value = data.items
  } finally {
    loading.value = false
  }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(keyword, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => void load(), 280)
})

onMounted(load)
</script>

<template>
  <n-card title="規格管理">
    <p class="hint">選擇商品進入商品編輯頁配置規格／SKU（可套用規格模板）。全局模板請到「規格模板」維護。</p>
    <div class="toolbar">
      <n-space>
        <n-input
          v-model:value="keyword"
          clearable
          placeholder="搜索名稱 / 代碼 / ID"
          style="width: 16rem"
          @keyup.enter="load"
        />
        <n-button :loading="loading" @click="load">搜索</n-button>
      </n-space>
    </div>
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
        <tr v-for="row in paged" :key="row.db_id" v-bind="rowAttrs(row.db_id)" :class="{ off: row.status !== 1 }">
          <td class="col-thumb">
            <img v-if="row.image" class="thumb" :src="assetUrl(row.image)" :alt="row.name['zh-HK']" />
            <span v-else class="thumb placeholder" />
          </td>
          <td>{{ row.code }}</td>
          <td>
            <div class="name-zh">{{ row.name['zh-HK'] }}</div>
            <div class="name-en">{{ row.name.en }}</div>
          </td>
          <td>{{ row.price }}</td>
          <td>{{ row.stock ?? 0 }}</td>
          <td>{{ row.status === 1 ? '上架' : '下架' }}</td>
          <td>
            <n-button size="tiny" type="primary" @click="router.push(`/catalog/products/${row.db_id}`)">
              編輯規格
            </n-button>
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
.hint {
  color: #6d675f;
  margin: 0 0 1rem;
  font-size: 0.875rem;
}
.toolbar {
  margin-bottom: 1rem;
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
.name-zh {
  font-weight: 600;
}
.name-en {
  margin-top: 0.15rem;
  font-size: 0.75rem;
  color: #8a8378;
}
tr.off td {
  color: #b0aaa0;
}
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
</style>
