<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ProductCard from './ProductCard.vue'
import { fetchHomeRecommendations, type ProductCard as Card } from '../api/catalog'
import {
  clearRecommendFeedCache,
  getRecommendFeedCache,
  saveRecommendFeedCache,
} from '../composables/useRecommendFeedCache'

const props = withDefaults(
  defineProps<{
    /** 標題；默認「為你推薦」 */
    title?: string
    /** 排除的商品 id（如購物車／心願清單已有） */
    excludeIds?: string[]
    /** 每頁條數 */
    pageSize?: number
  }>(),
  {
    excludeIds: () => [],
    pageSize: 30,
  },
)

const { t } = useI18n()
/** 緩存中的原始列表（未按頁面排除） */
const items = ref<Card[]>([])
const offset = ref(0)
const total = ref(0)
const loading = ref(false)
const error = ref(false)
const busy = ref(false)
const initial = ref(true)
let seq = 0

const titleText = computed(() => props.title || t('account.wishlistRecommend'))
const excludeSet = computed(() => new Set(props.excludeIds || []))
const displayItems = computed(() => {
  const ex = excludeSet.value
  if (!ex.size) return items.value
  return items.value.filter((p) => !ex.has(p.id))
})
const showSkeleton = computed(() => initial.value && busy.value && !items.value.length)
const finished = computed(
  () => total.value === 0 && !busy.value && displayItems.value.length === 0 && !initial.value,
)

function restoreFromCache(): boolean {
  const cache = getRecommendFeedCache()
  if (!cache || !cache.items.length) return false
  items.value = cache.items
  offset.value = cache.nextOffset
  total.value = cache.total
  initial.value = false
  busy.value = false
  loading.value = false
  error.value = false
  return true
}

async function fetchBatch(pageOffset: number): Promise<{
  batch: Card[]
  nextOffset: number
  total: number
}> {
  const data = await fetchHomeRecommendations('hot', props.pageSize, pageOffset)
  return {
    batch: data.items || [],
    nextOffset: Number(data.next_offset ?? pageOffset + (data.items || []).length),
    total: Number(data.total || 0),
  }
}

async function loadPage(reset: boolean) {
  const my = ++seq

  if (reset) {
    if (restoreFromCache()) return
    offset.value = Math.floor(Math.random() * 500)
    error.value = false
  }

  const pageOffset = offset.value
  busy.value = true
  loading.value = true
  try {
    let { batch, nextOffset, total: tot } = await fetchBatch(pageOffset)
    if (my !== seq) return
    total.value = tot
    offset.value = nextOffset

    const seen = new Set(reset ? [] : items.value.map((p) => p.id))
    const merged: Card[] = reset ? [] : items.value.slice()
    for (const p of batch) {
      if (seen.has(p.id)) continue
      merged.push(p)
      seen.add(p.id)
    }
    // 本頁排除後展示偏少時再補一頁進緩存
    const visibleAfter = merged.filter((p) => !excludeSet.value.has(p.id)).length
    const prevVisible = reset
      ? 0
      : items.value.filter((p) => !excludeSet.value.has(p.id)).length
    if (visibleAfter - prevVisible < props.pageSize && excludeSet.value.size > 0) {
      const more = await fetchBatch(offset.value)
      if (my !== seq) return
      offset.value = more.nextOffset
      total.value = more.total || total.value
      for (const p of more.batch) {
        if (seen.has(p.id)) continue
        merged.push(p)
        seen.add(p.id)
      }
    }

    items.value = merged
    saveRecommendFeedCache({
      items: merged,
      nextOffset: offset.value,
      total: total.value,
      renewTtl: reset,
    })
  } catch {
    if (my !== seq) return
    error.value = true
    if (reset && !items.value.length) items.value = []
  } finally {
    if (my === seq) {
      busy.value = false
      loading.value = false
      initial.value = false
    }
  }
}

async function onLoad() {
  if (busy.value) return
  // 有緩存時首屏已恢復；觸底才追加
  await loadPage(items.value.length === 0)
}

onMounted(() => {
  if (restoreFromCache()) return
  void loadPage(true)
})

function onRefresh() {
  if (busy.value) return
  clearRecommendFeedCache()
  items.value = []
  offset.value = 0
  total.value = 0
  initial.value = true
  error.value = false
  void loadPage(true)
}

defineExpose({ reload: onRefresh })
</script>

<template>
  <section class="rec">
    <div class="rec-hd">
      <h3 class="rec-title">{{ titleText }}</h3>
      <button
        type="button"
        class="rec-refresh sm-icon-btn"
        :aria-label="t('home.feedRefresh')"
        :disabled="busy"
        @click="onRefresh"
      >
        <van-icon name="replay" :class="{ spinning: busy }" />
      </button>
    </div>
    <div v-if="showSkeleton" class="grid" aria-hidden="true">
      <div v-for="n in 6" :key="`sk-${n}`" class="sk-card">
        <div class="sk-media sk-pulse" />
        <div class="sk-meta">
          <span class="sk-line sk-pulse" />
          <span class="sk-line sk-pulse sk-short" />
        </div>
      </div>
    </div>
    <van-list
      v-else
      v-model:loading="loading"
      v-model:error="error"
      :finished="finished"
      :loading-text="t('home.feedLoading')"
      :error-text="t('home.feedLoadFail')"
      :immediate-check="false"
      @load="onLoad"
    >
      <div class="grid">
        <ProductCard v-for="(p, idx) in displayItems" :key="`${p.id}-${idx}`" :product="p" />
      </div>
      <p v-if="!busy && !displayItems.length" class="rec-empty">{{ t('home.feedEmpty') }}</p>
    </van-list>
  </section>
</template>

<style scoped>
.rec {
  padding-top: 0.75rem;
}
.rec-hd {
  display: flex;
  align-items: center;
  gap: 0.15rem;
  padding: 0.5rem 0.75rem 0.25rem;
}
.rec-title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--sm-text);
}
.rec-refresh {
  flex: 0 0 auto;
  width: 2rem;
  height: 2rem;
  border: 0;
  background: transparent;
  color: var(--sm-text-muted);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.rec-refresh:disabled {
  opacity: 0.55;
}
.rec-refresh .spinning {
  animation: rec-spin 0.75s linear infinite;
}
@keyframes rec-spin {
  to {
    transform: rotate(-360deg);
  }
}
.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  padding: 0.5rem 0.75rem 1.5rem;
  position: relative;
  z-index: 0;
}
.rec-empty {
  margin: 0;
  padding: 0.5rem 0.75rem 1.5rem;
  text-align: center;
  color: var(--sm-text-muted);
  font-size: 0.8125rem;
}
.sk-card {
  min-width: 0;
}
.sk-media {
  aspect-ratio: 1 / 1;
  border-radius: 0.625rem;
  background: #eee;
}
.sk-meta {
  padding: 0.5rem 0.125rem 0.25rem;
  display: grid;
  gap: 0.35rem;
}
.sk-line {
  display: block;
  height: 0.75rem;
  border-radius: 0.25rem;
  background: #eee;
}
.sk-short {
  width: 55%;
}
.sk-pulse {
  animation: sk-pulse 1.2s ease-in-out infinite;
}
@keyframes sk-pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.55;
  }
}
</style>
