<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import ProductCard from '../components/ProductCard.vue'
import SubPageNav from '../components/SubPageNav.vue'
import { allProducts, categories } from '../mock/data'
import { useLocaleText } from '../composables/useLocaleText'

const { t } = useI18n()
const { text } = useLocaleText()
const route = useRoute()

const categoryId = computed(() => String(route.query.category || ''))
const title = computed(() => {
  const cat = categories.find((c) => c.id === categoryId.value)
  return cat ? text(cat.name) : t('pages.productList')
})
const list = computed(() => {
  if (!categoryId.value) return allProducts
  const matched = allProducts.filter((p) => p.category === categoryId.value)
  return matched.length ? matched : allProducts
})
</script>

<template>
  <div class="page">
    <SubPageNav :title="title" />
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
