<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { fetchOrder, type Order } from '../api/trade'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const order = ref<Order | null>(null)
const ready = ref(false)

const statusKey: Record<string, string> = {
  pending_payment: 'orders.pendingPayment',
  pending_shipment: 'orders.pendingShipment',
  pending_receipt: 'orders.pendingReceipt',
  completed: 'orders.completed',
  cancelled: 'orders.cancelled',
  refunded: 'orders.refunded',
}

const statusText = computed(() => {
  if (!order.value) return t('pay.toShip')
  const key = statusKey[order.value.status]
  return key ? t(key) : t('pay.toShip')
})

onMounted(async () => {
  try {
    order.value = await fetchOrder(String(route.query.order_id || ''))
  } catch {
    order.value = null
  } finally {
    ready.value = true
  }
})

async function viewOrder() {
  const id = order.value?.id
  // 先 replace 到首頁再 push 訂單，訂單頁返回不會回到支付成功頁
  await router.replace({ name: 'home' })
  if (id) {
    await router.push({ name: 'order-detail', params: { id } })
  } else {
    await router.push({ name: 'orders' })
  }
}

function backHome() {
  router.replace({ name: 'home' })
}
</script>

<template>
  <div class="page">
    <div class="bg" aria-hidden="true">
      <span class="orb orb-a" />
      <span class="orb orb-b" />
      <span class="orb orb-c" />
      <span class="grid" />
      <span class="wave" />
      <span class="spark s1" />
      <span class="spark s2" />
      <span class="spark s3" />
    </div>

    <header class="nav">
      <button type="button" class="sm-icon-btn back" aria-label="back" @click="router.back()">
        <van-icon name="arrow-left" size="1.25rem" />
      </button>
      <h1 class="nav-title">{{ t('pay.resultTitle') }}</h1>
      <span class="nav-spacer" />
    </header>

    <main class="body">
      <div class="badge" :class="{ in: ready }">
        <span class="ring" />
        <span class="core">
          <van-icon name="success" class="tick" />
        </span>
      </div>

      <h2 class="title" :class="{ in: ready }">{{ t('pay.success') }}</h2>
      <p class="meta" :class="{ in: ready }">
        <template v-if="order">{{ order.order_no }} · {{ statusText }}</template>
        <template v-else>&nbsp;</template>
      </p>

      <div class="actions" :class="{ in: ready }">
        <van-button block round type="primary" color="var(--sm-primary)" @click="viewOrder">
          {{ t('pay.viewOrder') }}
        </van-button>
        <van-button block round plain hairline type="primary" @click="backHome">
          {{ t('pay.backHome') }}
        </van-button>
      </div>
    </main>
  </div>
</template>

<style scoped>
.page {
  --pay-ink: #14141f;
  --pay-muted: #8a8494;
  --pay-glow: #b78dff;
  --pay-core: #2a2148;
  position: relative;
  isolation: isolate;
  min-height: 100vh;
  min-height: 100dvh;
  overflow: hidden;
  color: var(--pay-ink);
  background: linear-gradient(180deg, #f3e9fb 0%, #ebe0f8 38%, #e4d6f4 68%, #dcccf0 100%);
}

.bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  overflow: hidden;
}

.grid {
  position: absolute;
  inset: 18% -8% -5%;
  opacity: 0.28;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.7) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.7) 1px, transparent 1px);
  background-size: 1.65rem 1.65rem;
  mask-image: linear-gradient(180deg, transparent 0%, #000 18%, #000 78%, transparent 100%);
}

.orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(2.25rem);
  opacity: 0.85;
}

.orb-a {
  top: -5rem;
  left: 50%;
  width: 18rem;
  height: 18rem;
  translate: -50% 0;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.95) 0%, rgba(210, 180, 255, 0.45) 48%, transparent 72%);
}

.orb-b {
  top: 42%;
  left: -6rem;
  width: 14rem;
  height: 14rem;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.75) 0%, rgba(200, 170, 255, 0.35) 50%, transparent 72%);
}

.orb-c {
  bottom: 4%;
  right: -5rem;
  width: 15rem;
  height: 15rem;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.7) 0%, rgba(180, 150, 240, 0.4) 50%, transparent 72%);
}

.wave {
  position: absolute;
  left: -15%;
  right: -15%;
  top: 46%;
  height: 10rem;
  opacity: 0.55;
  background:
    radial-gradient(90% 70% at 15% 60%, transparent 42%, rgba(255, 255, 255, 0.55) 43%, transparent 44%),
    radial-gradient(100% 80% at 55% 55%, transparent 44%, rgba(255, 255, 255, 0.4) 45%, transparent 46%),
    radial-gradient(95% 75% at 90% 70%, transparent 42%, rgba(255, 255, 255, 0.5) 43%, transparent 44%);
}

.spark {
  position: absolute;
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.9);
  box-shadow: 0 0 0.75rem rgba(183, 141, 255, 0.8);
  animation: twinkle 2.8s ease-in-out infinite;
}

.s1 {
  top: 28%;
  left: 18%;
}
.s2 {
  top: 36%;
  right: 16%;
  animation-delay: 0.7s;
}
.s3 {
  top: 58%;
  left: 72%;
  animation-delay: 1.4s;
}

@keyframes twinkle {
  0%,
  100% {
    opacity: 0.25;
    transform: scale(0.75);
  }
  50% {
    opacity: 1;
    transform: scale(1.15);
  }
}

.nav {
  position: relative;
  z-index: 2;
  display: grid;
  grid-template-columns: 2.75rem 1fr 2.75rem;
  align-items: center;
  padding: calc(0.65rem + env(safe-area-inset-top, 0px)) 0.5rem 0.65rem;
}

.back {
  color: var(--pay-ink);
}

.nav-title {
  margin: 0;
  text-align: center;
  font-size: 1.0625rem;
  font-weight: 700;
  letter-spacing: -0.01em;
}

.nav-spacer {
  width: 2.75rem;
}

.body {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 3.25rem 1.75rem 2.5rem;
  text-align: center;
}

.badge {
  position: relative;
  width: 5.75rem;
  height: 5.75rem;
  opacity: 0;
  transform: scale(0.72);
  transition:
    opacity 0.45s ease,
    transform 0.55s cubic-bezier(0.22, 1.2, 0.36, 1);
}

.badge.in {
  opacity: 1;
  transform: scale(1);
}

.ring {
  position: absolute;
  inset: -0.12rem;
  border-radius: 50%;
  background:
    radial-gradient(circle at 35% 30%, rgba(255, 255, 255, 0.55), transparent 42%),
    conic-gradient(from 200deg, #d7b8ff, #8f5dff 28%, #c4a0ff 52%, #7a4ef0 78%, #d7b8ff);
  box-shadow:
    0 0 0 0.18rem rgba(167, 120, 255, 0.45),
    0 0 1.35rem rgba(143, 93, 255, 0.75),
    0 0 2.6rem rgba(143, 93, 255, 0.4);
  animation: glow 2.4s ease-in-out infinite;
}

.ring::before {
  content: '';
  position: absolute;
  inset: 0.34rem;
  border-radius: 50%;
  background: transparent;
}

.ring::after {
  content: '';
  position: absolute;
  inset: 0.18rem;
  border-radius: 50%;
  box-shadow:
    inset 0 0 0 0.12rem rgba(255, 255, 255, 0.65),
    inset 0 0 0.55rem rgba(210, 180, 255, 0.35);
}

@keyframes glow {
  0%,
  100% {
    filter: brightness(1);
    box-shadow:
      0 0 0 0.2rem rgba(183, 141, 255, 0.3),
      0 0 1.5rem rgba(155, 109, 255, 0.55),
      0 0 2.5rem rgba(155, 109, 255, 0.28);
  }
  50% {
    filter: brightness(1.08);
    box-shadow:
      0 0 0 0.28rem rgba(183, 141, 255, 0.45),
      0 0 2.1rem rgba(155, 109, 255, 0.8),
      0 0 3.25rem rgba(155, 109, 255, 0.42);
  }
}

.core {
  position: absolute;
  inset: 0.48rem;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: linear-gradient(155deg, #3d335f 0%, #241a3d 48%, #15101f 100%);
  color: #fff;
  box-shadow:
    inset 0 0.1rem 0.2rem rgba(255, 255, 255, 0.2),
    0 0.15rem 0.45rem rgba(40, 20, 70, 0.35);
  z-index: 1;
}

.tick {
  font-size: 2rem;
  line-height: 1;
}

.title {
  margin: 1.35rem 0 0;
  font-size: 1.625rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  opacity: 0;
  transform: translateY(0.5rem);
  transition:
    opacity 0.4s ease 0.12s,
    transform 0.45s ease 0.12s;
}

.title.in {
  opacity: 1;
  transform: none;
}

.meta {
  margin: 0.55rem 0 0;
  min-height: 1.25rem;
  color: var(--pay-muted);
  font-size: 0.875rem;
  letter-spacing: 0.01em;
  opacity: 0;
  transform: translateY(0.4rem);
  transition:
    opacity 0.4s ease 0.2s,
    transform 0.45s ease 0.2s;
}

.meta.in {
  opacity: 1;
  transform: none;
}

.actions {
  width: 100%;
  max-width: 18.5rem;
  margin-top: 2.75rem;
  display: grid;
  gap: 0.85rem;
  opacity: 0;
  transform: translateY(0.65rem);
  transition:
    opacity 0.4s ease 0.28s,
    transform 0.5s ease 0.28s;
}

.actions.in {
  opacity: 1;
  transform: none;
}
</style>
