<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { NButton, NCard, NEmpty, NSpace, NSwitch, NTag, useMessage } from 'naive-ui'
import { api } from '../api'

type LayoutModule = {
  id: number
  module_key: string
  module_type: string
  ref_type: string | null
  ref_code: string | null
  title: { 'zh-HK': string; en: string }
  enabled: number
  sort_order: number
  config: Record<string, unknown> | null
}

const TYPE_LABEL: Record<string, string> = {
  banner_swipe: '輪播廣告',
  text_marquee: '廣告字幕',
  category_row: '商品分類',
  seckill_rail: '秒殺專區',
  recommend_feed: '為你推薦',
  campaign_rail: '活動橫滑',
  product_showcase: '商品櫥窗',
  image_block: '圖片塊',
}

const message = useMessage()
const items = ref<LayoutModule[]>([])
const saving = ref(false)
const dragFrom = ref<number | null>(null)
const dragOver = ref<number | null>(null)
const dirty = ref(false)

async function load() {
  const data = await api<{ items: LayoutModule[] }>('/api/v1/admin/home-layout', {}, true)
  items.value = data.items
  dirty.value = false
  dragFrom.value = null
  dragOver.value = null
}

function move(index: number, delta: number) {
  const next = index + delta
  if (next < 0 || next >= items.value.length) return
  const copy = items.value.slice()
  const [row] = copy.splice(index, 1)
  copy.splice(next, 0, row)
  items.value = copy
  dirty.value = true
}

function onDragStart(index: number, ev: DragEvent) {
  dragFrom.value = index
  if (ev.dataTransfer) {
    ev.dataTransfer.effectAllowed = 'move'
    ev.dataTransfer.setData('text/plain', String(index))
  }
}

function onDragOver(index: number, ev: DragEvent) {
  ev.preventDefault()
  if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'move'
  dragOver.value = index
}

function onDrop(index: number, ev: DragEvent) {
  ev.preventDefault()
  const from = dragFrom.value
  dragFrom.value = null
  dragOver.value = null
  if (from == null || from === index) return
  const copy = items.value.slice()
  const [row] = copy.splice(from, 1)
  copy.splice(index, 0, row)
  items.value = copy
  dirty.value = true
}

function onDragEnd() {
  dragFrom.value = null
  dragOver.value = null
}

async function saveOrder() {
  saving.value = true
  try {
    const data = await api<{ items: LayoutModule[] }>(
      '/api/v1/admin/home-layout/reorder',
      {
        method: 'PUT',
        body: JSON.stringify({
          modules: items.value.map((m) => ({ id: m.id, enabled: m.enabled })),
        }),
      },
      true,
    )
    items.value = data.items
    dirty.value = false
    message.success('首頁佈局已保存')
  } finally {
    saving.value = false
  }
}

async function toggleEnabled(row: LayoutModule, enabled: boolean) {
  row.enabled = enabled ? 1 : 0
  await api(
    `/api/v1/admin/home-layout/${row.id}`,
    { method: 'PUT', body: JSON.stringify({ enabled: row.enabled }) },
    true,
  )
  message.success(enabled ? '已啟用' : '已停用')
}

onMounted(load)
</script>

<template>
  <n-card title="首頁佈局">
    <p class="hint">
      拖動手把調整模組上下順序，或用上移／下移；開關即時生效，排序需點「保存排序」。
    </p>
    <n-empty v-if="!items.length" description="暫無模組。在「活動專區」勾選展示於首頁後會出現於此。" />
    <div
      v-for="(row, index) in items"
      :key="row.id"
      class="row"
      :class="{
        dragging: dragFrom === index,
        'drag-over': dragOver === index && dragFrom !== index,
      }"
      draggable="true"
      @dragstart="onDragStart(index, $event)"
      @dragover="onDragOver(index, $event)"
      @drop="onDrop(index, $event)"
      @dragend="onDragEnd"
    >
      <span class="handle" title="拖放排序" aria-hidden="true">⋮⋮</span>
      <div class="meta">
        <strong>{{ row.title['zh-HK'] || row.module_key }}</strong>
        <p>
          <n-tag size="small" :bordered="false">{{ TYPE_LABEL[row.module_type] || row.module_type }}</n-tag>
          <span class="key">{{ row.module_key }}</span>
          <span v-if="row.ref_code"> · {{ row.ref_code }}</span>
        </p>
      </div>
      <n-space align="center">
        <n-switch
          :value="row.enabled === 1"
          @update:value="(v: boolean) => toggleEnabled(row, v)"
        />
        <n-button size="tiny" :disabled="index === 0" @click="move(index, -1)">上移</n-button>
        <n-button size="tiny" :disabled="index === items.length - 1" @click="move(index, 1)">下移</n-button>
      </n-space>
    </div>
    <n-space style="margin-top: 1rem">
      <n-button type="primary" :loading="saving" :disabled="!items.length" @click="saveOrder">
        保存排序{{ dirty ? ' *' : '' }}
      </n-button>
      <n-button :disabled="saving" @click="load">重新加載</n-button>
    </n-space>
  </n-card>
</template>

<style scoped>
.hint {
  margin: 0 0 0.75rem;
  color: #888;
  font-size: 0.8125rem;
}
.row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem 0.5rem;
  border-bottom: 1px solid #eee;
  border-radius: 4px;
  cursor: grab;
  user-select: none;
  transition: background 0.12s ease, box-shadow 0.12s ease;
}
.row:active {
  cursor: grabbing;
}
.row.dragging {
  opacity: 0.55;
  background: #f7f7f7;
}
.row.drag-over {
  box-shadow: inset 0 2px 0 #18a058;
}
.handle {
  flex: none;
  width: 1.25rem;
  color: #bbb;
  letter-spacing: -0.15em;
  font-size: 0.875rem;
  line-height: 1;
  cursor: grab;
}
.meta {
  min-width: 0;
  flex: 1;
}
.meta p {
  margin: 0.25rem 0 0;
  color: #888;
  font-size: 0.8125rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  align-items: center;
}
.key {
  font-family: ui-monospace, monospace;
  font-size: 0.75rem;
}
</style>
