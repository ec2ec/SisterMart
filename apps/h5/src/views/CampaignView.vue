<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import ProductCard from '../components/ProductCard.vue'
import SubPageNav from '../components/SubPageNav.vue'
import {
  fetchCampaign,
  fetchCampaignProducts,
  type CampaignSummary,
  type ProductCard as Card,
} from '../api/catalog'
import { useLocaleText } from '../composables/useLocaleText'
import { assetUrl } from '../utils/assetUrl'

const PAGE = 30
const { t, locale } = useI18n()
const { text } = useLocaleText()
const route = useRoute()

const code = computed(() => String(route.params.code || ''))
const campaign = ref<CampaignSummary | null>(null)
const items = ref<Card[]>([])
const offset = ref(0)
const total = ref(0)
const loading = ref(false)
const listLoading = ref(false)
const listError = ref(false)
const initial = ref(true)
let seq = 0

const title = computed(() =>
  campaign.value?.title ? text(campaign.value.title) : t('home.campaignZone'),
)
const bannerUrl = computed(() => {
  const u = campaign.value?.banner_image_url || ''
  return u ? assetUrl(u) : ''
})
const introHtml = computed(() => {
  const html = campaign.value?.intro_html
  if (!html) return ''
  const loc = String(locale.value || 'zh-HK')
  return loc.startsWith('en') ? html.en || html['zh-HK'] : html['zh-HK'] || html.en
})
const finished = computed(() => !initial.value && offset.value >= total.value && !loading.value)
const showSkeleton = computed(() => initial.value && loading.value && !items.value.length)

async function loadMeta() {
  if (!code.value) return
  try {
    const data = await fetchCampaign(code.value)
    campaign.value = data.campaign
  } catch {
    campaign.value = null
  }
}

async function loadPage(reset: boolean) {
  if (!code.value) return
  const my = ++seq
  if (reset) {
    offset.value = 0
    listError.value = false
  }
  loading.value = true
  listLoading.value = true
  try {
    const data = await fetchCampaignProducts(code.value, offset.value, PAGE)
    if (my !== seq) return
    campaign.value = data.campaign
    total.value = data.total
    const batch = data.items || []
    items.value = reset ? batch : items.value.concat(batch)
    offset.value = data.next_offset ?? offset.value + batch.length
    if (data.next_offset == null && batch.length) {
      offset.value = total.value
    }
  } catch {
    if (my !== seq) return
    listError.value = true
    if (reset) items.value = []
  } finally {
    if (my === seq) {
      loading.value = false
      listLoading.value = false
      initial.value = false
    }
  }
}

async function onLoad() {
  if (loading.value) return
  await loadPage(items.value.length === 0)
}

async function boot() {
  initial.value = true
  items.value = []
  offset.value = 0
  await loadMeta()
  await loadPage(true)
}

onMounted(boot)
watch(code, boot)
</script>

<template>
  <div class="page camp">
    <SubPageNav :title="title" />

    <div v-if="bannerUrl" class="banner">
      <img :src="bannerUrl" :alt="title" />
    </div>
    <div v-if="introHtml" class="intro" v-html="introHtml" />
    <p v-else-if="campaign" class="intro-fallback">{{ t('home.campaignIntroFallback') }}</p>

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
      v-model:loading="listLoading"
      v-model:error="listError"
      :finished="finished"
      :loading-text="t('home.feedLoading')"
      :error-text="t('home.feedLoadFail')"
      :immediate-check="false"
      @load="onLoad"
    >
      <div class="grid">
        <ProductCard v-for="(p, idx) in items" :key="`${p.id}-${idx}`" :product="p" />
      </div>
      <p v-if="!loading && !items.length" class="empty">{{ t('home.feedEmpty') }}</p>
    </van-list>
  </div>
</template>

<style scoped>
.banner {
  width: 100%;
  aspect-ratio: 16 / 7;
  background: var(--sm-bg-soft);
  overflow: hidden;
}
.banner img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.intro,
.intro-fallback {
  margin: 0;
  padding: 0.875rem 0.875rem 0.25rem;
  font-size: 0.875rem;
  line-height: 1.55;
  color: var(--sm-text);
}
.intro :deep(p) {
  margin: 0 0 0.5rem;
}
.intro-fallback {
  color: var(--sm-text-muted);
}
.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  padding: 0.5rem 0.75rem 1.5rem;
}
.empty {
  text-align: center;
  color: #999;
  font-size: 0.8125rem;
  padding: 1rem 0 2rem;
}
.sk-card {
  min-width: 0;
}
.sk-media {
  aspect-ratio: 1;
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
