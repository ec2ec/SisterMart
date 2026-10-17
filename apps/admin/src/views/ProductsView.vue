<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NCard, NSpace, NTag } from 'naive-ui'
import { api } from '../api'

type Row = {
  db_id: number
  code: string
  name: { 'zh-HK': string; en: string }
  price: number
  sold: string
  status: number
  badges: string[]
}

const router = useRouter()
const rows = ref<Row[]>([])

onMounted(async () => {
  const data = await api<{ items: Row[] }>('/api/v1/admin/products', {}, true)
  rows.value = data.items
})
</script>

<template>
  <n-card title="商品">
    <n-space style="margin-bottom: 1rem">
      <n-button type="primary" @click="router.push('/catalog/products/new')">新增商品</n-button>
    </n-space>
    <table class="table">
      <thead>
        <tr>
          <th>代碼</th>
          <th>名稱</th>
          <th>售價起</th>
          <th>已售</th>
          <th>角標</th>
          <th>狀態</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.code">
          <td>{{ row.code }}</td>
          <td>{{ row.name['zh-HK'] }}</td>
          <td>{{ row.price }}</td>
          <td>{{ row.sold }}</td>
          <td>
            <n-tag v-for="b in row.badges" :key="b" size="small" style="margin-right: 0.25rem">{{ b }}</n-tag>
          </td>
          <td>{{ row.status === 1 ? '上架' : '下架' }}</td>
          <td>
            <n-button size="tiny" @click="router.push(`/catalog/products/${row.db_id}`)">編輯</n-button>
          </td>
        </tr>
      </tbody>
    </table>
  </n-card>
</template>

<style scoped>
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
}
</style>
