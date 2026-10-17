<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchDownlines } from '../api/m7'

const { t } = useI18n()
const items = ref<Awaited<ReturnType<typeof fetchDownlines>>['items']>([])

onMounted(async () => {
  items.value = (await fetchDownlines()).items
})
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('m7.downlines')" />
    <van-cell-group inset>
      <van-cell
        v-for="row in items"
        :key="row.id"
        :title="row.nickname"
        :label="`${t('m7.spentShort')}: HK$${row.spent} · ${t('m7.earnShort')}: HK$${row.commission_earned}`"
        :value="row.registered_at.slice(0, 10)"
      />
    </van-cell-group>
  </div>
</template>

<style scoped>
</style>
