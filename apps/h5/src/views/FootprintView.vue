<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { showConfirmDialog, showToast } from 'vant'
import ProductCard from '../components/ProductCard.vue'
import SubPageNav from '../components/SubPageNav.vue'
import { api } from '../api/client'
import type { ProductCard as Card } from '../api/catalog'

type Row = Card & { viewed_at?: string; invalid?: boolean }

const { t } = useI18n()
const items = ref<Row[]>([])
const busy = ref(false)

async function load() {
  const data = await api<{ items: Row[] }>('/api/v1/browse-history', {}, 'member')
  items.value = data.items
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
  } catch (e: any) {
    showToast(e?.message || t('footprint.clearFail'))
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('footprint.title')" />
    <div v-if="items.length" class="toolbar">
      <button type="button" class="clear" :disabled="busy" @click="clearAll">
        {{ t('footprint.clear') }}
      </button>
    </div>
    <p v-if="!items.length" class="empty">{{ t('footprint.empty') }}</p>
    <div v-else class="grid">
      <div v-for="p in items" :key="p.id" class="cell">
        <ProductCard :product="p" />
        <span v-if="p.invalid" class="badge">{{ t('footprint.invalid') }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.toolbar {
  display: flex;
  justify-content: flex-end;
  padding: 0 0.75rem 0.25rem;
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
.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem 1.5rem;
}
.cell {
  position: relative;
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
