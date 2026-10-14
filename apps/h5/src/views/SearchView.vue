<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ProductCard from '../components/ProductCard.vue'
import SubPageNav from '../components/SubPageNav.vue'
import { searchProducts } from '../mock/data'

const { t } = useI18n()
const keyword = ref('')
const list = computed(() => searchProducts(keyword.value))
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('pages.search')" />
    <van-search
      v-model="keyword"
      show-action
      :placeholder="t('pages.searchHint')"
      shape="round"
    >
      <template #action>
        <div @click="keyword = keyword">{{ t('pages.search') }}</div>
      </template>
    </van-search>
    <p v-if="!list.length" class="empty">{{ t('pages.searchEmpty') }}</p>
    <div v-else class="grid">
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

.empty {
  text-align: center;
  color: #999;
  padding: 2.5rem 1rem;
}
</style>
