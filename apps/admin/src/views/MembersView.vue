<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import {
  NButton,
  NCard,
  NForm,
  NFormItem,
  NInput,
  NInputNumber,
  NModal,
  NPagination,
  NSpace,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'

type Member = {
  id: number
  email: string | null
  phone: string | null
  promo_code: string
  balance: number
  points: number
  total_spent: number
  level: { stars: number; name: { 'zh-HK': string } }
  created_at?: string
}

const message = useMessage()
const q = ref('')
const rows = ref<Member[]>([])
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const show = ref(false)
const current = ref<Member | null>(null)
const adjust = reactive({ points_delta: 0, balance_delta: 0, reason: '' })

async function load() {
  const qs = q.value ? `?q=${encodeURIComponent(q.value)}` : ''
  const data = await api<{ items: Member[] }>(`/api/v1/admin/members${qs}`, {}, true)
  rows.value = data.items
}

onMounted(load)

function openAdjust(row: Member) {
  current.value = row
  adjust.points_delta = 0
  adjust.balance_delta = 0
  adjust.reason = ''
  show.value = true
}

async function submitAdjust() {
  if (!current.value) return
  await api(
    `/api/v1/admin/members/${current.value.id}/asset-adjustments`,
    { method: 'POST', body: JSON.stringify(adjust) },
    true,
  )
  message.success('已調整')
  show.value = false
  await load()
}
</script>

<template>
  <n-card title="會員">
    <n-space style="margin-bottom: 1rem">
      <n-input v-model:value="q" placeholder="郵箱 / 手機 / 推廣碼" style="width: 16rem" @keyup.enter="load" />
      <n-button @click="load">搜索</n-button>
    </n-space>
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>帳號</th>
          <th>等級</th>
          <th>累計實付</th>
          <th>餘額</th>
          <th>積分</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in paged" :key="row.id">
          <td>{{ row.id }}</td>
          <td>{{ row.email || row.phone }}<br /><small>{{ row.promo_code }}</small></td>
          <td>
            <n-tag size="small">{{ '★'.repeat(row.level?.stars || 1) }} {{ row.level?.name?.['zh-HK'] }}</n-tag>
          </td>
          <td>{{ Number(row.total_spent || 0).toFixed(2) }}</td>
          <td>{{ Number(row.balance).toFixed(2) }}</td>
          <td>{{ row.points }}</td>
          <td><n-button size="tiny" @click="openAdjust(row)">調整資產</n-button></td>
        </tr>
      </tbody>
    </table>
    <div class="pager">
      <n-pagination
        v-model:page="page"
        v-model:page-size="pageSize"
        :item-count="total"
        :page-sizes="[30, 50, 100]"
        show-size-picker
      />
    </div>

    <n-modal v-model:show="show" preset="card" title="調整會員資產" style="width: 28rem">
      <p v-if="current">會員 #{{ current.id }} · {{ current.email || current.phone }}</p>
      <n-form label-placement="left" label-width="6rem">
        <n-form-item label="積分變動"><n-input-number v-model:value="adjust.points_delta" /></n-form-item>
        <n-form-item label="餘額變動"><n-input-number v-model:value="adjust.balance_delta" :step="0.01" /></n-form-item>
        <n-form-item label="原因"><n-input v-model:value="adjust.reason" type="textarea" placeholder="必填" /></n-form-item>
      </n-form>
      <n-button type="primary" @click="submitAdjust">確認調整</n-button>
    </n-modal>
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
}
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
</style>
