<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NCard, NDescriptions, NDescriptionsItem, NInput, NSpace, useMessage } from 'naive-ui'
import { api } from '../api'

const route = useRoute()
const router = useRouter()
const message = useMessage()
const order = ref<any>(null)
const shipment = ref<any>(null)
const company = ref('順豐速運')
const tracking = ref('')
const nodeNote = ref('')

async function load() {
  order.value = await api(`/api/v1/admin/orders/${route.params.id}`, {}, true)
  try {
    shipment.value = await api(`/api/v1/admin/orders/${route.params.id}/shipment`, {}, true)
  } catch {
    shipment.value = null
  }
}

onMounted(load)

async function ship() {
  await api(`/api/v1/admin/orders/${route.params.id}/shipments`, {
    method: 'POST',
    body: JSON.stringify({ company: company.value, tracking_no: tracking.value, note: '商家已發貨' }),
  }, true)
  message.success('已發貨')
  await load()
}

async function addNode() {
  await api(`/api/v1/admin/orders/${route.params.id}/shipment-nodes`, {
    method: 'POST',
    body: JSON.stringify({ note: nodeNote.value }),
  }, true)
  nodeNote.value = ''
  message.success('已追加節點')
  await load()
}
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
      <n-descriptions-item label="售後觸達">{{ order.aftersale_touched ? '是' : '否' }}</n-descriptions-item>
      <n-descriptions-item label="完成發積分">{{ order.points_awarded }}</n-descriptions-item>
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
        <div>HK$ {{ item.unit_price }} × {{ item.qty }}（已退 {{ item.refunded_qty || 0 }}）= {{ item.line_amount }}</div>
      </div>
    </div>

    <n-card v-if="order.status === 'pending_shipment'" size="small" title="發貨" style="margin-top: 1rem">
      <n-space>
        <n-input v-model:value="company" placeholder="物流公司" style="width: 10rem" />
        <n-input v-model:value="tracking" placeholder="運單號" style="width: 12rem" />
        <n-button type="primary" @click="ship">確認發貨</n-button>
      </n-space>
    </n-card>

    <n-card v-if="shipment" size="small" title="物流" style="margin-top: 1rem">
      <p>{{ shipment.company }} · {{ shipment.tracking_no }}</p>
      <ul>
        <li v-for="n in shipment.nodes" :key="n.id">{{ n.occurred_at }} — {{ n.note }}</li>
      </ul>
      <n-space style="margin-top: 0.75rem">
        <n-input v-model:value="nodeNote" placeholder="追加軌跡說明" style="width: 16rem" />
        <n-button @click="addNode">追加節點</n-button>
      </n-space>
    </n-card>

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
ul {
  padding-left: 1.25rem;
  margin: 0.5rem 0;
}
</style>
