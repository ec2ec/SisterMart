<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { applyAftersale, fetchOrder, type Order } from '../api/trade'
import { useLocaleText } from '../composables/useLocaleText'

const { t } = useI18n()
const { text } = useLocaleText()
const route = useRoute()
const router = useRouter()
const order = ref<Order | null>(null)
const itemId = ref<number | null>(null)
const type = ref<'refund_only' | 'return_refund'>('refund_only')
const refundTo = ref<'balance' | 'original'>('balance')
const reason = ref('')
const description = ref('')
const submitting = ref(false)

onMounted(async () => {
  order.value = await fetchOrder(String(route.params.id))
  const first = (order.value.items || []).find((i: any) => (i.refunded_qty || 0) < i.qty)
  itemId.value = first ? (first as any).id : null
  if (order.value.status === 'pending_shipment') type.value = 'refund_only'
})

async function submit() {
  if (!order.value || !itemId.value) return
  submitting.value = true
  try {
    const as = await applyAftersale({
      order_id: order.value.id,
      order_item_id: itemId.value,
      type: type.value,
      refund_to: refundTo.value,
      reason: reason.value || t('fulfill.defaultReason'),
      description: description.value,
    })
    showToast(t('fulfill.applied'))
    router.replace({ name: 'aftersale-detail', params: { id: as.id } })
  } catch (error) {
    showToast(error instanceof Error ? error.message : t('checkout.fail'))
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="page" v-if="order">
    <SubPageNav :title="t('fulfill.apply')" />
    <section class="card">
      <p>{{ order.order_no }} · {{ order.status }}</p>
      <div v-for="item in order.items" :key="(item as any).id" class="line">
        <label>
          <input v-model="itemId" type="radio" :value="(item as any).id" :disabled="((item as any).refunded_qty || 0) >= item.qty" />
          <img :src="item.image" alt="" />
          <span>{{ text(item.name) }} × {{ item.qty }}</span>
        </label>
      </div>
    </section>
    <section class="card">
      <van-radio-group v-if="order.status === 'pending_receipt'" v-model="type">
        <van-cell clickable @click="type = 'refund_only'">
          <template #title>{{ t('fulfill.refundOnly') }}</template>
          <template #right-icon><van-radio name="refund_only" /></template>
        </van-cell>
        <van-cell clickable @click="type = 'return_refund'">
          <template #title>{{ t('fulfill.returnRefund') }}</template>
          <template #right-icon><van-radio name="return_refund" /></template>
        </van-cell>
      </van-radio-group>
      <van-radio-group v-model="refundTo">
        <van-cell clickable @click="refundTo = 'balance'">
          <template #title>{{ t('fulfill.toBalance') }}</template>
          <template #right-icon><van-radio name="balance" /></template>
        </van-cell>
        <van-cell clickable @click="refundTo = 'original'">
          <template #title>{{ t('fulfill.toOriginal') }}</template>
          <template #right-icon><van-radio name="original" /></template>
        </van-cell>
      </van-radio-group>
      <van-field v-model="reason" :label="t('fulfill.reason')" />
      <van-field v-model="description" :label="t('fulfill.desc')" type="textarea" />
      <van-button block type="primary" color="var(--sm-primary)" round :loading="submitting" style="margin-top: 1rem" @click="submit">
        {{ t('fulfill.submit') }}
      </van-button>
    </section>
  </div>
</template>

<style scoped>
.card {
  margin: 0.75rem;
  padding: 0.875rem;
  background: #fff;
  border-radius: 0.75rem;
}
.line label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0.5rem 0;
  font-size: 0.8125rem;
}
.line img {
  width: 2.5rem;
  height: 2.5rem;
  object-fit: cover;
  border-radius: 0.375rem;
}
</style>
