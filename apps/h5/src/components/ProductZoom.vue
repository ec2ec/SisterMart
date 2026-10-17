<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  images: string[]
  start: number
}>()

const emit = defineEmits<{ close: [] }>()
const { t } = useI18n()

const index = ref(props.start)
const scale = ref(1)
const panX = ref(0)
const panY = ref(0)

let startX = 0
let startY = 0
let moved = 0
let pinchBase = 1
let pinchDist = 0
let lastTap = 0

watch(
  () => props.start,
  (value) => {
    index.value = value
    resetZoom()
  },
)

function resetZoom() {
  scale.value = 1
  panX.value = 0
  panY.value = 0
}

function clampIndex(next: number) {
  index.value = Math.min(props.images.length - 1, Math.max(0, next))
  resetZoom()
}

function toggleZoom() {
  if (scale.value > 1) resetZoom()
  else scale.value = 2.5
}

function distance(a: Touch, b: Touch) {
  return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY)
}

function onStart(event: TouchEvent) {
  if (event.touches.length === 2) {
    pinchDist = distance(event.touches[0], event.touches[1])
    pinchBase = scale.value
    return
  }
  const touch = event.touches[0]
  startX = touch.clientX
  startY = touch.clientY
  moved = 0
}

function onMove(event: TouchEvent) {
  if (event.touches.length === 2 && pinchDist) {
    const next = pinchBase * (distance(event.touches[0], event.touches[1]) / pinchDist)
    scale.value = Math.min(4, Math.max(1, next))
    if (scale.value === 1) {
      panX.value = 0
      panY.value = 0
    }
    return
  }
  const touch = event.touches[0]
  const dx = touch.clientX - startX
  const dy = touch.clientY - startY
  moved = Math.max(moved, Math.hypot(dx, dy))
  if (scale.value > 1) {
    panX.value = dx
    panY.value = dy
  }
}

function onEnd(event: TouchEvent) {
  if (event.touches.length > 0) return
  const dx = (event.changedTouches[0]?.clientX || startX) - startX
  if (scale.value === 1 && Math.abs(dx) > 48 && moved > 48) {
    clampIndex(index.value + (dx < 0 ? 1 : -1))
    return
  }
  if (moved < 12) {
    const now = Date.now()
    if (now - lastTap < 280) toggleZoom()
    lastTap = now
  }
  pinchDist = 0
}
</script>

<template>
  <div class="viewer" @touchstart="onStart" @touchmove.prevent="onMove" @touchend="onEnd">
    <div class="bar">
      <button type="button" @click="emit('close')">✕</button>
      <b>{{ index + 1 }} / {{ images.length }}</b>
      <span />
    </div>
    <div class="stage" @dblclick="toggleZoom">
      <img
        :src="images[index]"
        alt=""
        :style="{ transform: `translate(${panX}px, ${panY}px) scale(${scale})` }"
      />
    </div>
    <p>{{ t('pdp.swipeHint') }}</p>
  </div>
</template>

<style scoped>
.viewer {
  position: fixed;
  inset: 0;
  z-index: 300;
  background: #111;
  color: #fff;
  display: flex;
  flex-direction: column;
}

.bar {
  display: grid;
  grid-template-columns: 2.5rem 1fr 2.5rem;
  align-items: center;
  min-height: 2.75rem;
  padding: calc(0.5rem + env(safe-area-inset-top, 0rem)) 0.75rem 0.5rem;
}

.bar button {
  border: 0;
  background: transparent;
  color: #fff;
  font-size: 1.25rem;
  padding: 0;
}

.bar b {
  text-align: center;
  font-size: 0.875rem;
}

.stage {
  flex: 1;
  display: grid;
  place-items: center;
  overflow: hidden;
  touch-action: none;
}

.stage img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  transform-origin: center center;
}

p {
  margin: 0;
  padding: 0.75rem 0.75rem calc(1rem + env(safe-area-inset-bottom, 0rem));
  text-align: center;
  color: #bbb;
  font-size: 0.75rem;
}
</style>
