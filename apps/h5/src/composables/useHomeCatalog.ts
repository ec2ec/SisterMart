import { ref } from 'vue'
import { fetchCategories, fetchProducts, type CategoryNode, type ProductCard } from '../api/catalog'

/** Display buffer A — survives HomeView remount so back-navigation does not blank the page. */
const displayProducts = ref<ProductCard[]>([])
const displayCategories = ref<CategoryNode[]>([])

let inflight: Promise<void> | null = null

/**
 * Stale-while-revalidate for home catalog.
 * Template binds A (display*). Fetch writes B, then assigns B → A when ready.
 */
export function useHomeCatalog() {
  async function refresh() {
    if (inflight) return inflight
    inflight = (async () => {
      try {
        const [list, cats] = await Promise.all([fetchProducts(), fetchCategories()])
        // B ready → swap into A (single assignment; no intermediate clear)
        displayProducts.value = list.items
        displayCategories.value = cats
      } finally {
        inflight = null
      }
    })()
    return inflight
  }

  return {
    products: displayProducts,
    categories: displayCategories,
    refresh,
  }
}
