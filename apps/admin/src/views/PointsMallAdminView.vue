<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NCard, NSpace, useMessage, NPagination } from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'

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

const router = useRouter()
const message = useMessage()
const items = ref<Item[]>([])
const { page, pageSize, total, paged } = useClientPager(items, 30)

async function load() {
  const data = await api<{ items: Item[] }>('/api/v1/admin/points-mall/items', {}, true)
  items.value = data.items
}

function goNew() {
  router.push('/marketing/points-mall/new')
}

function goEdit(row: Item) {
  router.push(`/marketing/points-mall/${row.points_mall_item_id}`)
}

async function remove(id: number) {
  await api(`/api/v1/admin/points-mall/items/${id}`, { method: 'DELETE' }, true)
  message.success('已刪除')
  await load()
}

onMounted(load)
</script>

<template>
  <n-card title="積分商城商品">
    <template #header-extra>
      <n-button type="primary" size="small" @click="goNew">新增積分商品</n-button>
    </template>

    <div v-for="row in paged" :key="row.points_mall_item_id" class="row">
      <div>
        <strong>{{ row.name['zh-HK'] }}</strong>
        <p>
          {{ row.id }} · {{ row.product_id }} / sku {{ row.sku_id }} · {{ row.points_cost }} 積分 · 庫存
          {{ row.stock }} · 已兌 {{ row.sold }} · {{ row.status === 1 ? '上架' : '下架' }}
        </p>
      </div>
      <n-space>
        <n-button size="tiny" @click="goEdit(row)">編輯</n-button>
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
</style>
