<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import ProductCard from './ProductCard.vue'
import { fetchCampaignRail, type Localized, type ProductCard as Card } from '../api/catalog'
import { useLocaleText } from '../composables/useLocaleText'

const props = withDefaults(
  defineProps<{
    code: string
    title?: Localized | null
    railLimit?: number
  }>(),
  { title: null, railLimit: 10 },
)

const { t } = useI18n()
const { text } = useLocaleText()
const router = useRouter()
const items = ref<Card[]>([])
const heading = ref<Localized | null>(null)
const loading = ref(true)
const visible = ref(false)

async function load() {
  if (!props.code) {
    visible.value = false
    loading.value = false
    return
  }
  loading.value = true
  try {
    const data = await fetchCampaignRail(props.code)
    items.value = data.items || []
    heading.value = data.campaign?.title || props.title
    visible.value = items.value.length > 0
  } catch {
    items.value = []
    visible.value = false
  } finally {
    loading.value = false
  }
}

function goMore() {
  router.push({ name: 'campaign', params: { code: props.code } })
}

onMounted(load)
watch(() => props.code, load)

defineExpose({ reload: load })
</script>

<template>
  <section v-if="loading || visible" class="camp-rail">
    <div class="section-title">
      <h2>{{ (heading || title) ? text(heading || title!) : t('home.campaignZone') }}</h2>
      <button type="button" class="more sm-pressable" @click="goMore">
        {{ t('home.viewMore') }}
      </button>
    </div>
    <div v-if="loading && !items.length" class="rail" aria-hidden="true">
      <div v-for="n in 3" :key="`sk-${n}`" class="sk-card">
        <div class="sk-media sk-pulse" />
        <div class="sk-meta">
          <span class="sk-line sk-pulse" />
          <span class="sk-line sk-pulse sk-short" />
        </div>
      </div>
    </div>
    <div v-else class="rail">
      <ProductCard v-for="p in items" :key="p.id" :product="p" />
      <button type="button" class="more-card sm-pressable" @click="goMore">
        <span class="more-card-inner">
          <van-icon name="arrow" />
          <span>{{ t('home.viewMoreCampaign') }}</span>
        </span>
      </button>
    </div>
  </section>
</template>

<style scoped>
.camp-rail {
  padding-bottom: 0.125rem;
}
.section-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.75rem 0.875rem 0.35rem;
}
.section-title h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
}
.more {
  border: 0;
  background: transparent;
  color: var(--sm-text-muted);
  font-size: 0.8125rem;
  padding: 0;
}
.rail {
  --rail-gap: 0.75rem;
  /* 左側 = 商品間隔一半：用 margin 內縮整個 scrollport，避免 snap 把 padding 滾出視口貼邊 */
  --rail-pad-left: calc(var(--rail-gap) / 2);
  --rail-pad-right: 0.875rem;
  display: flex;
  gap: var(--rail-gap);
  box-sizing: border-box;
  margin-left: var(--rail-pad-left);
  width: calc(100% - var(--rail-pad-left));
  overflow-x: auto;
  scroll-snap-type: x mandatory;
  padding: 0 var(--rail-pad-right) 0.375rem 0;
  -webkit-overflow-scrolling: touch;
}
.rail :deep(.card) {
  flex: 0 0 calc((100% - 1.5rem) / 3);
  min-width: 0;
  scroll-snap-align: start;
}
.more-card {
  flex: 0 0 calc((100% - 1.5rem) / 3);
  scroll-snap-align: start;
  border: 0;
  border-radius: 0.5rem;
  background: var(--sm-bg-soft);
  min-height: 8rem;
  padding: 0.75rem;
  color: var(--sm-text-muted);
}
.more-card-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  height: 100%;
  font-size: 0.75rem;
  text-align: center;
  line-height: 1.35;
}
.sk-card {
  flex: 0 0 calc((100% - 1.5rem) / 3);
  min-width: 0;
}
.sk-media {
  aspect-ratio: 1;
  border-radius: 0.5rem;
  background: #eee;
}
.sk-meta {
  padding-top: 0.35rem;
  display: grid;
  gap: 0.25rem;
}
.sk-line {
  display: block;
  height: 0.65rem;
  border-radius: 0.2rem;
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
