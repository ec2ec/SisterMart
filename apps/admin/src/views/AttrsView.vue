<script setup lang="ts">
import { onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NFormItem,
  NInput,
  NInputNumber,
  NPagination,
  NSpace,
  NSwitch,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'
import { useTableRowSelect } from '../composables/useTableRowSelect'

type AttrValue = {
  id: number
  code: string
  sort: number
  status: number
  name: { 'zh-HK': string; en: string }
}

type Attr = {
  id: number
  code: string
  sort: number
  status: number
  name: { 'zh-HK': string; en: string }
  values: AttrValue[]
}

const message = useMessage()
const rows = ref<Attr[]>([])
const { page, pageSize, total, paged } = useClientPager(rows, 20)
const { rowAttrs } = useTableRowSelect()
const form = ref({
  id: 0,
  code: '',
  sort: 0,
  status: true,
  name_zh: '',
  name_en: '',
  values: [] as Array<{
    id: number
    code: string
    sort: number
    status: boolean
    name_zh: string
    name_en: string
  }>,
})

async function load() {
  const data = await api<{ items: Attr[] }>('/api/v1/admin/attrs', {}, true)
  rows.value = data.items
}

function emptyValue(sort = 0) {
  return { id: 0, code: '', sort, status: true, name_zh: '', name_en: '' }
}

function resetForm() {
  form.value = {
    id: 0,
    code: '',
    sort: rows.value.length + 1,
    status: true,
    name_zh: '',
    name_en: '',
    values: [emptyValue(1)],
  }
}

function edit(row: Attr) {
  form.value = {
    id: row.id,
    code: row.code,
    sort: row.sort,
    status: row.status === 1,
    name_zh: row.name['zh-HK'],
    name_en: row.name.en,
    values: (row.values || []).map((v) => ({
      id: v.id,
      code: v.code,
      sort: v.sort,
      status: v.status === 1,
      name_zh: v.name['zh-HK'],
      name_en: v.name.en,
    })),
  }
  if (!form.value.values.length) form.value.values.push(emptyValue(1))
}

function addValue() {
  form.value.values.push(emptyValue(form.value.values.length + 1))
}

function removeValue(idx: number) {
  form.value.values.splice(idx, 1)
  if (!form.value.values.length) form.value.values.push(emptyValue(1))
}

async function save() {
  const body = {
    code: form.value.code,
    sort: form.value.sort,
    status: form.value.status ? 1 : 0,
    name: { 'zh-HK': form.value.name_zh, en: form.value.name_en },
    values: form.value.values
      .filter((v) => v.code.trim() && v.name_zh.trim() && v.name_en.trim())
      .map((v) => ({
        id: v.id || undefined,
        code: v.code,
        sort: v.sort,
        status: v.status ? 1 : 0,
        name: { 'zh-HK': v.name_zh, en: v.name_en },
      })),
  }
  if (form.value.id) {
    await api(`/api/v1/admin/attrs/${form.value.id}`, { method: 'PUT', body: JSON.stringify(body) }, true)
  } else {
    await api('/api/v1/admin/attrs', { method: 'POST', body: JSON.stringify(body) }, true)
  }
  message.success('商品參數已保存')
  resetForm()
  await load()
}

onMounted(() => {
  resetForm()
  void load()
})
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="商品參數">
      <p class="hint">參數名稱與取值均支援中英文；商品可多選取值，未選則 H5 不顯示該參數。</p>
      <table class="table">
        <thead>
          <tr>
            <th>代碼</th>
            <th>名稱</th>
            <th>取值</th>
            <th>狀態</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in paged" :key="row.id" v-bind="rowAttrs(row.id)">
            <td>{{ row.code }}</td>
            <td>
              <div>{{ row.name['zh-HK'] }}</div>
              <div class="en">{{ row.name.en }}</div>
            </td>
            <td>
              <n-tag
                v-for="v in row.values"
                :key="v.id"
                size="small"
                :type="v.status === 1 ? 'default' : 'warning'"
                style="margin: 0 0.25rem 0.25rem 0"
              >
                {{ v.name['zh-HK'] }}
              </n-tag>
            </td>
            <td>{{ row.status === 1 ? '啟用' : '停用' }}</td>
            <td><n-button size="tiny" @click="edit(row)">編輯</n-button></td>
          </tr>
        </tbody>
      </table>
      <div class="pager">
        <n-pagination
          v-model:page="page"
          v-model:page-size="pageSize"
          :item-count="total"
          :page-sizes="[20, 50, 100]"
          show-size-picker
        />
      </div>
    </n-card>

    <n-card :title="form.id ? `編輯參數 #${form.id}` : '新增商品參數'">
      <div class="form-grid">
        <n-form-item label="代碼"><n-input v-model:value="form.code" placeholder="如 material" /></n-form-item>
        <n-form-item label="繁中名稱"><n-input v-model:value="form.name_zh" placeholder="材質" /></n-form-item>
        <n-form-item label="英文名稱"><n-input v-model:value="form.name_en" placeholder="Material" /></n-form-item>
        <n-form-item label="排序"><n-input-number v-model:value="form.sort" :min="0" /></n-form-item>
        <n-form-item label="啟用"><n-switch v-model:value="form.status" /></n-form-item>
      </div>

      <h4 class="sub">參數取值</h4>
      <div v-for="(v, idx) in form.values" :key="idx" class="value-row">
        <n-input v-model:value="v.code" placeholder="代碼" style="width: 8rem" />
        <n-input v-model:value="v.name_zh" placeholder="繁中" style="width: 8rem" />
        <n-input v-model:value="v.name_en" placeholder="英文" style="width: 8rem" />
        <n-input-number v-model:value="v.sort" :min="0" style="width: 6rem" />
        <n-switch v-model:value="v.status" />
        <n-button size="tiny" @click="removeValue(idx)">刪除</n-button>
      </div>
      <n-space style="margin-top: 0.75rem">
        <n-button @click="addValue">加取值</n-button>
        <n-button type="primary" @click="save">保存</n-button>
        <n-button v-if="form.id" @click="resetForm">取消編輯</n-button>
      </n-space>
    </n-card>
  </n-space>
</template>

<style scoped>
.hint {
  color: #6d675f;
  margin: 0 0 1rem;
  font-size: 0.875rem;
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
.en {
  margin-top: 0.15rem;
  font-size: 0.75rem;
  color: #8a8378;
}
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem 1rem;
  max-width: 48rem;
}
.sub {
  margin: 0.5rem 0 0.75rem;
  font-size: 0.9375rem;
}
.value-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 0.5rem;
}
@media (max-width: 900px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
