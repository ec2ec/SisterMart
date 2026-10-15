<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NCard, NDescriptions, NDescriptionsItem } from 'naive-ui'
import { api } from '../api'

const route = useRoute()
const router = useRouter()
const order = ref<any>(null)

onMounted(async () => {
  order.value = await api(`/api/v1/admin/orders/${route.params.id}`, {}, true)
})
</script>

<template>
  <n-card v-if="order" :title="`訂單 ${order.order_no}`">
    <n-descriptions label-placement="left" :column="2">
      <n-descriptions-item label="狀態">{{ order.status }}</n-descriptions-item>
      <n-descriptions-item label="實付">{{ order.pay_amount }}</n-descriptions-item>
      <n-descriptions-item label="商品金額">{{ order.goods_amount }}</n-descriptions-item>
      <n-descriptions-item label="運費">{{ order.shipping_amount }}</n-descriptions-item>
      <n-descriptions-item label="券抵扣">{{ order.coupon_amount }}</n-descriptions-item>
      <n-descriptions-item label="積分抵扣">{{ order.points_amount }}</n-descriptions-item>
      <n-descriptions-item label="餘額抵扣">{{ order.balance_amount }}</n-descriptions-item>
      <n-descriptions-item label="支付渠道">{{ order.pay_channel || '-' }}</n-descriptions-item>
      <n-descriptions-item label="收件人">{{ order.address?.receiver }} {{ order.address?.phone }}</n-descriptions-item>
      <n-descriptions-item label="地址">
        {{ order.address?.region }} {{ order.address?.district }} {{ order.address?.detail }}
      </n-descriptions-item>
    </n-descriptions>
    <h3 style="margin-top: 1rem">明細</h3>
    <div v-for="(item, idx) in order.items" :key="idx" class="line">
      <img :src="item.image" alt="" />
      <div>
        <div>{{ item.name['zh-HK'] }} / {{ item.spec['zh-HK'] }}</div>
        <div>HK$ {{ item.unit_price }} × {{ item.qty }} = {{ item.line_amount }}</div>
      </div>
    </div>
    <n-button style="margin-top: 1rem" @click="router.push('/sales/orders')">返回列表</n-button>
  </n-card>
</template>

<style scoped>
.line {
  display: flex;
  gap: 0.75rem;
  margin: 0.5rem 0;
  align-items: center;
}
.line img {
  width: 3rem;
  height: 3rem;
  object-fit: cover;
  border-radius: 0.375rem;
}
</style>
