<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchRanking } from '../api/m7'

const { t } = useI18n()
const period = ref<'month' | 'total'>('month')
const items = ref<Awaited<ReturnType<typeof fetchRanking>>['items']>([])

async function load() {
  items.value = (await fetchRanking(period.value)).items
}

onMounted(load)
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('m7.ranking')" />
    <p class="mock">{{ t('m7.mockBanner') }}</p>
    <van-tabs v-model:active="period" @change="load">
      <van-tab :title="t('m7.month')" name="month" />
      <van-tab :title="t('m7.total')" name="total" />
    </van-tabs>
    <van-cell-group inset>
      <van-cell
        v-for="row in items"
        :key="row.rank"
        :title="`#${row.rank} ${row.nickname}`"
        :value="`HK$ ${row.commission}`"
      />
    </van-cell-group>
  </div>
</template>

<style scoped>
.mock {
  margin: 0.5rem 1rem;
  font-size: 0.75rem;
  color: #a67c52;
}
</style>
