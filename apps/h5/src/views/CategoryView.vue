<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import { fetchCategories, type CategoryNode } from '../api/catalog'
import { useLocaleText } from '../composables/useLocaleText'

const { text } = useLocaleText()
const router = useRouter()
const active = ref(0)
const categories = ref<CategoryNode[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    categories.value = await fetchCategories()
  } finally {
    loading.value = false
  }
})

const current = computed(() => categories.value[active.value] || categories.value[0])

const gridItems = computed(() => {
  const main = current.value
  if (!main) return []
  // Icons come from admin/API (`categories.icon`); 「全部商品」沿用父類 icon。
  const all = {
    id: -1,
    code: `${main.code}-all`,
    parent_id: main.id,
    icon: main.icon,
    sort: -1,
    name: { 'zh-HK': '全部商品', en: 'All' } as const,
    children: [],
  }
  const children = (main.children || []).map((c) => ({
    ...c,
    icon: c.icon || main.icon,
  }))
  return [all, ...children]
})

function openList(item: CategoryNode | (typeof gridItems.value)[0]) {
  const main = current.value
  if (!main) return
  const category = item.id === -1 ? main.code : item.code
  router.push({ name: 'product-list', query: { category } })
}
</script>

<template>
  <div class="page page--soft category-page">
    <AppHeader />
    <div class="body">
      <van-sidebar v-model="active" class="side">
        <van-sidebar-item v-for="c in categories" :key="c.id" :title="text(c.name)" />
      </van-sidebar>
      <div class="content">
        <p v-if="loading" class="empty">…</p>
        <section v-else-if="current" class="sec">
          <div class="sec-title">
            <span />
            <em>{{ text(current.name) }}</em>
            <span />
          </div>
          <div class="grid">
            <button v-for="item in gridItems" :key="item.code" type="button" class="item" @click="openList(item)">
              <img v-if="item.icon" :src="item.icon" :alt="text(item.name)" />
              <span v-else class="ph" />
              <span>{{ text(item.name) }}</span>
            </button>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.category-page {
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
  padding-top: var(--sm-header-h);
  padding-bottom: calc(var(--sm-tabbar-h) + env(safe-area-inset-bottom, 0rem));
}
.body {
  flex: 1;
  display: flex;
  min-height: 0;
}
.side {
  width: 5.5rem;
  flex-shrink: 0;
  background: var(--sm-bg-soft);
}
.content {
  flex: 1;
  overflow: auto;
  padding: 0.75rem;
  background: #fff;
}
.sec-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  color: var(--sm-text-secondary);
  font-size: 0.75rem;
}
.sec-title span {
  flex: 1;
  height: 0.0625rem;
  background: var(--sm-border);
}
.sec-title em {
  font-style: normal;
  font-weight: 600;
  color: var(--sm-text);
}
.grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem 0.5rem;
}
.item {
  border: 0;
  background: transparent;
  padding: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.6875rem;
  color: var(--sm-text);
}
.item img,
.ph {
  width: 4rem;
  height: 4rem;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 50%;
  background: var(--sm-bg-soft);
}
.empty {
  margin: 2rem;
  text-align: center;
  color: var(--sm-text-muted);
}
</style>
