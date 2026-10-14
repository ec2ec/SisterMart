<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import ProductCard from '../components/ProductCard.vue'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchProducts, type ProductCard as Card } from '../api/catalog'

const { t } = useI18n()
const route = useRoute()
const list = ref<Card[]>([])
const title = ref(t('pages.productList'))

async function load() {
  const category = String(route.query.category || '')
  const data = await fetchProducts(category ? { category } : {})
  list.value = data.items
  title.value = category || t('pages.productList')
}

onMounted(load)
watch(() => route.query.category, load)
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
