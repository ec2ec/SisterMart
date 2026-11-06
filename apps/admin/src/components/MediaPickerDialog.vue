<script setup lang="ts">
import { ref, watch } from 'vue'
import { NButton, NEmpty, NModal, NPagination, NSpin, NTag } from 'naive-ui'
import { api } from '../api'
import { thumbUrl } from '../utils/assetUrl'

const props = defineProps<{
  show: boolean
  scene: string
}>()

const emit = defineEmits<{
  'update:show': [value: boolean]
  select: [url: string]
}>()

type LibItem = { url: string; thumb_url?: string | null; bytes: number; tags?: string[] }

const source = ref<'scene' | 'public'>('scene')
const items = ref<LibItem[]>([])
const tagOptions = ref<string[]>([])
const activeTag = ref('')
const total = ref(0)
const page = ref(1)
const pageSize = 24
const loading = ref(false)
const error = ref('')

watch(
  () => props.show,
  (open) => {
    if (!open) return
    source.value = 'scene'
    activeTag.value = ''
    page.value = 1
    void load()
  },
)

watch(source, () => {
  activeTag.value = ''
  page.value = 1
  void load()
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const q = new URLSearchParams({
      scene: props.scene,
      source: source.value,
      page: String(page.value),
      page_size: String(pageSize),
    })
    if (source.value === 'public' && activeTag.value) {
      q.set('tag', activeTag.value)
    }
    const data = await api<{ items: LibItem[]; total: number; tags?: string[] }>(
      `/api/v1/admin/uploads/library?${q}`,
      {},
      true,
      true,
    )
    items.value = data.items || []
    total.value = data.total || 0
    tagOptions.value = data.tags || []
  } catch (e: unknown) {
    items.value = []
    total.value = 0
    tagOptions.value = []
    error.value = e instanceof Error ? e.message : '載入失敗'
  } finally {
    loading.value = false
  }
}

function setTag(tag: string) {
  activeTag.value = activeTag.value === tag ? '' : tag
  page.value = 1
  void load()
}

function pick(url: string) {
  emit('select', url)
  emit('update:show', false)
}

function close() {
  emit('update:show', false)
}

function preview(item: LibItem) {
  return thumbUrl(item.thumb_url || item.url)
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
    <div class="tabs">
      <button type="button" :class="{ on: source === 'scene' }" @click="source = 'scene'">本場景</button>
      <button type="button" :class="{ on: source === 'public' }" @click="source = 'public'">公共圖庫</button>
    </div>
    <p class="lead">
      <template v-if="source === 'scene'">只顯示這個場景已經上傳過的圖片。選一張即可套用，不必再傳一次。</template>
      <template v-else>共用素材（保養說明、服務須知等）。選中後直接引用同一路徑，不會複製檔案。</template>
    </p>
    <div v-if="source === 'public' && tagOptions.length" class="tags">
      <n-tag
        v-for="tag in tagOptions"
        :key="tag"
        size="small"
        round
        :type="activeTag === tag ? 'primary' : 'default'"
        style="cursor: pointer"
        @click="setTag(tag)"
      >
        {{ tag }}
      </n-tag>
    </div>
    <n-spin :show="loading">
      <p v-if="error" class="err">{{ error }}</p>
      <n-empty
        v-else-if="!items.length"
        :description="source === 'public' ? '公共圖庫還沒有上架圖片' : '此場景還沒有上傳過的圖片'"
      />
      <div v-else class="grid">
        <button v-for="item in items" :key="item.url" type="button" class="cell" @click="pick(item.url)">
          <img :src="preview(item)" alt="" />
          <span v-if="item.tags?.length" class="cell-tag">{{ item.tags[0] }}</span>
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
.tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}
.tabs button {
  border: 1px solid #e5e1d8;
  background: #fff;
  border-radius: 999px;
  padding: 0.35rem 0.9rem;
  font-size: 0.8125rem;
  cursor: pointer;
}
.tabs button.on {
  border-color: #2f6fed;
  color: #2f6fed;
  background: #eef4ff;
}
.lead {
  margin: 0 0 0.75rem;
  color: #8a8378;
  font-size: 0.8125rem;
}
.tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-bottom: 0.75rem;
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
  position: relative;
  aspect-ratio: 1;
  border: 1px solid #ebe6dc;
  border-radius: 0.5rem;
  padding: 0;
  overflow: hidden;
  background: #f6f4ef;
  cursor: pointer;
}
.cell img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.cell-tag {
  position: absolute;
  left: 0.25rem;
  bottom: 0.25rem;
  max-width: calc(100% - 0.5rem);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.625rem;
  line-height: 1.2;
  padding: 0.1rem 0.3rem;
  border-radius: 0.25rem;
  background: rgba(22, 22, 43, 0.65);
  color: #fff;
}
.pager {
  display: flex;
  justify-content: center;
  margin-top: 0.75rem;
}
</style>
