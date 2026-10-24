<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ProductCard from '../components/ProductCard.vue'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchProducts, type ProductCard as Card } from '../api/catalog'

const { t } = useI18n()
const keyword = ref('')
const list = ref<Card[]>([])

async function search() {
  const q = keyword.value.trim()
  const data = await fetchProducts(q ? { q } : {})
  list.value = data.items
}

watch(keyword, () => {
  window.clearTimeout((search as unknown as { t?: number }).t)
  ;(search as unknown as { t?: number }).t = window.setTimeout(search, 250)
}, { immediate: true })
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('pages.search')" />
    <van-search v-model="keyword" :placeholder="t('pages.searchHint')" shape="round" />
    <p v-if="!list.length" class="empty">{{ t('pages.searchEmpty') }}</p>
    <div v-else class="grid">
      <ProductCard v-for="p in list" :key="p.id" :product="p" />
    </div>
  </div>
</template>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  padding: 0.5rem 0.75rem 1.5rem;
}
.empty {
  text-align: center;
  color: #999;
  padding: 2.5rem 1rem;
}
</style>
