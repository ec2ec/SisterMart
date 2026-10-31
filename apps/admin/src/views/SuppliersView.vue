<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NFormItem,
  NInput,
  NPagination,
  NSelect,
  NSpace,
  NSwitch,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'
import { useTableRowSelect } from '../composables/useTableRowSelect'

type Supplier = {
  id: number
  code: string
  name: string
  type: string
  type_label: string
  status: number
  remark: string | null
  contact_person: string | null
  phone: string | null
  whatsapp: string | null
  email: string | null
  address: string | null
}

const message = useMessage()
const rows = ref<Supplier[]>([])
const typeLabels = ref<Record<string, string>>({})
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const { rowAttrs } = useTableRowSelect()

const form = ref({
  id: 0,
  code: '',
  name: '',
  type: 'factory',
  status: true,
  remark: '',
  contact_person: '',
  phone: '',
  whatsapp: '',
  email: '',
  address: '',
})

const typeOptions = computed(() =>
  Object.entries(typeLabels.value).map(([value, label]) => ({ label, value })),
)

async function load() {
  const data = await api<{ items: Supplier[]; types: Record<string, string> }>(
    '/api/v1/admin/suppliers',
    {},
    true,
  )
  rows.value = data.items
  typeLabels.value = data.types || {}
}

function resetForm() {
  form.value = {
    id: 0,
    code: '',
    name: '',
    type: 'factory',
    status: true,
    remark: '',
    contact_person: '',
    phone: '',
    whatsapp: '',
    email: '',
    address: '',
  }
}

function edit(row: Supplier) {
  form.value = {
    id: row.id,
    code: row.code,
    name: row.name,
    type: row.type,
    status: row.status === 1,
    remark: row.remark || '',
    contact_person: row.contact_person || '',
    phone: row.phone || '',
    whatsapp: row.whatsapp || '',
    email: row.email || '',
    address: row.address || '',
  }
}

async function save() {
  const body = {
    code: form.value.code.trim().toUpperCase(),
    name: form.value.name.trim(),
    type: form.value.type,
    status: form.value.status ? 1 : 0,
    remark: form.value.remark.trim() || null,
    contact_person: form.value.contact_person.trim() || null,
    phone: form.value.phone.trim() || null,
    whatsapp: form.value.whatsapp.trim() || null,
    email: form.value.email.trim() || null,
    address: form.value.address.trim() || null,
  }
  if (form.value.id) {
    await api(`/api/v1/admin/suppliers/${form.value.id}`, { method: 'PUT', body: JSON.stringify(body) }, true)
  } else {
    await api('/api/v1/admin/suppliers', { method: 'POST', body: JSON.stringify(body) }, true)
  }
  message.success('供應商已保存')
  resetForm()
  await load()
}

onMounted(load)
</script>

<template>
  <div class="split">
    <n-card title="供應商列表" class="pane">
      <template #header-extra>
        <n-button type="primary" size="small" @click="resetForm">新增供應商</n-button>
      </template>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>簡碼</th>
              <th>名稱</th>
              <th>類型</th>
              <th>狀態</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in paged"
              :key="row.id"
              v-bind="rowAttrs(row.id)"
              :class="{ off: row.status !== 1 }"
            >
              <td>{{ row.code }}</td>
              <td>{{ row.name }}</td>
              <td>{{ row.type_label || row.type }}</td>
              <td>
                <n-tag :type="row.status === 1 ? 'success' : 'default'" size="small">
                  {{ row.status === 1 ? '啟用' : '停用' }}
                </n-tag>
              </td>
              <td>
                <n-button size="tiny" @click.stop="edit(row)">編輯</n-button>
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
          :page-sizes="[30, 50]"
          show-size-picker
        />
      </div>
    </n-card>

    <n-card :title="form.id ? `編輯 #${form.id}` : '新增供應商'" class="pane">
      <n-form-item label="簡碼">
        <n-input v-model:value="form.code" placeholder="如 S001" :disabled="form.id > 0" />
      </n-form-item>
      <n-form-item label="全名"><n-input v-model:value="form.name" /></n-form-item>
      <n-form-item label="類型">
        <n-select v-model:value="form.type" :options="typeOptions" />
      </n-form-item>
      <n-form-item label="啟用">
        <n-switch v-model:value="form.status" />
        <span class="hint">停用後不可新綁商品；已綁商品保留</span>
      </n-form-item>
      <n-form-item label="聯絡人"><n-input v-model:value="form.contact_person" /></n-form-item>
      <n-form-item label="電話"><n-input v-model:value="form.phone" /></n-form-item>
      <n-form-item label="WhatsApp"><n-input v-model:value="form.whatsapp" /></n-form-item>
      <n-form-item label="郵箱"><n-input v-model:value="form.email" /></n-form-item>
      <n-form-item label="地址"><n-input v-model:value="form.address" type="textarea" :rows="2" /></n-form-item>
      <n-form-item label="備註"><n-input v-model:value="form.remark" type="textarea" :rows="2" /></n-form-item>
      <n-space>
        <n-button type="primary" @click="save">保存</n-button>
        <n-button @click="resetForm">清空</n-button>
      </n-space>
    </n-card>
  </div>
</template>

<style scoped>
.split {
  display: grid;
  grid-template-columns: 1.1fr 0.9fr;
  gap: 1rem;
  align-items: start;
}
.pane {
  min-width: 0;
}
.table-wrap {
  width: 100%;
  overflow-x: auto;
}
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
tr.off td {
  color: #b0aaa0;
}
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
.hint {
  margin-left: 0.75rem;
  font-size: 0.75rem;
  color: #8a8378;
}
@media (max-width: 960px) {
  .split {
    grid-template-columns: 1fr;
  }
}
</style>
