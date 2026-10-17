<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchPointsMall } from '../api/m7'
import { useLocaleText } from '../composables/useLocaleText'

const { t } = useI18n()
const { text } = useLocaleText()
const router = useRouter()
const items = ref<Awaited<ReturnType<typeof fetchPointsMall>>['items']>([])

onMounted(async () => {
  items.value = (await fetchPointsMall()).items
})
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('account.pointsMall')" />
    <p class="mock">{{ t('m7.mockBanner') }}</p>
    <div class="grid">
      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        @click="router.push({ name: 'points-mall-detail', params: { id: item.id } })"
      >
        <img :src="item.image" alt="" />
        <strong>{{ text(item.name) }}</strong>
        <span>{{ item.points_cost }} {{ t('account.points') }}</span>
      </button>
    </div>
  </div>
</template>

<style scoped>
.mock {
  margin: 0.5rem 1rem;
  font-size: 0.75rem;
  color: #a67c52;
}
.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
}
.grid button {
  border: 0;
  background: #fff;
  text-align: left;
  padding: 0;
}
.grid img {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 0.5rem;
}
.grid strong {
  display: block;
  margin-top: 0.35rem;
  font-size: 0.8125rem;
}
.grid span {
  color: var(--sm-primary);
  font-size: 0.75rem;
}
</style>
