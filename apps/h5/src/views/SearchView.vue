<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ProductCard from '../components/ProductCard.vue'
import { fetchProducts, type ProductCard as Card } from '../api/catalog'
import { useNavBack } from '../composables/useNavBack'

const PAGE_SIZE = 20

const { t } = useI18n()
const { goBack } = useNavBack(() => '/')

const keyword = ref('')
const list = ref<Card[]>([])
const offset = ref(0)
const total = ref(0)
const loading = ref(false)
const listLoading = ref(false)
const finished = ref(false)
const initial = ref(true)
/** 僅用戶滑動後才允許 van-list 觸底加載（避免首屏自動連拉） */
const allowLoadMore = ref(false)
let seq = 0
let debounceTimer = 0

const empty = computed(() => !initial.value && !loading.value && list.value.length === 0)

function markUserScroll() {
  allowLoadMore.value = true
}

async function loadPage(reset: boolean) {
  const my = ++seq
  if (reset) {
    offset.value = 0
    finished.value = false
    allowLoadMore.value = false
    list.value = []
    loading.value = true
  } else {
    if (finished.value || loading.value) {
      listLoading.value = false
      return
    }
    loading.value = true
  }
  listLoading.value = true
  try {
    const q = keyword.value.trim()
    const data = await fetchProducts({
      ...(q ? { q } : {}),
      limit: String(PAGE_SIZE),
      offset: String(offset.value),
    })
    if (my !== seq) return
    const items = data.items || []
    total.value = Number(data.total ?? items.length) || 0
    list.value = reset ? items : list.value.concat(items)
    offset.value = list.value.length
    const hasMore =
      typeof data.has_more === 'boolean' ? data.has_more : list.value.length < total.value
    finished.value = !hasMore || items.length === 0
  } catch {
    if (my !== seq) return
    if (reset) list.value = []
    finished.value = true
  } finally {
    if (my === seq) {
      loading.value = false
      listLoading.value = false
      initial.value = false
    }
  }
}

function onListLoad() {
  // 首屏裝載走 keyword watch；此處只處理觸底上拉
  if (!allowLoadMore.value) {
    listLoading.value = false
    return
  }
  void loadPage(false)
}

function scheduleSearch() {
  window.clearTimeout(debounceTimer)
  debounceTimer = window.setTimeout(() => {
    void loadPage(true)
  }, 250)
}

watch(keyword, scheduleSearch, { immediate: true })

onMounted(() => {
  window.addEventListener('scroll', markUserScroll, { passive: true })
})
onUnmounted(() => {
  window.removeEventListener('scroll', markUserScroll)
  window.clearTimeout(debounceTimer)
})
</script>

<template>
  <div class="page search-page">
    <header class="search-bar">
      <button type="button" class="back" :aria-label="t('pages.back')" @click="goBack">
        <van-icon name="arrow-left" size="1.25rem" />
      </button>
      <h1 class="title">{{ t('pages.search') }}</h1>
      <van-search
        v-model="keyword"
        class="field"
        shape="round"
        background="transparent"
        :placeholder="t('pages.searchHint')"
        clearable
      />
    </header>
    <div class="bar-placeholder" aria-hidden="true" />

    <p v-if="empty" class="empty">{{ t('pages.searchEmpty') }}</p>
    <van-list
      v-else
      v-model:loading="listLoading"
      :finished="finished"
      :finished-text="list.length ? t('pages.searchNoMore') : ''"
      :immediate-check="false"
      @load="onListLoad"
    >
      <div class="grid">
        <ProductCard v-for="p in list" :key="p.id" :product="p" />
      </div>
    </van-list>
  </div>
</template>

<style scoped>
.search-bar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 300;
  display: flex;
  align-items: center;
  gap: 0.35rem;
  min-height: var(--sm-header-h);
  padding: calc(0.35rem + env(safe-area-inset-top, 0rem)) 0.5rem 0.35rem 0.25rem;
  background: #fff;
  box-sizing: border-box;
}

.bar-placeholder {
  height: calc(var(--sm-header-h) + env(safe-area-inset-top, 0rem));
}

.back {
  flex: none;
  width: 2.25rem;
  height: 2.25rem;
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--sm-text);
  display: grid;
  place-items: center;
}

.title {
  flex: none;
  margin: 0;
  max-width: 4.5rem;
  font-size: 1rem;
  font-weight: 700;
  line-height: 1.2;
  white-space: nowrap;
}

.field {
  flex: 1 1 auto;
  min-width: 0;
  padding: 0 !important;
}

.field :deep(.van-search__content) {
  flex: 1;
  background: #f5f5f5;
}

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
