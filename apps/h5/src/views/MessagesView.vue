<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import SubPageNav from '../components/SubPageNav.vue'

const { t, locale } = useI18n()

const items = computed(() =>
  locale.value === 'en'
    ? [
        { title: 'Order shipped', time: 'Today 10:20', body: 'Your order #10021 is on the way.' },
        { title: 'Coupon received', time: 'Yesterday', body: 'HK$20 off coupon is ready.' },
      ]
    : [
        { title: '訂單已發貨', time: '今天 10:20', body: '您的訂單 #10021 已發出。' },
        { title: '優惠券到帳', time: '昨天', body: 'HK$20 優惠券已入帳。' },
      ],
)
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('pages.messages')" />
    <van-cell-group inset>
      <van-cell
        v-for="(m, i) in items"
        :key="i"
        :title="m.title"
        :label="m.body"
        :value="m.time"
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
