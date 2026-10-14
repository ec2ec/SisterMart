<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ProductCard from '../components/ProductCard.vue'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchProducts, type ProductCard as Card } from '../api/catalog'
import { isFavorite } from '../composables/useFavorites'

const { t } = useI18n()
const all = ref<Card[]>([])
onMounted(async () => {
  const data = await fetchProducts()
  all.value = data.items
})
const list = computed(() => all.value.filter((p) => isFavorite(p.id)))
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('pages.wishlist')" />
    <div class="grid">
      <ProductCard v-for="p in list" :key="p.id" :product="p" />
    </div>
  </div>
</template>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem 1.5rem;
}
</style>
