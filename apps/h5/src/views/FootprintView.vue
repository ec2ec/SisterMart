<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showConfirmDialog, showToast } from 'vant'
import ProductCard from '../components/ProductCard.vue'
import SubPageNav from '../components/SubPageNav.vue'
import { api, ApiError } from '../api/client'
import type { ProductCard as Card } from '../api/catalog'
import { isLoggedIn, syncSessionFromStorage } from '../composables/useSession'

type Row = Card & { viewed_at?: string; invalid?: boolean }

const { t } = useI18n()
const router = useRouter()
const items = ref<Row[]>([])
const busy = ref(false)
const loaded = ref(false)
const loadError = ref('')

async function load() {
  syncSessionFromStorage()
  if (!isLoggedIn()) {
    router.replace({ name: 'login', query: { redirect: '/footprint' } })
    return
  }
  loadError.value = ''
  try {
    const data = await api<{ items: Row[] }>('/api/v1/browse-history?limit=50', {}, 'member')
    items.value = data.items || []
  } catch (e) {
    items.value = []
    loadError.value = e instanceof ApiError && e.message ? e.message : t('m7.loadFail')
  } finally {
    loaded.value = true
  }
}

async function clearAll() {
  try {
    await showConfirmDialog({ title: t('footprint.clearConfirm') })
  } catch {
    return
  }
  busy.value = true
  try {
    await api('/api/v1/browse-history', { method: 'DELETE' }, 'member')
    items.value = []
    showToast(t('footprint.cleared'))
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : t('footprint.clearFail')
    showToast(msg)
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page footprint-page">
    <SubPageNav :title="t('footprint.title')" />
    <div v-if="items.length" class="toolbar">
      <button type="button" class="clear" :disabled="busy" @click="clearAll">
        {{ t('footprint.clear') }}
      </button>
    </div>
    <p v-if="!loaded" class="empty">{{ t('m7.loading') }}</p>
    <p v-else-if="loadError" class="empty err">{{ loadError }}</p>
    <p v-else-if="!items.length" class="empty">{{ t('footprint.empty') }}</p>
    <div v-else class="grid">
      <div v-for="p in items" :key="p.id" class="cell">
        <ProductCard :product="p" />
        <span v-if="p.invalid" class="badge">{{ t('footprint.invalid') }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.footprint-page {
  min-height: 100%;
  background: #fff;
}
.toolbar {
  display: flex;
  justify-content: flex-end;
  padding: 0 0.75rem 0.25rem;
  position: relative;
  z-index: 0;
}
.clear {
  border: 0;
  background: transparent;
  color: var(--sm-primary);
  font: inherit;
  font-size: 0.8125rem;
  padding: 0.25rem;
}
.empty {
  text-align: center;
  color: #999;
  padding: 2rem;
}
.empty.err {
  color: #c45c26;
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
