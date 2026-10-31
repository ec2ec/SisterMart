<script setup lang="ts">
import { showError } from '../feedback'
import { onMounted, reactive, ref } from 'vue'
import {
  NButton,
  NCard,
  NCheckbox,
  NForm,
  NFormItem,
  NInput,
  NRadio,
  NRadioGroup,
  NSelect,
  NPagination,
  NSpace,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import { useClientPager } from '../composables/useClientPager'
import { useTableRowSelect } from '../composables/useTableRowSelect'
import RichEditor from '../components/RichEditor.vue'

type Batch = {
  batch_id: string
  title: { 'zh-HK': string; en: string }
  body: { 'zh-HK': string; en: string }
  scope?: string
  member_ids?: number[]
  send_email?: boolean
  email_queued?: number
  recipient_count: number
  created_at: string
}

type MemberOpt = { label: string; value: number }

const message = useMessage()
const rows = ref<Batch[]>([])
const { page, pageSize, total, paged } = useClientPager(rows, 30)
const { rowAttrs } = useTableRowSelect()
const memberOptions = ref<MemberOpt[]>([])
const memberLoading = ref(false)
const form = reactive({
  title_zh: '',
  title_en: '',
  body_zh: '',
  body_en: '',
  scope: 'all' as 'all' | 'selected',
  member_ids: [] as number[],
  send_email: false,
})
const busy = ref(false)

async function load() {
  const data = await api<{ items: Batch[] }>('/api/v1/admin/messages', {}, true)
  rows.value = data.items
}

onMounted(load)

async function searchMembers(q: string) {
  memberLoading.value = true
  try {
    const qs = q.trim() ? `?q=${encodeURIComponent(q.trim())}` : ''
    const data = await api<{ items: Array<{ id: number; email: string | null; phone: string | null }> }>(
      `/api/v1/admin/members${qs}`,
      {},
      true,
    )
    memberOptions.value = (data.items || []).slice(0, 50).map((m) => ({
      value: m.id,
      label: `#${m.id} ${m.email || m.phone || ''}`,
    }))
  } finally {
    memberLoading.value = false
  }
}

function stripPreview(html: string) {
  const t = html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
  return t.length > 80 ? t.slice(0, 80) + '…' : t
}

async function create() {
  if (!form.title_zh.trim() || !form.body_zh.replace(/<[^>]+>/g, '').trim()) {
    message.warning('請填寫中文標題與內容')
    return
  }
  if (form.scope === 'selected' && !form.member_ids.length) {
    message.warning('請選擇至少一位會員')
    return
  }
  busy.value = true
  try {
    const payload: Record<string, unknown> = {
      title_zh: form.title_zh,
      title_en: form.title_en || form.title_zh,
      body_zh: form.body_zh,
      body_en: form.body_en || form.body_zh,
      send_email: form.send_email,
    }
    if (form.scope === 'selected') {
      payload.member_ids = form.member_ids
    }
    const res = await api<{ sent: number; email_queued: number }>(
      '/api/v1/admin/messages',
      { method: 'POST', body: JSON.stringify(payload) },
      true,
    )
    message.success(
      `已發送站內信 ${res.sent} 人` +
        (form.send_email ? `；郵件入隊 ${res.email_queued}` : ''),
    )
    form.title_zh = ''
    form.title_en = ''
    form.body_zh = ''
    form.body_en = ''
    form.member_ids = []
    form.send_email = false
    form.scope = 'all'
    await load()
  } catch (e: any) {
    showError(e?.message || '發送失敗')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <n-card title="系統消息發佈">
    <n-form label-placement="left" label-width="7rem" style="max-width: 46rem; margin-bottom: 1.5rem">
      <n-form-item label="中文標題"><n-input v-model:value="form.title_zh" /></n-form-item>
      <n-form-item label="英文標題"><n-input v-model:value="form.title_en" placeholder="可留空同中文" /></n-form-item>
      <n-form-item label="中文內容">
        <RichEditor v-model="form.body_zh" placeholder="支援粗體、列表、連結等富文本" />
      </n-form-item>
      <n-form-item label="英文內容">
        <RichEditor v-model="form.body_en" placeholder="可留空同中文" />
      </n-form-item>
      <n-form-item label="推送範圍">
        <n-radio-group v-model:value="form.scope">
          <n-space>
            <n-radio value="all">全局推送</n-radio>
            <n-radio value="selected">指定用戶</n-radio>
          </n-space>
        </n-radio-group>
      </n-form-item>
      <n-form-item v-if="form.scope === 'selected'" label="選擇會員">
        <n-select
          v-model:value="form.member_ids"
          multiple
          filterable
          remote
          :options="memberOptions"
          :loading="memberLoading"
          placeholder="搜索郵箱 / 手機 / 推廣碼"
          style="width: 100%"
          @search="searchMembers"
          @focus="() => searchMembers('')"
        />
      </n-form-item>
      <n-form-item label="郵件">
        <n-checkbox v-model:checked="form.send_email">同時發送郵件（需會員有郵箱；未配 MAIL_FROM 時記 mock）</n-checkbox>
      </n-form-item>
      <n-button type="primary" :loading="busy" @click="create">發佈</n-button>
    </n-form>

    <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>時間</th>
          <th>標題</th>
          <th>內容</th>
          <th>範圍</th>
          <th>人數</th>
          <th>郵件</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in paged" :key="row.batch_id" v-bind="rowAttrs(row.batch_id)">
          <td>{{ row.created_at }}</td>
          <td>{{ row.title['zh-HK'] }}</td>
          <td class="snip">{{ stripPreview(row.body['zh-HK'] || '') }}</td>
          <td>
            <n-tag size="small" :type="row.scope === 'selected' ? 'info' : 'success'">
              {{ row.scope === 'selected' ? '指定用戶' : '全局' }}
            </n-tag>
          </td>
          <td>{{ row.recipient_count }}</td>
          <td>
            <template v-if="row.send_email">入隊 {{ row.email_queued ?? 0 }}</template>
            <template v-else>—</template>
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
.table {
  width: 100%;
  min-width: 900px;
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
.snip {
  max-width: 16rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.pager {
  display: flex;
  justify-content: flex-end;
  margin-top: 1rem;
}
</style>
