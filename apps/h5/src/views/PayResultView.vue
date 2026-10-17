<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchOrder, type Order } from '../api/trade'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const order = ref<Order | null>(null)

onMounted(async () => {
  order.value = await fetchOrder(String(route.query.order_id || ''))
})
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('pay.resultTitle')" />
    <div class="box">
      <van-icon name="checked" color="var(--sm-primary)" size="3rem" />
      <h1>{{ t('pay.success') }}</h1>
      <p v-if="order">{{ order.order_no }} · {{ t('pay.toShip') }}</p>
      <van-button type="primary" color="var(--sm-primary)" round block @click="router.push({ name: 'order-detail', params: { id: order?.id } })">
        {{ t('pay.viewOrder') }}
      </van-button>
      <van-button plain round block style="margin-top: 0.75rem" @click="router.replace('/')">{{ t('pay.backHome') }}</van-button>
    </div>
  </div>
</template>

<style scoped>
.box {
  padding: 2rem 1.25rem;
  text-align: center;
}
h1 {
  margin: 0.75rem 0 0.5rem;
}
p {
  color: #666;
  margin-bottom: 1.5rem;
}
</style>
