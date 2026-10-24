<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import ProductCard from '../components/ProductCard.vue'
import SubPageNav from '../components/SubPageNav.vue'
import { api } from '../api/client'
import type { ProductCard as Card } from '../api/catalog'
import { hydrateFavorites, isLoggedIn } from '../composables/useFavorites'

type Row = Card & { favorited_at?: string; invalid?: boolean }

const { t } = useI18n()
const router = useRouter()
const items = ref<Row[]>([])
const loaded = ref(false)

async function load() {
  if (!isLoggedIn()) {
    router.replace({ name: 'login', query: { redirect: '/wishlist' } })
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
    <p v-if="loaded && !items.length" class="empty">{{ t('account.wishlistEmpty') }}</p>
    <div v-else class="grid">
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
.empty {
  text-align: center;
  color: #999;
  padding: 2rem;
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
</style>
