<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NDrawer,
  NDrawerContent,
  NInput,
  NSelect,
  NSpace,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'

type Row = {
  id: number
  product_code: string
  stars: number
  author: string
  body: string
  images: string[]
  follow?: string | null
  reply?: string | null
  admin_reply?: string | null
  replied_at?: string | null
  is_hidden?: number
  created_at: string
  order_id?: number
}

const message = useMessage()
const rows = ref<Row[]>([])
const hiddenFilter = ref<string>('')
const productCode = ref('')
const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'
function assetUrl(u: string) {
  return u.startsWith('http') ? u : `${apiBase}${u}`
}

const hiddenOptions = [
  { label: '全部', value: '' },
  { label: '顯示中', value: '0' },
  { label: '已隱藏', value: '1' },
]

const show = ref(false)
const current = ref<Row | null>(null)
const replyText = ref('')
const busy = ref(false)

const isHidden = computed(() => Number(current.value?.is_hidden || 0) === 1)

async function load() {
  const qs = new URLSearchParams()
  if (hiddenFilter.value !== '') qs.set('is_hidden', hiddenFilter.value)
  if (productCode.value.trim()) qs.set('product_code', productCode.value.trim())
  const q = qs.toString() ? `?${qs}` : ''
  const data = await api<{ items: Row[] }>(`/api/v1/admin/reviews${q}`, {}, true)
  rows.value = data.items
}

onMounted(load)

async function openDetail(row: Row) {
  const data = await api<Row>(`/api/v1/admin/reviews/${row.id}`, {}, true)
  current.value = data
  replyText.value = data.admin_reply || data.reply || ''
  show.value = true
}

async function toggleHidden() {
  if (!current.value) return
  busy.value = true
  try {
    const next = isHidden.value ? 0 : 1
    current.value = await api<Row>(
      `/api/v1/admin/reviews/${current.value.id}/visibility`,
      { method: 'POST', body: JSON.stringify({ is_hidden: next }) },
      true,
    )
    message.success(next ? '已隱藏（前台不顯示）' : '已恢復顯示')
    await load()
  } catch (e: any) {
    message.error(e?.message || '操作失敗')
  } finally {
    busy.value = false
  }
}

async function saveReply() {
  if (!current.value) return
  if (!replyText.value.trim()) {
    message.warning('請填寫商家回覆')
    return
  }
  busy.value = true
  try {
    current.value = await api<Row>(
      `/api/v1/admin/reviews/${current.value.id}/reply`,
      { method: 'POST', body: JSON.stringify({ reply: replyText.value.trim() }) },
      true,
    )
    message.success('回覆已保存')
    await load()
  } catch (e: any) {
    message.error(e?.message || '保存失敗')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <n-card title="評價管理">
    <n-space style="margin-bottom: 1rem">
      <n-select v-model:value="hiddenFilter" :options="hiddenOptions" style="width: 9rem" @update:value="load" />
      <n-input
        v-model:value="productCode"
        placeholder="商品代碼"
        style="width: 10rem"
        @keyup.enter="load"
      />
      <n-button @click="load">刷新</n-button>
    </n-space>

    <table class="table">
      <thead>
        <tr>
          <th>商品</th>
          <th>作者</th>
          <th>星級</th>
          <th>內容</th>
          <th>狀態</th>
          <th>回覆</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.id">
          <td>{{ row.product_code }}</td>
          <td>{{ row.author }}</td>
          <td>{{ row.stars }}★</td>
          <td class="snip">{{ row.body }}</td>
          <td>
            <n-tag size="small" :type="row.is_hidden ? 'warning' : 'success'">
              {{ row.is_hidden ? '已隱藏' : '顯示中' }}
            </n-tag>
          </td>
          <td>{{ row.reply || row.admin_reply ? '有' : '—' }}</td>
          <td><n-button size="tiny" type="primary" @click="openDetail(row)">管理</n-button></td>
        </tr>
      </tbody>
    </table>
  </n-card>

  <n-drawer v-model:show="show" :width="480" placement="right">
    <n-drawer-content v-if="current" title="評價詳情" closable>
      <n-space vertical size="large">
        <div class="meta">
          <div>{{ current.product_code }} · {{ current.author }} · {{ current.stars }}★</div>
          <div>{{ current.created_at }}</div>
          <n-tag size="small" :type="isHidden ? 'warning' : 'success'">
            {{ isHidden ? '已隱藏' : '顯示中' }}
          </n-tag>
        </div>
        <div>
          <strong>主評</strong>
          <p class="body">{{ current.body }}</p>
          <div v-if="current.images?.length" class="imgs">
            <a v-for="(u, i) in current.images" :key="i" :href="assetUrl(u)" target="_blank" rel="noreferrer">
              <img :src="assetUrl(u)" alt="" />
            </a>
          </div>
        </div>
        <div v-if="current.follow">
          <strong>追評</strong>
          <p class="body">{{ current.follow }}</p>
        </div>
        <div>
          <strong>商家回覆</strong>
          <n-input
            v-model:value="replyText"
            type="textarea"
            :rows="3"
            placeholder="回覆將顯示在商品詳情評價區"
            style="margin-top: 0.5rem"
          />
        </div>
        <n-space>
          <n-button type="primary" :loading="busy" @click="saveReply">保存回覆</n-button>
          <n-button :loading="busy" @click="toggleHidden">{{ isHidden ? '恢復顯示' : '隱藏評價' }}</n-button>
        </n-space>
      </n-space>
    </n-drawer-content>
  </n-drawer>
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
.snip {
  max-width: 14rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.meta {
  font-size: 0.875rem;
  line-height: 1.6;
}
.body {
  margin: 0.35rem 0 0;
  white-space: pre-wrap;
  font-size: 0.875rem;
}
.imgs {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
}
.imgs img {
  width: 4rem;
  height: 4rem;
  object-fit: cover;
  border-radius: 0.25rem;
}
</style>
