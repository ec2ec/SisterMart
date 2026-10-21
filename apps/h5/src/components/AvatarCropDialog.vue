<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { showToast } from 'vant'
import { cropSquareCover } from '../utils/imageCompress'

/** 選圖上限 5MB；落庫由服務端再壓縮 */
const MAX_AVATAR_BYTES = 5 * 1024 * 1024

const props = defineProps<{
  show: boolean
  /** 當前頭像 URL，打開彈框時回顯 */
  currentSrc?: string
}>()
const emit = defineEmits<{
  'update:show': [boolean]
  confirm: [Blob]
}>()

const { t } = useI18n()
const file = ref<File | null>(null)
const previewUrl = ref('')
const scale = ref(1)
/** 裁切偏移，範圍 -1～1，對應 cropSquareCover */
const offsetX = ref(0)
const offsetY = ref(0)
const busy = ref(false)
const dragging = ref(false)
const inputRef = ref<HTMLInputElement | null>(null)

let startX = 0
let startY = 0
let originOx = 0
let originOy = 0
let pinchDist = 0
let pinchBase = 1
let moved = 0

const displayUrl = computed(() => previewUrl.value || props.currentSrc || '')
const hasNewFile = computed(() => Boolean(file.value))
const canSave = computed(() => hasNewFile.value && !busy.value)

const imgStyle = computed(() => {
  if (!displayUrl.value) return undefined
  if (!hasNewFile.value) {
    return { transform: 'none' }
  }
  // 跟手：縮放 + 平移（百分比隨 scale 放大可拖範圍）
  const pan = 28 * scale.value
  return {
    transform: `translate3d(${offsetX.value * pan}%, ${offsetY.value * pan}%, 0) scale(${scale.value})`,
    transition: dragging.value ? 'none' : 'transform 0.12s ease-out',
  }
})

watch(
  () => props.show,
  (v) => {
    if (!v) reset()
    else {
      // 打開時只回顯當前頭像，不帶上次選圖狀態
      reset()
    }
  },
)

function reset() {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = ''
  file.value = null
  scale.value = 1
  offsetX.value = 0
  offsetY.value = 0
  busy.value = false
  dragging.value = false
  pinchDist = 0
  moved = 0
}

function close() {
  emit('update:show', false)
}

function pick() {
  inputRef.value?.click()
}

function onFile(ev: Event) {
  const input = ev.target as HTMLInputElement
  const f = input.files?.[0]
  input.value = ''
  if (!f) return
  if (!f.type.startsWith('image/')) {
    showToast(t('account.avatarType'))
    return
  }
  if (f.size > MAX_AVATAR_BYTES) {
    showToast(t('account.avatarTooLarge'))
    return
  }
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  file.value = f
  previewUrl.value = URL.createObjectURL(f)
  scale.value = 1
  offsetX.value = 0
  offsetY.value = 0
}

function distance(a: Touch, b: Touch) {
  return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY)
}

function onStart(event: TouchEvent) {
  if (!hasNewFile.value) return
  moved = 0
  if (event.touches.length === 2) {
    pinchDist = distance(event.touches[0], event.touches[1])
    pinchBase = scale.value
    dragging.value = true
    return
  }
  const touch = event.touches[0]
  if (!touch) return
  dragging.value = true
  startX = touch.clientX
  startY = touch.clientY
  originOx = offsetX.value
  originOy = offsetY.value
}

function onMove(event: TouchEvent) {
  if (!hasNewFile.value || !dragging.value) return
  if (event.touches.length === 2 && pinchDist) {
    const next = pinchBase * (distance(event.touches[0], event.touches[1]) / pinchDist)
    scale.value = Math.min(3, Math.max(1, next))
    return
  }
  const touch = event.touches[0]
  if (!touch) return
  const dx = touch.clientX - startX
  const dy = touch.clientY - startY
  moved = Math.max(moved, Math.hypot(dx, dy))
  // 約半個預覽寬對應 offset ±1
  const unit = 96 * scale.value
  offsetX.value = Math.max(-1, Math.min(1, originOx + dx / unit))
  offsetY.value = Math.max(-1, Math.min(1, originOy + dy / unit))
}

function onEnd() {
  dragging.value = false
  pinchDist = 0
}

function onPreviewClick() {
  // 已選新圖且剛拖過：不打開相冊；無圖或僅回顯：點擊選圖
  if (hasNewFile.value && moved > 8) return
  if (hasNewFile.value) return
  pick()
}

async function confirm() {
  if (!file.value) {
    showToast(t('account.avatarPick'))
    return
  }
  busy.value = true
  try {
    const blob = await cropSquareCover(file.value, {
      size: 512,
      quality: 0.85,
      scale: scale.value,
      offsetX: offsetX.value,
      offsetY: offsetY.value,
    })
    emit('confirm', blob)
    close()
  } catch {
    showToast(t('account.avatarFail'))
  } finally {
    busy.value = false
  }
}

onBeforeUnmount(reset)
</script>

<template>
  <van-popup :show="show" position="bottom" round @update:show="emit('update:show', $event)">
    <div class="sheet">
      <h3>{{ t('account.avatarTitle') }}</h3>
      <input ref="inputRef" type="file" accept="image/*" hidden @change="onFile" />
      <div
        class="preview"
        :class="{ editable: hasNewFile }"
        @click="onPreviewClick"
        @touchstart.passive="onStart"
        @touchmove.prevent="onMove"
        @touchend="onEnd"
        @touchcancel="onEnd"
      >
        <img v-if="displayUrl" :src="displayUrl" alt="" draggable="false" :style="imgStyle" />
        <span v-else>{{ t('account.avatarPick') }}</span>
      </div>
      <p v-if="hasNewFile" class="hint">{{ t('account.avatarAdjustHint') }}</p>
      <div v-if="hasNewFile" class="zoom">
        <span>{{ t('account.avatarZoom') }}</span>
        <input v-model.number="scale" type="range" min="1" max="3" step="0.05" />
      </div>
      <div class="actions">
        <van-button block @click="pick">{{ t('account.avatarPick') }}</van-button>
        <van-button
          block
          type="primary"
          color="var(--sm-primary)"
          :loading="busy"
          :disabled="!canSave"
          @click="confirm"
        >
          {{ t('account.avatarSave') }}
        </van-button>
        <van-button block plain @click="close">{{ t('pages.back') }}</van-button>
      </div>
    </div>
  </van-popup>
</template>

<style scoped>
.sheet {
  padding: 1rem 1rem 1.5rem;
}
.sheet h3 {
  margin: 0 0 0.75rem;
  font-size: 1rem;
  text-align: center;
}
.preview {
  width: 12rem;
  height: 12rem;
  margin: 0 auto 0.5rem;
  border-radius: 50%;
  overflow: hidden;
  background: #f0ebe3;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #999;
  font-size: 0.8125rem;
  touch-action: none;
  user-select: none;
  -webkit-user-select: none;
}
.preview.editable {
  cursor: grab;
}
.preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transform-origin: center center;
  pointer-events: none;
  will-change: transform;
}
.hint {
  margin: 0 0 0.5rem;
  text-align: center;
  font-size: 0.75rem;
  color: #999;
}
.zoom {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  font-size: 0.75rem;
  color: #666;
}
.zoom input {
  flex: 1;
}
.actions {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
</style>
