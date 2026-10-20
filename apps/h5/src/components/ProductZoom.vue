<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  images: string[]
  start: number
}>()

const emit = defineEmits<{ close: []; change: [index: number] }>()
const { t } = useI18n()

const index = ref(Math.min(Math.max(0, props.start), Math.max(0, props.images.length - 1)))
const scale = ref(1)
const panX = ref(0)
const panY = ref(0)
/** 未放大時跟手橫滑位移 */
const dragX = ref(0)
const animating = ref(false)

let startX = 0
let startY = 0
let originPanX = 0
let originPanY = 0
let moved = 0
let axis: 'x' | 'y' | '' = ''
let pinchBase = 1
let pinchDist = 0
let lastTap = 0

watch(
  () => props.start,
  (value) => {
    index.value = Math.min(Math.max(0, value), Math.max(0, props.images.length - 1))
    resetZoom()
    dragX.value = 0
  },
  { immediate: true },
)

function resetZoom() {
  scale.value = 1
  panX.value = 0
  panY.value = 0
}

function goTo(next: number) {
  const clamped = Math.min(props.images.length - 1, Math.max(0, next))
  if (clamped === index.value) {
    dragX.value = 0
    return
  }
  index.value = clamped
  resetZoom()
  dragX.value = 0
  emit('change', clamped)
}

function toggleZoom() {
  if (scale.value > 1) resetZoom()
  else scale.value = 2.5
}

function distance(a: Touch, b: Touch) {
  return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY)
}

function onStart(event: TouchEvent) {
  if (animating.value) return
  dragging.value = true
  if (event.touches.length === 2) {
    pinchDist = distance(event.touches[0], event.touches[1])
    pinchBase = scale.value
    axis = ''
    return
  }
  const touch = event.touches[0]
  startX = touch.clientX
  startY = touch.clientY
  originPanX = panX.value
  originPanY = panY.value
  moved = 0
  axis = ''
}

function onMove(event: TouchEvent) {
  if (animating.value) return
  if (event.touches.length === 2 && pinchDist) {
    const next = pinchBase * (distance(event.touches[0], event.touches[1]) / pinchDist)
    scale.value = Math.min(4, Math.max(1, next))
    if (scale.value === 1) {
      panX.value = 0
      panY.value = 0
      dragX.value = 0
    }
    return
  }
  const touch = event.touches[0]
  if (!touch) return
  const dx = touch.clientX - startX
  const dy = touch.clientY - startY
  moved = Math.max(moved, Math.hypot(dx, dy))

  if (!axis && moved > 8) {
    axis = Math.abs(dx) >= Math.abs(dy) ? 'x' : 'y'
  }

  if (scale.value > 1) {
    panX.value = originPanX + dx
    panY.value = originPanY + dy
    return
  }

  // 未放大：橫向跟手滑動切圖
  if (axis === 'x') {
    const atStart = index.value === 0 && dx > 0
    const atEnd = index.value >= props.images.length - 1 && dx < 0
    dragX.value = atStart || atEnd ? dx * 0.35 : dx
  }
}

function onEnd(event: TouchEvent) {
  if (event.touches.length > 0) return
  pinchDist = 0
  dragging.value = false

  if (scale.value > 1) {
    // 放大態結束：保留平移；輕點雙擊邏輯
    if (moved < 12) {
      const now = Date.now()
      if (now - lastTap < 280) toggleZoom()
      lastTap = now
    }
    axis = ''
    return
  }

  const dx = dragX.value
  const threshold = Math.min(72, (typeof window !== 'undefined' ? window.innerWidth : 320) * 0.18)

  if (axis === 'x' && Math.abs(dx) > threshold) {
    animating.value = true
    const width = typeof window !== 'undefined' ? window.innerWidth : 375
    // 先滑出當前圖，再換頁歸零（跟手 → 慣性收尾）
    dragX.value = dx < 0 ? -width : width
    window.setTimeout(() => {
      goTo(index.value + (dx < 0 ? 1 : -1))
      animating.value = false
    }, 160)
    axis = ''
    return
  }

  // 未過閾值：彈回
  dragX.value = 0

  if (moved < 12) {
    const now = Date.now()
    if (now - lastTap < 280) toggleZoom()
    lastTap = now
  }
  axis = ''
}

const dragging = ref(false)

const imgStyle = computed(() => {
  const x = scale.value > 1 ? panX.value : dragX.value
  const y = scale.value > 1 ? panY.value : 0
  const follow = scale.value === 1 && dragging.value
  return {
    transform: `translate3d(${x}px, ${y}px, 0) scale(${scale.value})`,
    transition: follow ? 'none' : 'transform 0.16s ease-out',
  }
})
</script>

<template>
  <div class="viewer" @touchstart.passive="onStart" @touchmove.prevent="onMove" @touchend="onEnd">
    <div class="bar">
      <button type="button" aria-label="close" @click="emit('close')">✕</button>
      <b>{{ index + 1 }} / {{ images.length }}</b>
      <span />
    </div>
    <div class="stage" @dblclick="toggleZoom">
      <img :src="images[index]" alt="" :style="imgStyle" />
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
  will-change: transform;
  user-select: none;
  -webkit-user-drag: none;
  pointer-events: none;
}

p {
  margin: 0;
  padding: 0.75rem 0.75rem calc(1rem + env(safe-area-inset-bottom, 0rem));
  text-align: center;
  color: #bbb;
  font-size: 0.75rem;
}
</style>
