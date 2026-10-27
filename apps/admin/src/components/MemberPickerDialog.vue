<script setup lang="ts">
import { showError } from '../feedback'
import { computed, ref, watch } from 'vue'
import { NButton, NEmpty, NInput, NModal, NSpace, NSpin } from 'naive-ui'
import { api } from '../api'

export type MemberPickResult = {
  id: number
  email: string | null
  phone: string | null
  nickname: string | null
  promo_code: string
  status: number
}

const props = withDefaults(
  defineProps<{
    show: boolean
    title?: string
    /** 已在白名單的 id，列表中標記且不可再選 */
    excludeIds?: number[]
  }>(),
  {
    title: '選擇會員',
    excludeIds: () => [],
  },
)

const emit = defineEmits<{
  'update:show': [value: boolean]
  select: [value: MemberPickResult]
}>()

const keyword = ref('')
const loading = ref(false)
const rows = ref<MemberPickResult[]>([])
const searched = ref(false)
let searchTimer: ReturnType<typeof setTimeout> | null = null

const visible = computed({
  get: () => props.show,
  set: (v: boolean) => emit('update:show', v),
})

const excluded = computed(() => new Set(props.excludeIds || []))

function resetState() {
  keyword.value = ''
  rows.value = []
  searched.value = false
  if (searchTimer) {
    clearTimeout(searchTimer)
    searchTimer = null
  }
}

watch(
  () => props.show,
  (v) => {
    if (v) {
      resetState()
      void load()
    }
  },
)

async function load() {
  loading.value = true
  try {
    const q = keyword.value.trim()
    const qs = q ? `?q=${encodeURIComponent(q)}` : ''
    const data = await api<{ items: MemberPickResult[] }>(`/api/v1/admin/members${qs}`, {}, true)
    rows.value = data.items || []
    searched.value = true
  } catch (e: unknown) {
    showError(e instanceof Error ? e.message : '搜索失敗')
  } finally {
    loading.value = false
  }
}

function scheduleSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    void load()
  }, 280)
}

function pick(row: MemberPickResult) {
  if (excluded.value.has(row.id)) return
  emit('select', row)
  visible.value = false
}

function labelOf(m: MemberPickResult) {
  return m.email || m.phone || m.nickname || m.promo_code || '—'
}
</script>

<template>
  <n-modal
    v-model:show="visible"
    preset="card"
    :title="title"
    style="width: min(40rem, 96vw)"
    :mask-closable="false"
   :close-on-esc="false">
    <n-space style="margin-bottom: 0.75rem">
      <n-input
        v-model:value="keyword"
        clearable
        placeholder="搜索郵箱 / 手機 / 推廣碼 / ID"
        style="width: 18rem"
        @keyup.enter="load"
        @update:value="scheduleSearch"
      />
      <n-button :loading="loading" @click="load">搜索</n-button>
    </n-space>

    <n-spin :show="loading">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>帳號</th>
              <th>推廣碼</th>
              <th>狀態</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id" :class="{ off: row.status !== 1 }">
              <td>{{ row.id }}</td>
              <td class="col-name">
                <div>{{ labelOf(row) }}</div>
                <div v-if="row.email && row.phone" class="sub">{{ row.phone }}</div>
              </td>
              <td>{{ row.promo_code || '—' }}</td>
              <td>{{ row.status === 1 ? '正常' : '停用' }}</td>
              <td>
                <n-button
                  size="tiny"
                  type="primary"
                  :disabled="excluded.has(row.id)"
                  @click="pick(row)"
                >
                  {{ excluded.has(row.id) ? '已加入' : '加入' }}
                </n-button>
              </td>
            </tr>
          </tbody>
        </table>
        <n-empty
          v-if="searched && !loading && !rows.length"
          description="沒有匹配會員"
          style="padding: 1.5rem 0"
        />
      </div>
    </n-spin>
  </n-modal>
</template>

<style scoped>
.table-wrap {
  max-height: min(24rem, 60vh);
  overflow: auto;
}
.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
}
.table th,
.table td {
  padding: 0.45rem 0.5rem;
  border-bottom: 1px solid #f0ebe4;
  text-align: left;
  vertical-align: middle;
}
.table th {
  color: #8a8378;
  font-weight: 600;
  position: sticky;
  top: 0;
  background: #fff;
}
.col-name .sub {
  color: #999;
  font-size: 0.75rem;
}
tr.off {
  opacity: 0.55;
}
</style>
