<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { NButton, NCard, NInputNumber, NSpace, useMessage } from 'naive-ui'
import { api } from '../api'

type Level = {
  id: number
  stars: number
  name: { 'zh-HK': string; en: string }
  min_spent: number
  max_spent: number | null
}

const message = useMessage()
const rows = ref<Level[]>([])

async function load() {
  const data = await api<{ items: Level[] }>('/api/v1/admin/member-levels', {}, true)
  rows.value = data.items
}

onMounted(load)

async function save(row: Level) {
  await api(
    `/api/v1/admin/member-levels/${row.id}`,
    {
      method: 'PUT',
      body: JSON.stringify({ min_spent: row.min_spent, max_spent: row.max_spent }),
    },
    true,
  )
  message.success('已更新並同步會員等級')
  await load()
}
</script>

<template>
  <n-card title="會員等級閾值">
    <p style="color: #666; margin-bottom: 1rem">按累計實付（不含運費）分檔；改閾值後會重算全體會員等級。</p>
    <table class="table">
      <thead>
        <tr>
          <th>星級</th>
          <th>名稱</th>
          <th>最低累計</th>
          <th>最高累計</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ '★'.repeat(row.stars) }}</td>
          <td>{{ row.name['zh-HK'] }}</td>
          <td><n-input-number v-model:value="row.min_spent" :min="0" :step="1" /></td>
          <td>
            <n-input-number v-model:value="row.max_spent" :min="0" :step="1" placeholder="無上限" clearable />
          </td>
          <td><n-button size="tiny" type="primary" @click="save(row)">保存</n-button></td>
        </tr>
      </tbody>
    </table>
  </n-card>
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
  vertical-align: middle;
}
</style>
