<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SubPageNav from '../components/SubPageNav.vue'
import { api } from '../api/client'

type Level = {
  id: number
  stars: number
  name: { 'zh-HK': string; en: string }
  min_spent: number
  max_spent: number | null
}

const { t, locale } = useI18n()
const totalSpent = ref(0)
const current = ref<Level | null>(null)
const levels = ref<Level[]>([])

onMounted(async () => {
  const data = await api<{ total_spent: number; level: Level; levels: Level[] }>(
    '/api/v1/members/me/level',
    {},
    'member',
  )
  totalSpent.value = data.total_spent
  current.value = data.level
  levels.value = data.levels
})

const stars = computed(() => '★'.repeat(current.value?.stars || 1))
function nameOf(row: Level) {
  return locale.value === 'en' ? row.name.en : row.name['zh-HK']
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('account.member')" />
    <section class="hero">
      <p class="stars">{{ stars }}</p>
      <h1>{{ current ? nameOf(current) : '—' }}</h1>
      <p>{{ t('account.spent', { n: totalSpent.toFixed(0) }).replace(/★+/g, stars) }}</p>
    </section>
    <van-cell-group inset>
      <van-cell
        v-for="row in levels"
        :key="row.id"
        :title="`${'★'.repeat(row.stars)} ${nameOf(row)}`"
        :label="
          row.max_spent == null
            ? `≥ HK$ ${row.min_spent}`
            : `HK$ ${row.min_spent} – ${row.max_spent}`
        "
        :value="current?.id === row.id ? t('m6.current') : ''"
      />
    </van-cell-group>
  </div>
</template>

<style scoped>
.hero {
  margin: 1rem;
  padding: 1.5rem;
  border-radius: 1rem;
  background: linear-gradient(160deg, #2a2118, #5a4634);
  color: #f7efe4;
  text-align: center;
}
.stars {
  font-size: 1.5rem;
  letter-spacing: 0.15em;
  margin: 0;
}
.hero h1 {
  margin: 0.35rem 0;
  font-size: 1.25rem;
}
.hero p {
  margin: 0;
  opacity: 0.85;
  font-size: 0.875rem;
}
</style>
