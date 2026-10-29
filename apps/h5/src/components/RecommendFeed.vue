<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ProductCard from './ProductCard.vue'
import { fetchHomeRecommendations, type ProductCard as Card } from '../api/catalog'

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
const showSkeleton = computed(() => initial.value && busy.value && !items.value.length)
const finished = computed(
  () => total.value === 0 && !busy.value && items.value.length === 0 && !initial.value,
)

function filterNew(batch: Card[], seen: Set<string>): Card[] {
  const out: Card[] = []
  for (const p of batch) {
    if (excludeSet.value.has(p.id) || seen.has(p.id)) continue
    out.push(p)
    seen.add(p.id)
  }
  return out
}

async function loadPage(reset: boolean) {
  const my = ++seq
  if (reset) {
    offset.value = Math.floor(Math.random() * 500)
    error.value = false
  }
  const pageOffset = reset ? offset.value : offset.value
  busy.value = true
  loading.value = true
  try {
    const data = await fetchHomeRecommendations('hot', props.pageSize, pageOffset)
    if (my !== seq) return
    const batch = data.items || []
    total.value = Number(data.total || 0)
    offset.value = Number(data.next_offset ?? pageOffset + batch.length)
    const seen = new Set(reset ? [] : items.value.map((p) => p.id))
    let filtered = filterNew(batch, seen)
    // 排除過多時再補一頁，儘量湊滿 pageSize
    if (filtered.length < props.pageSize && excludeSet.value.size > 0) {
      const more = await fetchHomeRecommendations('hot', props.pageSize, offset.value)
      if (my !== seq) return
      offset.value = Number(more.next_offset ?? offset.value + (more.items || []).length)
      filtered = filtered.concat(filterNew(more.items || [], seen))
    }
    const next = filtered.slice(0, props.pageSize)
    items.value = reset ? next : items.value.concat(next)
  } catch {
    if (my !== seq) return
    error.value = true
    if (reset) items.value = []
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
  await loadPage(items.value.length === 0)
}

watch(
  () => (props.excludeIds || []).join(','),
  () => {
    // 排除集變化時不強制重拉，僅影響後續分頁過濾
  },
)

onMounted(() => {
  void loadPage(true)
})

defineExpose({ reload: () => loadPage(true) })
</script>

<template>
  <section class="rec">
    <h3 class="rec-title">{{ titleText }}</h3>
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
        <ProductCard v-for="(p, idx) in items" :key="`${p.id}-${idx}`" :product="p" />
      </div>
      <p v-if="!busy && !items.length" class="rec-empty">{{ t('home.feedEmpty') }}</p>
    </van-list>
  </section>
</template>

<style scoped>
.rec {
  padding-top: 0.75rem;
}
.rec-title {
  margin: 0;
  padding: 0.5rem 0.75rem 0.25rem;
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--sm-text);
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
