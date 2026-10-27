<script setup lang="ts">
import { showError } from '../feedback'
import { computed, ref } from 'vue'
import { NSpin, useDialog, useMessage } from 'naive-ui'
import { uploadImage } from '../api'
import { assetUrl, toStoredUrl } from '../utils/assetUrl'

export type MediaItem = {
  media_type: string
  url: string
}

const props = withDefaults(
  defineProps<{
    modelValue: MediaItem[]
    max?: number
    /** 單張上限（字節），UI 文案按 3MB */
    maxBytes?: number
  }>(),
  {
    max: 12,
    maxBytes: 3 * 1024 * 1024,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: MediaItem[]]
}>()

const message = useMessage()
const dialog = useDialog()
const inputRef = ref<HTMLInputElement | null>(null)
const busy = ref(false)

const items = computed({
  get: () => props.modelValue || [],
  set: (v: MediaItem[]) => emit('update:modelValue', v),
})

const canAdd = computed(() => items.value.length < props.max)

function previewUrl(url: string) {
  return assetUrl(url)
}

function isVideo(item: MediaItem) {
  return item.media_type === 'video' || /\.(mp4|webm|mov)(\?|$)/i.test(item.url)
}

function openPicker() {
  if (!canAdd.value || busy.value) return
  inputRef.value?.click()
}

function removeAt(idx: number) {
  dialog.warning({
    maskClosable: false,
    closeOnEsc: false,
    title: '確認刪除圖片',
    content: '確定從商品圖庫移除此圖？保存商品後才會生效。',
    positiveText: '刪除',
    negativeText: '取消',
    onPositiveClick: () => {
      const next = items.value.slice()
      next.splice(idx, 1)
      items.value = next
    },
  })
}

function move(idx: number, delta: number) {
  const j = idx + delta
  if (j < 0 || j >= items.value.length) return
  const next = items.value.slice()
  const [row] = next.splice(idx, 1)
  next.splice(j, 0, row)
  items.value = next
}

async function onFiles(ev: Event) {
  const input = ev.target as HTMLInputElement
  const files = Array.from(input.files || [])
  input.value = ''
  if (!files.length) return

  const room = props.max - items.value.length
  if (room <= 0) {
    message.warning(`最多上傳 ${props.max} 張`)
    return
  }

  const picked = files.slice(0, room)
  busy.value = true
  try {
    const added: MediaItem[] = []
    for (const file of picked) {
      if (file.size > props.maxBytes) {
        message.warning(`「${file.name}」超過 3M，已跳過`)
        continue
      }
      const isVid = /^video\//i.test(file.type) || /\.(mp4|webm|mov)$/i.test(file.name)
      if (isVid) {
        message.warning(`暫不支持視頻直傳：「${file.name}」請先轉為圖片或聯繫後台擴展上傳`)
        continue
      }
      if (!/^image\//i.test(file.type) && !/\.(jpe?g|png|gif|webp)$/i.test(file.name)) {
        message.warning(`不支持的格式：「${file.name}」`)
        continue
      }
      try {
        const data = await uploadImage(file, {
          preset: 'product',
          max_upload_bytes: props.maxBytes,
        })
        const url = toStoredUrl(data.url)
        added.push({
          media_type: 'image',
          url,
        })
      } catch (e: unknown) {
        showError(e instanceof Error ? e.message : `上傳失敗：${file.name}`)
      }
    }
    if (added.length) {
      items.value = [...items.value, ...added]
      message.success(`已添加 ${added.length} 張`)
    }
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="gallery">
    <n-spin :show="busy">
      <div class="row">
        <button
          v-if="canAdd"
          type="button"
          class="tile add"
          :disabled="busy"
          @click="openPicker"
        >
          <span class="plus">+</span>
          <span class="add-label">上傳照片</span>
        </button>

        <div v-for="(item, idx) in items" :key="`${item.url}-${idx}`" class="tile media">
          <video v-if="isVideo(item)" class="thumb" :src="previewUrl(item.url)" muted />
          <img v-else class="thumb" :src="previewUrl(item.url)" alt="" />
          <div class="actions">
            <button type="button" title="左移" :disabled="idx === 0" @click="move(idx, -1)">‹</button>
            <button type="button" title="右移" :disabled="idx === items.length - 1" @click="move(idx, 1)">›</button>
            <button type="button" class="del" title="刪除" @click="removeAt(idx)">×</button>
          </div>
        </div>
      </div>
    </n-spin>

    <p class="hint">
      商品圖片單張大小不能超過 3M，最多可上傳 {{ max }} 張，支持 JPG、PNG、GIF、視頻等格式
    </p>

    <input
      ref="inputRef"
      type="file"
      class="hidden"
      accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm"
      multiple
      @change="onFiles"
    />
  </div>
</template>

<style scoped>
.gallery {
  width: 100%;
}

.row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: flex-start;
}

.tile {
  position: relative;
  width: 5.5rem;
  height: 5.5rem;
  border-radius: 0.375rem;
  box-sizing: border-box;
  overflow: hidden;
  flex: 0 0 auto;
  padding: 0;
  background: #fff;
}

.tile.add {
  border: 1px dashed #c8c8c8;
  background: #fafafa;
  color: #999;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  cursor: pointer;
}

.tile.add:hover:not(:disabled) {
  border-color: #de46ad;
  color: #de46ad;
}

.tile.add:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.plus {
  font-size: 1.75rem;
  line-height: 1;
  font-weight: 300;
}

.add-label {
  font-size: 0.75rem;
}

.tile.media {
  border: 1px solid #eee;
}

.thumb {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.actions {
  position: absolute;
  inset: auto 0 0 0;
  display: flex;
  justify-content: stretch;
  background: rgba(0, 0, 0, 0.45);
}

.actions button {
  flex: 1;
  border: 0;
  background: transparent;
  color: #fff;
  font-size: 0.875rem;
  line-height: 1.4;
  cursor: pointer;
  padding: 0.15rem 0;
}

.actions button:disabled {
  opacity: 0.35;
  cursor: default;
}

.actions .del {
  color: #ffb4b4;
  font-weight: 700;
}

.hint {
  margin: 0.5rem 0 0;
  color: #888;
  font-size: 0.75rem;
  line-height: 1.45;
}

.hidden {
  display: none;
}
</style>
