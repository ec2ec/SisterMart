<script setup lang="ts">
import { showError } from '../feedback'
import { computed, ref } from 'vue'
import { NButton, NImage, NSpin, useMessage } from 'naive-ui'
import { uploadImage } from '../api'
import { assetUrl, toStoredUrl } from '../utils/assetUrl'
import MediaPickerDialog from './MediaPickerDialog.vue'

const props = defineProps<{
  modelValue: string | null | undefined
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string | null]
}>()

const message = useMessage()
const busy = ref(false)
const libraryOpen = ref(false)
const inputRef = ref<HTMLInputElement | null>(null)

const url = computed(() => (props.modelValue || '').trim())
const preview = computed(() => (url.value ? assetUrl(url.value) : ''))

function setUrl(next: string | null) {
  emit('update:modelValue', next && next.trim() ? toStoredUrl(next.trim()) : null)
}

function openPicker() {
  if (busy.value) return
  inputRef.value?.click()
}

function clear() {
  setUrl(null)
}

async function onFiles(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  if (!/^image\//i.test(file.type) && !/\.(jpe?g|png|gif|webp)$/i.test(file.name)) {
    message.warning('請上傳圖片檔')
    return
  }
  busy.value = true
  try {
    const data = await uploadImage(file, { scene: 'catalog.product' })
    setUrl(data.url)
    message.success('已上傳 SKU 圖')
  } catch (e: unknown) {
    showError(e instanceof Error ? e.message : '上傳失敗')
  } finally {
    busy.value = false
  }
}

function onLibrarySelect(picked: string) {
  setUrl(picked)
  message.success('已選用素材')
}
</script>

<template>
  <div class="sku-img">
    <n-spin :show="busy" size="small">
      <div class="row">
        <n-image
          v-if="preview"
          class="thumb"
          :src="preview"
          object-fit="cover"
          :preview-src="preview"
        />
        <div v-else class="thumb empty" aria-hidden="true" />
        <div class="actions">
          <n-button size="tiny" :disabled="busy" @click="openPicker">上傳</n-button>
          <n-button size="tiny" :disabled="busy" @click="libraryOpen = true">圖庫</n-button>
          <n-button v-if="url" size="tiny" quaternary :disabled="busy" @click="clear">清除</n-button>
        </div>
      </div>
    </n-spin>
    <input
      ref="inputRef"
      type="file"
      accept="image/jpeg,image/png,image/webp,image/gif"
      hidden
      @change="onFiles"
    />
    <MediaPickerDialog v-model:show="libraryOpen" scene="catalog.product" @select="onLibrarySelect" />
  </div>
</template>

<style scoped>
.sku-img {
  min-width: 7.5rem;
}
.row {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}
.thumb {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.25rem;
  flex-shrink: 0;
  overflow: hidden;
  border: 1px solid var(--n-border-color, #e0e0e6);
}
.thumb :deep(img) {
  width: 2.5rem;
  height: 2.5rem;
  object-fit: cover;
}
.thumb.empty {
  background: #f5f5f7;
}
.actions {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  align-items: flex-start;
}
</style>
