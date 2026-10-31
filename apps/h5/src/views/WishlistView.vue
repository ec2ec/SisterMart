<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import ProductCard from '../components/ProductCard.vue'
import RecommendFeed from '../components/RecommendFeed.vue'
import SubPageNav from '../components/SubPageNav.vue'
import { api } from '../api/client'
import type { ProductCard as Card } from '../api/catalog'
import { goLogin } from '../composables/useAuth'
import { hydrateFavorites, isLoggedIn } from '../composables/useFavorites'

type Row = Card & { favorited_at?: string; invalid?: boolean }

const { t } = useI18n()
const router = useRouter()
const items = ref<Row[]>([])
const loaded = ref(false)

const isEmpty = computed(() => loaded.value && items.value.length === 0)
const favIds = computed(() => items.value.map((p) => p.id))

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
}

onMounted(load)
</script>

<template>
  <div class="page wishlist-page">
    <SubPageNav :title="t('pages.wishlist')" />

    <div v-if="isEmpty" class="empty-block">
      <h2 class="empty-title">{{ t('account.wishlistEmpty') }}</h2>
      <p class="empty-hint">{{ t('account.wishlistEmptyHint') }}</p>
    </div>

    <div v-else-if="loaded" class="grid">
      <div v-for="p in items" :key="p.id" class="cell">
        <ProductCard :product="p" />
        <span v-if="p.invalid" class="badge">{{ t('footprint.invalid') }}</span>
      </div>
    </div>

    <RecommendFeed v-if="loaded" :exclude-ids="favIds" />
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

.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  padding: 0.5rem 0.75rem 0.75rem;
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
</style>
