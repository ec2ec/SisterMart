<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import ProductCard from '../components/ProductCard.vue'
import SubPageNav from '../components/SubPageNav.vue'
import { allProducts } from '../mock/data'

const { t } = useI18n()
const list = allProducts.filter((p) => p.liked)
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
