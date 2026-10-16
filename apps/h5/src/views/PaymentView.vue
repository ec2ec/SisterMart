<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { createPayment, fetchOrder, mockConfirmPayment, type Order } from '../api/trade'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const order = ref<Order | null>(null)
const channel = ref<'fps' | 'alipay_hk' | 'wechat_hk'>('fps')
const channelOptions = computed(() => [
  { value: 'fps' as const, label: t('pay.fps') },
  { value: 'alipay_hk' as const, label: t('pay.alipay') },
  { value: 'wechat_hk' as const, label: t('pay.wechat') },
])
const paying = ref(false)
let timer: number | undefined

async function load() {
  order.value = await fetchOrder(String(route.params.id))
  if (order.value.status === 'pending_shipment') {
    router.replace({ name: 'pay-result', query: { order_id: String(order.value.id) } })
  }
}

function stopPoll() {
  if (timer) {
    window.clearInterval(timer)
    timer = undefined
  }
}

onMounted(async () => {
  await load()
  // 僅待付款時輕量輪詢（模擬異步回調）；已離開待付或跳轉後立即停止
  if (order.value?.status !== 'pending_payment') return
  timer = window.setInterval(async () => {
    try {
      const latest = await fetchOrder(String(route.params.id))
      if (latest.status === 'pending_shipment') {
        stopPoll()
        router.replace({ name: 'pay-result', query: { order_id: String(latest.id) } })
        return
      }
      if (latest.status === 'cancelled') {
        stopPoll()
        showToast(t('pay.expired'))
        router.replace({ name: 'orders' })
      }
    } catch {
      /* ignore poll errors */
    }
  }, 5000)
})

onUnmounted(stopPoll)

async function pay() {
  if (!order.value) return
  paying.value = true
  try {
    const payment = await createPayment(order.value.id, channel.value)
    const confirmed = await mockConfirmPayment(payment.payment_id)
    router.replace({ name: 'pay-result', query: { order_id: String(confirmed.id) } })
  } catch (error) {
    showToast(error instanceof Error ? error.message : t('pay.fail'))
  } finally {
    paying.value = false
  }
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('pay.title')" />
    <section v-if="order" class="box">
      <p>{{ t('pay.orderNo', { n: order.order_no }) }}</p>
      <h1>HK$ {{ order.pay_amount.toFixed(2) }}</h1>
      <van-radio-group v-model="channel" class="channels">
        <van-cell-group inset>
          <van-cell
            v-for="opt in channelOptions"
            :key="opt.value"
            clickable
            :class="{ 'channel-row--active': channel === opt.value }"
            @click="channel = opt.value"
          >
            <template #icon>
              <van-icon
                name="checked"
                class="channel-check"
                :class="{ 'channel-check--hidden': channel !== opt.value }"
              />
            </template>
            <template #title>
              <span class="channel-label" :class="{ 'channel-label--active': channel === opt.value }">
                {{ opt.label }}
              </span>
            </template>
            <template #right-icon>
              <van-radio :name="opt.value" />
            </template>
          </van-cell>
        </van-cell-group>
      </van-radio-group>
      <p class="hint">{{ t('pay.mockHint') }}</p>
      <van-button block type="primary" color="var(--sm-primary)" round :loading="paying" @click="pay">
        {{ t('pay.confirm') }}
      </van-button>
    </section>
  </div>
</template>

<style scoped>
.box {
  padding: 1.25rem 1rem;
  text-align: center;
}
h1 {
  margin: 0.5rem 0 1.25rem;
  color: var(--sm-primary);
}
.hint {
  color: #999;
  font-size: 0.75rem;
  margin: 1rem 0;
}
.channels {
  text-align: left;
}
.channel-check {
  margin-right: 0.5rem;
  font-size: 1.125rem;
  color: var(--sm-primary);
}
.channel-check--hidden {
  visibility: hidden;
}
.channel-label {
  font-weight: 400;
}
.channel-label--active {
  font-weight: 700;
}
.channel-row--active :deep(.van-cell__title) {
  font-weight: 700;
}
</style>
