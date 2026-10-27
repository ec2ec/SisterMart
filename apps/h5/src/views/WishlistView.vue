<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import ProductCard from '../components/ProductCard.vue'
import SubPageNav from '../components/SubPageNav.vue'
import { api } from '../api/client'
import { fetchHomeRecommendations, type ProductCard as Card } from '../api/catalog'
import { goLogin } from '../composables/useAuth'
import { hydrateFavorites, isLoggedIn } from '../composables/useFavorites'

type Row = Card & { favorited_at?: string; invalid?: boolean }

const REC_LIMIT = 30

const { t } = useI18n()
const router = useRouter()
const items = ref<Row[]>([])
const loaded = ref(false)
const recItems = ref<Card[]>([])
const recBusy = ref(false)

const isEmpty = computed(() => loaded.value && items.value.length === 0)

async function loadRecs() {
  recBusy.value = true
  try {
    // 隨機偏移 + 服務端頁內 shuffle，每次進入空態推薦不同
    const offset = Math.floor(Math.random() * 500)
    const data = await fetchHomeRecommendations('hot', REC_LIMIT, offset)
    recItems.value = data.items || []
  } catch {
    recItems.value = []
  } finally {
    recBusy.value = false
  }
}

async function load() {
  if (!isLoggedIn()) {
    void goLogin(router, '/wishlist', { replace: true })
    return
  }
  try {
    const data = await api<{ items: Row[] }>('/api/v1/favorites', {}, 'member')
    items.value = data.items || []
    await hydrateFavorites()
  } catch {
    items.value = []
  } finally {
    loaded.value = true
  }
  if (items.value.length === 0) {
    void loadRecs()
  } else {
    recItems.value = []
  }
}

onMounted(load)
</script>

<template>
  <div class="page wishlist-page">
    <SubPageNav :title="t('pages.wishlist')" />

    <template v-if="isEmpty">
      <div class="empty-block">
        <h2 class="empty-title">{{ t('account.wishlistEmpty') }}</h2>
        <p class="empty-hint">{{ t('account.wishlistEmptyHint') }}</p>
      </div>

      <section v-if="recBusy || recItems.length" class="rec">
        <h3 class="rec-title">{{ t('account.wishlistRecommend') }}</h3>
        <div v-if="recBusy && !recItems.length" class="grid" aria-hidden="true">
          <div v-for="n in 6" :key="`sk-${n}`" class="sk-card">
            <div class="sk-media sk-pulse" />
            <div class="sk-meta">
              <span class="sk-line sk-pulse" />
              <span class="sk-line sk-pulse sk-short" />
            </div>
          </div>
        </div>
        <div v-else class="grid">
          <ProductCard v-for="p in recItems" :key="p.id" :product="p" />
        </div>
      </section>
    </template>

    <div v-else-if="loaded" class="grid">
      <div v-for="p in items" :key="p.id" class="cell">
        <ProductCard :product="p" />
        <span v-if="p.invalid" class="badge">{{ t('footprint.invalid') }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wishlist-page {
  min-height: 100%;
  background: #fff;
}

.empty-block {
  text-align: center;
  padding: 2.25rem 1.5rem 0.75rem;
}

.empty-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--sm-text);
  line-height: 1.4;
}

.empty-hint {
  margin: 0.5rem 0 0;
  font-size: 0.8125rem;
  color: var(--sm-text-muted);
  line-height: 1.5;
}

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

.cell {
  position: relative;
  z-index: 0;
}

.badge {
  position: absolute;
  top: 0.5rem;
  left: 0.5rem;
  z-index: 2;
  font-size: 0.6875rem;
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  padding: 0.15rem 0.35rem;
  border-radius: 0.2rem;
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
