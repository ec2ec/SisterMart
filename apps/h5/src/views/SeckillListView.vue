<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchSeckillSessions, type SeckillSession } from '../api/m7'
import { useLocaleText } from '../composables/useLocaleText'
import { assetUrl } from '../utils/assetUrl'

const { t } = useI18n()
const { text } = useLocaleText()
const router = useRouter()
const sessions = ref<SeckillSession[]>([])
const loading = ref(true)
const loadError = ref('')

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const data = await fetchSeckillSessions()
    sessions.value = data.items || []
  } catch (e) {
    sessions.value = []
    loadError.value = e instanceof Error && e.message ? e.message : t('m7.loadFail')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})

function statusLabel(status: string) {
  if (status === 'live') return t('m7.live')
  if (status === 'upcoming') return t('m7.upcoming')
  return t('m7.ended')
}

function openProduct(s: SeckillSession, p: SeckillSession['products'][number]) {
  router.push({
    name: 'seckill-detail',
    params: { id: s.id },
    query: { product: p.product_id, seckill_item_id: String(p.seckill_item_id) },
  })
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('m7.seckill')" />
    <p v-if="loading" class="hint">{{ t('m7.loading') }}</p>
    <p v-else-if="loadError" class="hint err">{{ loadError }}</p>
    <p v-else-if="!sessions.length" class="hint">{{ t('m7.empty') }}</p>
    <section v-for="s in sessions" :key="s.id" class="session">
      <div class="head">
        <h2>{{ text(s.name) }}</h2>
        <span :class="s.status">{{ statusLabel(s.status) }}</span>
      </div>
      <p class="time">{{ s.starts_at }} → {{ s.ends_at }}</p>
      <p v-if="!s.products?.length" class="hint tiny">{{ t('m7.noProducts') }}</p>
      <div v-else class="row">
        <button
          v-for="p in s.products"
          :key="p.id"
          type="button"
          class="card"
          @click="openProduct(s, p)"
        >
          <img :src="assetUrl(p.image)" alt="" />
          <strong>{{ text(p.name) }}</strong>
          <span class="price">HK$ {{ p.seckill_price }} <s>HK$ {{ p.origin_price }}</s></span>
          <span class="left">{{ t('pdp.stock', { n: p.stock }) }}</span>
        </button>
      </div>
    </section>
  </div>
</template>

<style scoped>
.session {
  margin: 0.75rem 1rem 1.25rem;
  padding: 0.75rem;
  border-radius: var(--sm-radius);
  background: linear-gradient(135deg, #f7edf9 0%, #c3d9fb 100%);
}
.head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
}
.head h2 {
  margin: 0;
  font-size: 1rem;
}
.head span {
  font-size: 0.75rem;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  flex-shrink: 0;
}
.head .live {
  background: #ffe8e0;
  color: #c45c26;
}
.head .upcoming,
.head .ended {
  background: rgba(255, 255, 255, 0.75);
  color: #666;
}
.time {
  margin: 0.25rem 0 0.75rem;
  font-size: 0.75rem;
  color: #666;
}
.row {
  display: flex;
  gap: 0.75rem;
  overflow-x: auto;
  padding-bottom: 0.25rem;
}
.card {
  flex: 0 0 8.5rem;
  min-width: 0;
  border: 0;
  background: rgba(255, 255, 255, 0.92);
  text-align: left;
  padding: 0.4rem;
  border-radius: 0.5rem;
}
.card img {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 0.4rem;
  display: block;
  background: #f3f0ea;
}
.card strong {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  font-size: 0.8125rem;
  line-height: 1.35;
  margin-top: 0.35rem;
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
}
.price {
  font-size: 0.75rem;
  color: var(--sm-accent);
  font-weight: 600;
}
.price s {
  color: #aaa;
  margin-left: 0.25rem;
  font-weight: 400;
}
.left {
  display: block;
  font-size: 0.6875rem;
  color: #999;
  margin-top: 0.15rem;
}
.hint {
  text-align: center;
  color: #999;
  padding: 2rem 1rem;
  font-size: 0.875rem;
}
.hint.err {
  color: var(--sm-danger);
}
.hint.tiny {
  padding: 0.5rem 0;
  text-align: left;
}
</style>
