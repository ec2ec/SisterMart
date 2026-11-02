<script setup lang="ts">
import { ref, watch } from 'vue'
import { NButton, NEmpty, NModal, NPagination, NSpin } from 'naive-ui'
import { api } from '../api'
import { assetUrl } from '../utils/assetUrl'

const props = defineProps<{
  show: boolean
  scene: string
}>()

const emit = defineEmits<{
  'update:show': [value: boolean]
  select: [url: string]
}>()

type LibItem = { url: string; bytes: number }

const items = ref<LibItem[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = 24
const loading = ref(false)
const error = ref('')

watch(
  () => props.show,
  (open) => {
    if (!open) return
    page.value = 1
    void load()
  },
)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const q = new URLSearchParams({
      scene: props.scene,
      page: String(page.value),
      page_size: String(pageSize),
    })
    const data = await api<{ items: LibItem[]; total: number }>(
      `/api/v1/admin/uploads/library?${q}`,
      {},
      true,
      true,
    )
    items.value = data.items || []
    total.value = data.total || 0
  } catch (e: unknown) {
    items.value = []
    total.value = 0
    error.value = e instanceof Error ? e.message : '載入失敗'
  } finally {
    loading.value = false
  }
}

function pick(url: string) {
  emit('select', url)
  emit('update:show', false)
}

function close() {
  emit('update:show', false)
}
</script>

<template>
  <n-modal
    :show="show"
    preset="card"
    title="素材庫"
    style="width: min(40rem, 92vw)"
    :mask-closable="false"
    :close-on-esc="false"
    @update:show="(v: boolean) => emit('update:show', v)"
  >
    <p class="lead">只顯示這個場景已經上傳過的圖片。選一張即可套用，不必再傳一次。</p>
    <n-spin :show="loading">
      <p v-if="error" class="err">{{ error }}</p>
      <n-empty v-else-if="!items.length" description="此場景還沒有上傳過的圖片" />
      <div v-else class="grid">
        <button v-for="item in items" :key="item.url" type="button" class="cell" @click="pick(item.url)">
          <img :src="assetUrl(item.url)" alt="" />
        </button>
      </div>
    </n-spin>
    <div v-if="total > pageSize" class="pager">
      <n-pagination
        v-model:page="page"
        :item-count="total"
        :page-size="pageSize"
        @update:page="load"
      />
    </div>
    <template #footer>
      <n-button @click="close">關閉</n-button>
    </template>
  </n-modal>
</template>

<style scoped>
.lead {
  margin: 0 0 0.75rem;
  color: #8a8378;
  font-size: 0.8125rem;
}
.err {
  color: #d03050;
  margin: 0;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(5.5rem, 1fr));
  gap: 0.5rem;
}
.cell {
  padding: 0;
  border: 1px solid #eee;
  border-radius: 0.375rem;
  overflow: hidden;
  background: #fafafa;
  cursor: pointer;
  aspect-ratio: 1;
}
.cell:hover {
  border-color: #de46ad;
}
.cell img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.pager {
  margin-top: 0.75rem;
  display: flex;
  justify-content: flex-end;
}
</style>
