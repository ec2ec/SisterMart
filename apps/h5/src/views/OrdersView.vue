<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import SubPageNav from '../components/SubPageNav.vue'

const { t, locale } = useI18n()

const orders = computed(() =>
  locale.value === 'en'
    ? [
        { id: '#10021', status: 'To ship', amount: 'HK$ 197.00' },
        { id: '#10018', status: 'Completed', amount: 'HK$ 89.00' },
      ]
    : [
        { id: '#10021', status: '待發貨', amount: 'HK$ 197.00' },
        { id: '#10018', status: '已完成', amount: 'HK$ 89.00' },
      ],
)
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('pages.orders')" />
    <van-cell-group inset>
      <van-cell
        v-for="o in orders"
        :key="o.id"
        :title="o.id"
        :value="o.status"
        :label="o.amount"
      />
    </van-cell-group>
    <p class="hint">{{ t('pages.coming') }}</p>
  </div>
</template>

<style scoped>
.hint {
  text-align: center;
  color: #999;
  font-size: 0.75rem;
  padding: 1rem;
}
</style>
