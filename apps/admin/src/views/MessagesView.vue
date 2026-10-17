<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { NButton, NCard, NForm, NFormItem, NInput, NInputNumber, useMessage } from 'naive-ui'
import { api } from '../api'

type Batch = {
  batch_id: string
  title: { 'zh-HK': string; en: string }
  body: { 'zh-HK': string; en: string }
  recipient_count: number
  created_at: string
}

const message = useMessage()
const rows = ref<Batch[]>([])
const form = reactive({
  title_zh: '',
  title_en: '',
  body_zh: '',
  body_en: '',
  member_id: null as number | null,
})

async function load() {
  const data = await api<{ items: Batch[] }>('/api/v1/admin/messages', {}, true)
  rows.value = data.items
}

onMounted(load)

async function create() {
  await api(
    '/api/v1/admin/messages',
    {
      method: 'POST',
      body: JSON.stringify({
        title_zh: form.title_zh,
        title_en: form.title_en || form.title_zh,
        body_zh: form.body_zh,
        body_en: form.body_en || form.body_zh,
        member_id: form.member_id || undefined,
      }),
    },
    true,
  )
  message.success('已發送')
  form.title_zh = ''
  form.title_en = ''
  form.body_zh = ''
  form.body_en = ''
  form.member_id = null
  await load()
}
</script>

<template>
  <n-card title="系統消息">
    <n-form label-placement="left" label-width="7rem" style="max-width: 40rem; margin-bottom: 1.5rem">
      <n-form-item label="中文標題"><n-input v-model:value="form.title_zh" /></n-form-item>
      <n-form-item label="英文標題"><n-input v-model:value="form.title_en" placeholder="可留空同中文" /></n-form-item>
      <n-form-item label="中文內容"><n-input v-model:value="form.body_zh" type="textarea" :rows="3" /></n-form-item>
      <n-form-item label="英文內容"><n-input v-model:value="form.body_en" type="textarea" :rows="3" placeholder="可留空同中文" /></n-form-item>
      <n-form-item label="指定會員">
        <n-input-number v-model:value="form.member_id" :min="1" clearable placeholder="空白=全體廣播" style="width: 100%" />
      </n-form-item>
      <n-button type="primary" @click="create">發送系統消息</n-button>
    </n-form>

    <table class="table">
      <thead>
        <tr>
          <th>時間</th>
          <th>標題</th>
          <th>內容</th>
          <th>人數</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.batch_id">
          <td>{{ row.created_at }}</td>
          <td>{{ row.title['zh-HK'] }}</td>
          <td>{{ row.body['zh-HK'] }}</td>
          <td>{{ row.recipient_count }}</td>
        </tr>
      </tbody>
    </table>
  </n-card>
</template>

<style scoped>
.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}
.table th,
.table td {
  border-bottom: 1px solid #eee;
  padding: 0.5rem 0.4rem;
  text-align: left;
  vertical-align: top;
}
</style>
