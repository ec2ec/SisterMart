<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchPromoOrders } from '../api/m7'

const { t } = useI18n()
const items = ref<Awaited<ReturnType<typeof fetchPromoOrders>>['items']>([])

onMounted(async () => {
  items.value = (await fetchPromoOrders()).items
})
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('m7.promoOrders')" />
    <p class="mock">{{ t('m7.mockBanner') }}</p>
    <van-cell-group inset>
      <van-cell
        v-for="row in items"
        :key="row.order_no"
        :title="row.title"
        :label="row.order_no"
        :value="`${row.commission} · ${row.status}`"
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
