<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import {
  NButton,
  NCard,
  NDatePicker,
  NForm,
  NFormItem,
  NInput,
  NInputNumber,
  NModal,
  NPagination,
  NRadio,
  NRadioGroup,
  NSelect,
  NSpace,
  NTag,
  useMessage,
} from 'naive-ui'
import { api, uploadImage } from '../api'
import { showWarningDialog } from '../feedback'
import WangSimpleEditor from '../components/WangSimpleEditor.vue'
import { useClientPager } from '../composables/useClientPager'
import { useTableRowSelect } from '../composables/useTableRowSelect'

type Coupon = {
  id: number
  code: string
  name: { 'zh-HK': string; en: string }
  name_zh?: string
  name_en?: string
  rules?: { 'zh-HK': string; en: string }
  rules_zh?: string
  rules_en?: string
  background_image?: string | null
  type: string
  threshold: number
  amount: number
  status: number
  stock: number
  claimed: number
  per_member_limit: number
  starts_at: string | null
  ends_at: string | null
  claim_permanent?: boolean
  valid_days: number
}

const message = useMessage()
const rows = ref<Coupon[]>([])
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const { rowAttrs } = useTableRowSelect()

const types = [
  { label: '固定減免', value: 'fixed' },
  { label: '折扣（如 0.9=九折）', value: 'discount' },
  { label: '免運', value: 'free_shipping' },
]

function formatYmd(d: Date) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function defaultClaimRange() {
  const start = new Date()
  const end = new Date()
  end.setMonth(end.getMonth() + 1)
  return { starts_at: formatYmd(start), ends_at: formatYmd(end) }
}

const createOpen = ref(false)
const createSaving = ref(false)
const form = reactive({
  code: '',
  name_zh: '',
  name_en: '',
  rules_zh: '',
  rules_en: '',
  background_image: '' as string,
  type: 'fixed',
  threshold: 0,
  amount: 20,
  stock: 100,
  per_member_limit: 1,
  claim_mode: 'range' as 'range' | 'permanent',
  starts_at: defaultClaimRange().starts_at as string | null,
  ends_at: defaultClaimRange().ends_at as string | null,
  valid_days: 30,
})

const editOpen = ref(false)
const editSaving = ref(false)
const bgUploading = ref(false)
const edit = reactive({
  id: 0,
  code: '',
  name_zh: '',
  name_en: '',
  rules_zh: '',
  rules_en: '',
  background_image: '' as string,
  claim_mode: 'range' as 'range' | 'permanent',
  starts_at: null as string | null,
  ends_at: null as string | null,
  valid_days: 30,
})

function claimWindowLabel(row: Coupon) {
  if (row.claim_permanent || !row.ends_at) {
    const start = row.starts_at ? String(row.starts_at).slice(0, 10) + ' 起' : ''
    return start ? `永久（${start}）` : '永久'
  }
  const a = row.starts_at ? String(row.starts_at).slice(0, 10) : '—'
  const b = String(row.ends_at).slice(0, 10)
  return `${a} ~ ${b}`
}

function validLabel(days: number) {
  return days > 0 ? `${days}天` : '永久'
}

async function load() {
  const data = await api<{ items: Coupon[] }>('/api/v1/admin/coupons', {}, true)
  rows.value = data.items
}

onMounted(load)

function asDateOnly(v: unknown): string | null {
  if (v == null || v === '') return null
  if (typeof v === 'number' && Number.isFinite(v)) {
    const d = new Date(v > 1e12 ? v : v * 1000)
    if (Number.isNaN(d.getTime())) return null
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
  }
  if (typeof v !== 'string') return null
  const m = v.trim().match(/^(\d{4}-\d{2}-\d{2})/)
  return m ? m[1] : null
}

function payloadClaimWindow(mode: 'range' | 'permanent', starts: unknown, ends: unknown) {
  const s = asDateOnly(starts)
  const e = asDateOnly(ends)
  if (mode === 'permanent') {
    return { claim_permanent: true, starts_at: s ? `${s} 00:00:00` : null, ends_at: null }
  }
  return {
    claim_permanent: false,
    starts_at: s ? `${s} 00:00:00` : null,
    ends_at: e ? `${e} 23:59:59` : null,
  }
}

function resetCreateForm() {
  const range = defaultClaimRange()
  form.code = ''
  form.name_zh = ''
  form.name_en = ''
  form.rules_zh = ''
  form.rules_en = ''
  form.background_image = ''
  form.type = 'fixed'
  form.threshold = 0
  form.amount = 20
  form.stock = 100
  form.per_member_limit = 1
  form.claim_mode = 'range'
  form.starts_at = range.starts_at
  form.ends_at = range.ends_at
  form.valid_days = 30
}

function openCreate() {
  resetCreateForm()
  createOpen.value = true
}

async function create() {
  if (!form.code.trim()) {
    message.warning('請填寫券碼')
    return
  }
  if (form.claim_mode === 'range' && (!form.starts_at || !form.ends_at)) {
    message.warning('請選擇發放時段起止日期')
    return
  }
  createSaving.value = true
  try {
    await api(
      '/api/v1/admin/coupons',
      {
        method: 'POST',
        body: JSON.stringify({
          code: form.code,
          name_zh: form.name_zh,
          name_en: form.name_en,
          rules_zh: form.rules_zh,
          rules_en: form.rules_en,
          background_image: form.background_image.trim() || null,
          type: form.type,
          threshold: form.threshold,
          amount: form.amount,
          stock: form.stock,
          per_member_limit: form.per_member_limit,
          valid_days: form.valid_days,
          ...payloadClaimWindow(form.claim_mode, form.starts_at, form.ends_at),
        }),
      },
      true,
    )
    message.success('已創建')
    createOpen.value = false
    resetCreateForm()
    await load()
  } finally {
    createSaving.value = false
  }
}

async function toggle(row: Coupon) {
  await api(
    `/api/v1/admin/coupons/${row.id}`,
    { method: 'PUT', body: JSON.stringify({ status: row.status === 1 ? 0 : 1 }) },
    true,
  )
  message.success('已更新狀態')
  await load()
}

function openEdit(row: Coupon) {
  edit.id = row.id
  edit.code = row.code
  edit.name_zh = row.name_zh || row.name?.['zh-HK'] || ''
  edit.name_en = row.name_en || row.name?.en || ''
  edit.rules_zh = row.rules_zh || row.rules?.['zh-HK'] || ''
  edit.rules_en = row.rules_en || row.rules?.en || ''
  edit.background_image = row.background_image || ''
  edit.claim_mode = row.claim_permanent || !row.ends_at ? 'permanent' : 'range'
  edit.starts_at = row.starts_at ? String(row.starts_at).slice(0, 10) : null
  edit.ends_at = row.ends_at ? String(row.ends_at).slice(0, 10) : null
  edit.valid_days = Number(row.valid_days ?? 30)
  editOpen.value = true
}

async function saveEdit() {
  if (edit.claim_mode === 'range' && (!edit.starts_at || !edit.ends_at)) {
    message.warning('請選擇發放時段起止日期')
    return
  }
  editSaving.value = true
  try {
    await api(
      `/api/v1/admin/coupons/${edit.id}`,
      {
        method: 'PUT',
        body: JSON.stringify({
          name_zh: edit.name_zh,
          name_en: edit.name_en,
          rules_zh: edit.rules_zh,
          rules_en: edit.rules_en,
          background_image: edit.background_image.trim() || null,
          valid_days: edit.valid_days,
          ...payloadClaimWindow(edit.claim_mode, edit.starts_at, edit.ends_at),
        }),
      },
      true,
    )
    message.success('已保存')
    editOpen.value = false
    await load()
  } finally {
    editSaving.value = false
  }
}

async function onBgFile(target: 'create' | 'edit', ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  bgUploading.value = true
  try {
    const data = await uploadImage(file, { preset: 'banner' })
    if (target === 'create') form.background_image = data.url
    else edit.background_image = data.url
    message.success('背景圖已上傳')
  } finally {
    bgUploading.value = false
    input.value = ''
  }
}

function clearBg(target: 'create' | 'edit') {
  if (target === 'create') form.background_image = ''
  else edit.background_image = ''
}

/** 臨時刪除：正式上線後移除此功能 */
function removeCoupon(row: Coupon) {
  showWarningDialog({
    title: '臨時刪除優惠券',
    content: `確定刪除「${row.code} / ${row.name['zh-HK']}」？將同時刪除已領取記錄（${row.claimed}）。此為上線前臨時功能。`,
    positiveText: '刪除',
    negativeText: '取消',
    onPositiveClick: async () => {
      const data = await api<{ deleted: boolean; grants_deleted: number }>(
        `/api/v1/admin/coupons/${row.id}`,
        { method: 'DELETE' },
        true,
      )
      message.success(`已刪除（含領取記錄 ${data.grants_deleted} 條）`)
      await load()
    },
  })
}
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="優惠券列表">
      <template #header-extra>
        <n-button type="primary" @click="openCreate">新增優惠券</n-button>
      </template>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>券碼</th>
              <th>名稱</th>
              <th>類型</th>
              <th>門檻/額度</th>
              <th>發放時段</th>
              <th>使用有效期</th>
              <th>領取</th>
              <th>細則</th>
              <th>背景</th>
              <th>狀態</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in paged" :key="row.id" v-bind="rowAttrs(row.id)">
              <td>{{ row.code }}</td>
              <td>{{ row.name['zh-HK'] }}</td>
              <td>{{ row.type }}</td>
              <td>≥{{ row.threshold }} / {{ row.amount }}</td>
              <td>{{ claimWindowLabel(row) }}</td>
              <td>{{ validLabel(row.valid_days) }}</td>
              <td>{{ row.claimed }}/{{ row.stock }}（限{{ row.per_member_limit }}）</td>
              <td>{{ (row.rules_zh || row.rules?.['zh-HK'] || '').trim() ? '已填' : '—' }}</td>
              <td>
                <img v-if="row.background_image" class="bg-thumb" :src="row.background_image" alt="" />
                <span v-else>—</span>
              </td>
              <td>
                <n-tag size="small" :type="row.status === 1 ? 'success' : 'default'">
                  {{ row.status === 1 ? '啟用' : '停用' }}
                </n-tag>
              </td>
              <td>
                <n-space>
                  <n-button size="tiny" @click="openEdit(row)">編輯</n-button>
                  <n-button size="tiny" @click="toggle(row)">{{ row.status === 1 ? '停用' : '啟用' }}</n-button>
                  <n-button size="tiny" type="error" quaternary @click="removeCoupon(row)">刪除</n-button>
                </n-space>
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
      <p class="temp-hint">※ 刪除為上線前臨時功能，正式上線後將移除。</p>
    </n-card>

    <n-modal
      v-model:show="createOpen"
      preset="card"
      title="新增優惠券"
      style="width: min(44rem, 94vw)"
      :mask-closable="false"
      :close-on-esc="false"
    >
      <n-form label-placement="top">
        <n-form-item label="券碼"><n-input v-model:value="form.code" placeholder="如 SAVE15" /></n-form-item>
        <n-form-item label="中文名"><n-input v-model:value="form.name_zh" /></n-form-item>
        <n-form-item label="英文名"><n-input v-model:value="form.name_en" /></n-form-item>
        <n-form-item label="類型"><n-select v-model:value="form.type" :options="types" /></n-form-item>
        <n-form-item label="門檻"><n-input-number v-model:value="form.threshold" :min="0" /></n-form-item>
        <n-form-item label="面額/折扣"><n-input-number v-model:value="form.amount" :min="0" :step="0.1" /></n-form-item>
        <n-form-item label="庫存"><n-input-number v-model:value="form.stock" :min="0" /></n-form-item>
        <n-form-item label="每人限領"><n-input-number v-model:value="form.per_member_limit" :min="1" /></n-form-item>
        <n-form-item label="發放時段">
          <n-space vertical :size="8" style="width: 100%">
            <n-radio-group v-model:value="form.claim_mode">
              <n-radio value="range">指定時段</n-radio>
              <n-radio value="permanent">永久</n-radio>
            </n-radio-group>
            <n-space v-if="form.claim_mode === 'range'" wrap>
              <n-date-picker
                v-model:formatted-value="form.starts_at"
                type="date"
                value-format="yyyy-MM-dd"
                clearable
                placeholder="開始"
                style="width: 10rem"
              />
              <span>至</span>
              <n-date-picker
                v-model:formatted-value="form.ends_at"
                type="date"
                value-format="yyyy-MM-dd"
                clearable
                placeholder="結束"
                style="width: 10rem"
              />
            </n-space>
            <n-date-picker
              v-else
              v-model:formatted-value="form.starts_at"
              type="date"
              value-format="yyyy-MM-dd"
              clearable
              placeholder="可選：開始發放日"
              style="width: 12rem"
            />
          </n-space>
        </n-form-item>
        <n-form-item label="使用有效期">
          <n-space align="center">
            <span>領取後</span>
            <n-input-number v-model:value="form.valid_days" :min="0" style="width: 7rem" />
            <span>天有效（0 = 永久）</span>
          </n-space>
        </n-form-item>
        <n-form-item label="背景圖（可選，H5 券卡 100% 寬）">
          <n-space vertical :size="8" style="width: 100%">
            <n-input v-model:value="form.background_image" placeholder="/storage/... 或留空用漸變" />
            <n-space align="center">
              <input type="file" accept="image/jpeg,image/png,image/webp" :disabled="bgUploading" @change="onBgFile('create', $event)" />
              <n-button v-if="form.background_image" size="tiny" quaternary @click="clearBg('create')">清除</n-button>
            </n-space>
            <img v-if="form.background_image" class="bg-preview" :src="form.background_image" alt="" />
          </n-space>
        </n-form-item>
        <n-form-item label="使用細則（繁中）">
          <WangSimpleEditor v-model="form.rules_zh" preset="admin" placeholder="使用細則（繁中）…" :height="180" />
        </n-form-item>
        <n-form-item label="使用細則（英文）">
          <WangSimpleEditor v-model="form.rules_en" preset="admin" placeholder="Usage rules (EN)…" :height="180" />
        </n-form-item>
      </n-form>
      <template #footer>
        <n-space justify="end">
          <n-button @click="createOpen = false">取消</n-button>
          <n-button type="primary" :loading="createSaving" @click="create">創建</n-button>
        </n-space>
      </template>
    </n-modal>

    <n-modal
      v-model:show="editOpen"
      preset="card"
      :title="`編輯優惠券 · ${edit.code}`"
      style="width: min(44rem, 94vw)"
      :mask-closable="false"
      :close-on-esc="false"
    >
      <n-form label-placement="top">
        <n-form-item label="中文名"><n-input v-model:value="edit.name_zh" /></n-form-item>
        <n-form-item label="英文名"><n-input v-model:value="edit.name_en" /></n-form-item>
        <n-form-item label="發放時段">
          <n-space vertical :size="8" style="width: 100%">
            <n-radio-group v-model:value="edit.claim_mode">
              <n-radio value="range">指定時段</n-radio>
              <n-radio value="permanent">永久</n-radio>
            </n-radio-group>
            <n-space v-if="edit.claim_mode === 'range'" wrap>
              <n-date-picker
                v-model:formatted-value="edit.starts_at"
                type="date"
                value-format="yyyy-MM-dd"
                clearable
                placeholder="開始"
                style="width: 10rem"
              />
              <span>至</span>
              <n-date-picker
                v-model:formatted-value="edit.ends_at"
                type="date"
                value-format="yyyy-MM-dd"
                clearable
                placeholder="結束"
                style="width: 10rem"
              />
            </n-space>
          </n-space>
        </n-form-item>
        <n-form-item label="使用有效期">
          <n-space align="center">
            <span>領取後</span>
            <n-input-number v-model:value="edit.valid_days" :min="0" style="width: 7rem" />
            <span>天有效（0 = 永久）</span>
          </n-space>
        </n-form-item>
        <n-form-item label="背景圖（可選，H5 券卡 100% 寬）">
          <n-space vertical :size="8" style="width: 100%">
            <n-input v-model:value="edit.background_image" placeholder="/storage/... 或留空用漸變" />
            <n-space align="center">
              <input type="file" accept="image/jpeg,image/png,image/webp" :disabled="bgUploading" @change="onBgFile('edit', $event)" />
              <n-button v-if="edit.background_image" size="tiny" quaternary @click="clearBg('edit')">清除</n-button>
            </n-space>
            <img v-if="edit.background_image" class="bg-preview" :src="edit.background_image" alt="" />
          </n-space>
        </n-form-item>
        <n-form-item label="使用細則（繁中）">
          <WangSimpleEditor v-model="edit.rules_zh" preset="admin" placeholder="使用細則（繁中）…" :height="220" />
        </n-form-item>
        <n-form-item label="使用細則（英文）">
          <WangSimpleEditor v-model="edit.rules_en" preset="admin" placeholder="Usage rules (EN)…" :height="220" />
        </n-form-item>
      </n-form>
      <template #footer>
        <n-space justify="end">
          <n-button @click="editOpen = false">取消</n-button>
          <n-button type="primary" :loading="editSaving" @click="saveEdit">保存</n-button>
        </n-space>
      </template>
    </n-modal>
  </n-space>
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
.bg-thumb {
  display: block;
  width: 4.5rem;
  height: 2rem;
  object-fit: cover;
  border-radius: 0.25rem;
  border: 1px solid #eee;
}
.bg-preview {
  display: block;
  width: 100%;
  max-width: 28rem;
  height: auto;
  border-radius: 0.5rem;
  border: 1px solid #eee;
}
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
.temp-hint {
  margin: 0.75rem 0 0;
  color: #8a8378;
  font-size: 0.75rem;
}
</style>
