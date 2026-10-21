<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  images: string[]
  start: number
}>()

const emit = defineEmits<{ close: []; change: [index: number] }>()
const { t } = useI18n()

const index = ref(0)
const scale = ref(1)
const panX = ref(0)
const panY = ref(0)
/** 未放大時相對當前頁的跟手偏移（px） */
const dragX = ref(0)
const dragging = ref(false)
const animating = ref(false)
/** 換頁瞬間關閉過渡，避免 Vue :style 蓋掉 DOM 上手寫的 transition:none */
const skipTransition = ref(false)
const stageW = ref(typeof window !== 'undefined' ? window.innerWidth : 375)

let startX = 0
let startY = 0
let originPanX = 0
let originPanY = 0
let moved = 0
let axis: 'x' | 'y' | '' = ''
let pinchBase = 1
let pinchDist = 0
let lastTap = 0
let animTimer = 0

const stageRef = ref<HTMLElement | null>(null)

function clampIndex(n: number) {
  return Math.min(props.images.length - 1, Math.max(0, n))
}

function measure() {
  stageW.value = stageRef.value?.clientWidth || window.innerWidth || 375
}

function resetZoom() {
  scale.value = 1
  panX.value = 0
  panY.value = 0
}

function syncIndex(next: number, notify = true) {
  const clamped = clampIndex(next)
  index.value = clamped
  resetZoom()
  dragX.value = 0
  if (notify) emit('change', clamped)
}

watch(
  () => [props.start, props.images.length] as const,
  () => {
    syncIndex(props.start, false)
  },
  { immediate: true },
)

onMounted(() => {
  measure()
  window.addEventListener('resize', measure)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', measure)
  if (animTimer) window.clearTimeout(animTimer)
})

function toggleZoom() {
  if (scale.value > 1) resetZoom()
  else scale.value = 2.5
}

function distance(a: Touch, b: Touch) {
  return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY)
}

function onStart(event: TouchEvent) {
  if (animating.value || skipTransition.value) return
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
  if (animating.value || skipTransition.value) return
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
  if (!touch) return
  const dx = touch.clientX - startX
  const dy = touch.clientY - startY
  moved = Math.max(moved, Math.hypot(dx, dy))

  if (!axis && moved > 8) {
    axis = Math.abs(dx) >= Math.abs(dy) ? 'x' : 'y'
  }

  // 放大：只平移當前圖，不切頁
  if (scale.value > 1) {
    panX.value = originPanX + dx
    panY.value = originPanY + dy
    return
  }

  if (axis === 'x') {
    const atStart = index.value === 0 && dx > 0
    const atEnd = index.value >= props.images.length - 1 && dx < 0
    dragX.value = atStart || atEnd ? dx * 0.35 : dx
  }
}

function finishSwipe(dir: -1 | 1) {
  const w = stageW.value || window.innerWidth
  const target = index.value + dir
  if (target < 0 || target >= props.images.length) {
    dragX.value = 0
    animating.value = false
    return
  }
  animating.value = true
  dragging.value = false
  // 軌道滑到相鄰頁中心：-index*w ± w == -target*w
  dragX.value = dir > 0 ? -w : w
  if (animTimer) window.clearTimeout(animTimer)
  animTimer = window.setTimeout(() => {
    // 與 syncIndex 同幀：transform 已在目標位 + transition none，無二次滑入
    skipTransition.value = true
    syncIndex(target, true)
    void nextTick(() => {
      void stageRef.value?.offsetHeight
      skipTransition.value = false
      animating.value = false
    })
  }, 220)
}

function onEnd(event: TouchEvent) {
  if (event.touches.length > 0) return
  pinchDist = 0
  dragging.value = false

  if (scale.value > 1) {
    if (moved < 12) {
      const now = Date.now()
      if (now - lastTap < 280) toggleZoom()
      lastTap = now
    }
    axis = ''
    return
  }

  const dx = dragX.value
  const threshold = Math.min(64, stageW.value * 0.18)

  if (axis === 'x' && Math.abs(dx) > threshold) {
    finishSwipe(dx < 0 ? 1 : -1)
    axis = ''
    return
  }

  // 未過閾值：彈回當前頁
  if (dx !== 0) {
    animating.value = true
    dragX.value = 0
    if (animTimer) window.clearTimeout(animTimer)
    animTimer = window.setTimeout(() => {
      animating.value = false
    }, 220)
  }

  if (moved < 12) {
    const now = Date.now()
    if (now - lastTap < 280) toggleZoom()
    lastTap = now
  }
  axis = ''
}

/** 軌道位移：整頁寬 * index + 跟手偏移 */
const trackStyle = computed(() => {
  const w = stageW.value
  const x = -index.value * w + dragX.value
  const follow = scale.value === 1 && dragging.value && !animating.value
  return {
    width: `${Math.max(1, props.images.length) * w}px`,
    transform: `translate3d(${x}px, 0, 0)`,
    transition:
      skipTransition.value || follow ? 'none' : 'transform 0.22s cubic-bezier(0.25, 0.8, 0.25, 1)',
  }
})

function slideBoxStyle() {
  return { width: `${stageW.value}px` }
}

function imgStyle(i: number) {
  const active = i === index.value
  const zooming = active && scale.value > 1
  return {
    transform: zooming ? `translate3d(${panX.value}px, ${panY.value}px, 0) scale(${scale.value})` : undefined,
    transition: zooming && dragging.value ? 'none' : zooming ? 'transform 0.16s ease-out' : undefined,
  }
}
</script>

<template>
  <div class="viewer" @touchstart.passive="onStart" @touchmove.prevent="onMove" @touchend="onEnd">
    <div class="bar">
      <button type="button" aria-label="close" @click="emit('close')">✕</button>
      <b>{{ index + 1 }} / {{ images.length }}</b>
      <span />
    </div>
    <div ref="stageRef" class="stage" @dblclick="toggleZoom">
      <div class="track" :style="trackStyle">
        <div v-for="(src, i) in images" :key="`${src}-${i}`" class="slide" :style="slideBoxStyle()">
          <img :src="src" alt="" :style="imgStyle(i)" />
        </div>
      </div>
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
  overflow: hidden;
  touch-action: none;
  position: relative;
}

.track {
  height: 100%;
  display: flex;
  flex-direction: row;
  will-change: transform;
}

.slide {
  height: 100%;
  flex: 0 0 auto;
  display: grid;
  place-items: center;
  overflow: hidden;
}

.slide img {
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
