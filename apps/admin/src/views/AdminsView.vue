<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import {
  NButton,
  NCard,
  NForm,
  NFormItem,
  NInput,
  NSelect,
  NSpace,
  NTag,
  useMessage,
  NPagination,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'

type Row = {
  id: number
  email: string
  name: string
  role: string
  role_name: string
  status: number
  created_at: string
}

const message = useMessage()
const rows = ref<Row[]>([])
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const roleOptions = ref<{ label: string; value: string }[]>([])
const form = reactive({
  email: '',
  name: '',
  password: '',
  role: 'ops',
})

async function load() {
  const data = await api<{ items: Row[]; roles: Array<{ code: string; name_zh: string }> }>(
    '/api/v1/admin/admins',
    {},
    true,
  )
  rows.value = data.items
  roleOptions.value = data.roles.map((r) => ({ label: r.name_zh, value: r.code }))
}

onMounted(async () => {
  try {
    await load()
  } catch (e) {
    message.error(e instanceof Error ? e.message : '無權限')
  }
})

async function create() {
  await api('/api/v1/admin/admins', { method: 'POST', body: JSON.stringify(form) }, true)
  message.success('已創建')
  form.email = ''
  form.name = ''
  form.password = ''
  await load()
}

async function toggle(row: Row) {
  await api(`/api/v1/admin/admins/${row.id}`, { method: 'PUT', body: JSON.stringify({ status: row.status === 1 ? 0 : 1 }) }, true)
  message.success('已更新狀態')
  await load()
}
</script>

<template>
  <n-card title="管理員與角色">
    <p style="color: #666; margin-bottom: 1rem">僅超級管理員可管理。運營帳號不可進入本頁與本 API。</p>
    <n-form label-placement="left" label-width="5rem" style="max-width: 28rem; margin-bottom: 1.5rem">
      <n-form-item label="郵箱"><n-input v-model:value="form.email" /></n-form-item>
      <n-form-item label="姓名"><n-input v-model:value="form.name" /></n-form-item>
      <n-form-item label="密碼"><n-input v-model:value="form.password" type="password" show-password-on="click" /></n-form-item>
      <n-form-item label="角色"><n-select v-model:value="form.role" :options="roleOptions" /></n-form-item>
      <n-button type="primary" @click="create">新建</n-button>
    </n-form>
    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>郵箱</th>
          <th>姓名</th>
          <th>角色</th>
          <th>狀態</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in paged" :key="row.id">
          <td>{{ row.id }}</td>
          <td>{{ row.email }}</td>
          <td>{{ row.name }}</td>
          <td>{{ row.role_name }} ({{ row.role }})</td>
          <td><n-tag size="small" :type="row.status === 1 ? 'success' : 'default'">{{ row.status === 1 ? '啟用' : '停用' }}</n-tag></td>
          <td>
            <n-space>
              <n-button size="tiny" @click="toggle(row)">{{ row.status === 1 ? '停用' : '啟用' }}</n-button>
            </n-space>
          </td>
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
