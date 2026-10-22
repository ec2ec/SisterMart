<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import ProductCard from '../components/ProductCard.vue'
import SubPageNav from '../components/SubPageNav.vue'
import {
  fetchCategories,
  fetchHomeSectionProducts,
  fetchProducts,
  type CategoryNode,
  type Localized,
  type ProductCard as Card,
} from '../api/catalog'
import { useLocaleText } from '../composables/useLocaleText'

const { t, locale } = useI18n()
const { text } = useLocaleText()
const route = useRoute()
const list = ref<Card[]>([])
const title = ref(t('pages.productList'))

function findCategoryName(nodes: CategoryNode[], code: string): Localized | null {
  for (const node of nodes) {
    if (node.code === code) return node.name
    const child = findCategoryName(node.children || [], code)
    if (child) return child
  }
  return null
}

async function resolveCategoryTitle(category: string): Promise<string> {
  if (!category) return t('pages.productList')
  try {
    const nodes = await fetchCategories()
    const name = findCategoryName(nodes, category)
    if (name) {
      const label = text(name)
      if (label) return label
    }
  } catch {
    /* 標題回退為通用列表名 */
  }
  return t('pages.productList')
}

async function load() {
  const category = String(route.query.category || '')
  const section = String(route.query.section || '')
  if (section) {
    const data = await fetchHomeSectionProducts(section)
    list.value = data.items
    title.value = text(data.section.title) || section
    return
  }
  const data = await fetchProducts(category ? { category } : {})
  list.value = data.items
  title.value = await resolveCategoryTitle(category)
}

onMounted(load)
watch(() => [route.query.category, route.query.section, locale.value], load)
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
