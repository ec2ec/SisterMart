<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import {
  cancelOrder,
  confirmReceipt,
  fetchOrder,
  fetchOrderMessages,
  postOrderMessage,
  type Order,
  type OrderMessage,
} from '../api/trade'
import { useLocaleText } from '../composables/useLocaleText'
import { formatHkAddressLine, type HkLocale } from '../data/hkDistricts'
import { ApiError } from '../api/client'
import { assetUrl } from '../utils/assetUrl'
import { previewImages } from '../utils/imagePreview'

const { t, locale } = useI18n()
const { text } = useLocaleText()
const uiLocale = computed(() => (locale.value === 'en' ? 'en' : 'zh-HK') as HkLocale)
const route = useRoute()
const router = useRouter()
const order = ref<Order | null>(null)
const messages = ref<OrderMessage[]>([])
const showChat = ref(false)
const draft = ref('')
const chatBusy = ref(false)

async function loadMessages() {
  if (!order.value) {
    messages.value = []
    return
  }
  try {
    const data = await fetchOrderMessages(order.value.id)
    messages.value = data.items || []
  } catch {
    messages.value = []
  }
}

async function load() {
  order.value = await fetchOrder(String(route.params.id))
  await loadMessages()
}

onMounted(load)
watch(
  () => route.params.id,
  () => {
    void load()
  },
)

const statusMap: Record<string, string> = {
  pending_payment: 'orders.pendingPayment',
  pending_shipment: 'orders.pendingShipment',
  pending_receipt: 'orders.pendingReceipt',
  completed: 'orders.completed',
  cancelled: 'orders.cancelled',
  refunded: 'orders.refunded',
}

const aftersaleStatusKey: Record<string, string> = {
  pending_review: 'fulfill.stReview',
  awaiting_return: 'fulfill.stReturn',
  pending_refund: 'fulfill.stRefund',
  completed: 'fulfill.stDone',
  rejected: 'fulfill.stRejected',
  cancelled: 'fulfill.stCancelled',
}

const aftersaleApplyLabel = computed(() =>
  order.value?.has_rejected_aftersale ? t('fulfill.reapply') : t('fulfill.apply'),
)

const promoDiscount = computed(() => {
  if (!order.value) return 0
  if (typeof order.value.promo_discount === 'number') return order.value.promo_discount
  return (order.value.items || []).reduce((sum, item) => {
    const compare = Number(item.compare_at ?? item.unit_price)
    const unit = Number(item.unit_price)
    return sum + Math.max(0, (compare - unit) * item.qty)
  }, 0)
})

const couponAmount = computed(() => Number(order.value?.coupon_amount ?? 0))

const reviewedItems = computed(() =>
  (order.value?.items || []).filter((item) => item.review),
)

function itemCompareAt(item: NonNullable<Order['items']>[number]) {
  const compare = Number(item.compare_at ?? 0)
  return compare > item.unit_price ? compare : 0
}

async function onCancel() {
  if (!order.value) return
  await cancelOrder(order.value.id)
  showToast(t('orders.cancelled'))
  await load()
}

async function onConfirm() {
  if (!order.value) return
  await confirmReceipt(order.value.id)
  showToast(t('fulfill.confirmed'))
  await load()
}

function openChat() {
  draft.value = ''
  showChat.value = true
}

async function sendChat() {
  if (!order.value || chatBusy.value) return
  const body = draft.value.trim()
  if (!body) {
    showToast(t('orders.chatRequired'))
    return
  }
  if (body.length > 256) {
    showToast(t('orders.chatTooLong'))
    return
  }
  chatBusy.value = true
  try {
    const msg = await postOrderMessage(order.value.id, body)
    messages.value = [...messages.value, msg]
    showChat.value = false
    draft.value = ''
    showToast(t('orders.chatSent'))
  } catch (e) {
    const msg = e instanceof ApiError && e.message ? e.message : t('orders.chatFail')
    showToast(msg)
  } finally {
    chatBusy.value = false
  }
}
</script>

<template>
  <div class="page order-detail" v-if="order">
    <SubPageNav :title="t('orders.detail')" />

    <section class="status-card">
      <p class="order-no">{{ order.order_no }}</p>
      <h2 class="status">{{ t(statusMap[order.status] || order.status) }}</h2>
    </section>

    <section v-if="order.address" class="card">
      <h3 class="sec-title">{{ t('orders.sectionShip') }}</h3>
      <p class="ship-line">
        <strong>{{ (order.address as any).receiver }}</strong>
        <span>{{ (order.address as any).phone }}</span>
      </p>
      <p class="ship-addr">
        {{
          formatHkAddressLine(
            String((order.address as any).region || ''),
            String((order.address as any).district || ''),
            String((order.address as any).detail || ''),
            uiLocale,
            String((order.address as any).area || ''),
          )
        }}
      </p>
    </section>

    <section v-if="order.remark" class="card remark">
      <h3 class="sec-title">{{ t('checkout.remark') }}</h3>
      <p>{{ order.remark }}</p>
    </section>

    <section class="card">
      <h3 class="sec-title">{{ t('orders.sectionItems') }}</h3>
      <div v-for="(item, idx) in order.items || []" :key="idx" class="line">
        <img :src="assetUrl(item.image)" alt="" />
        <div class="meta">
          <h4>{{ text(item.name) }}</h4>
          <p>{{ text(item.spec) }} × {{ item.qty }}</p>
          <div class="prices">
            <strong class="sale">HK$ {{ item.unit_price.toFixed(2) }}</strong>
            <s v-if="itemCompareAt(item)" class="origin">HK$ {{ itemCompareAt(item).toFixed(2) }}</s>
          </div>
          <div v-if="item.can_review || item.can_follow" class="item-actions">
            <van-button
              v-if="item.can_review"
              size="mini"
              plain
              round
              type="primary"
              @click.stop="
                router.push({ name: 'order-review', params: { id: order.id }, query: { item: String(item.id) } })
              "
            >
              {{ t('review.go') }}
            </van-button>
            <van-button
              v-else-if="item.can_follow"
              size="mini"
              plain
              round
              @click.stop="
                router.push({ name: 'order-review', params: { id: order.id }, query: { item: String(item.id) } })
              "
            >
              {{ t('review.goFollow') }}
            </van-button>
          </div>
        </div>
      </div>
    </section>

    <section v-if="(order.aftersales || []).length" class="card aftersales">
      <h3 class="sec-title">{{ t('fulfill.records') }}</h3>
      <button
        v-for="as in order.aftersales"
        :key="as.id"
        type="button"
        class="as-row"
        @click="router.push({ name: 'aftersale-detail', params: { id: as.id } })"
      >
        <div class="as-main">
          <strong>{{ as.aftersale_no }}</strong>
          <span class="as-meta">
            {{ as.type === 'return_refund' ? t('fulfill.returnRefund') : t('fulfill.refundOnly') }}
            · HK$ {{ Number(as.amount).toFixed(2) }}
          </span>
          <span v-if="as.item" class="as-item">{{ text(as.item.name) }}</span>
          <span v-if="as.status === 'rejected' && as.admin_note" class="as-note">
            {{ t('fulfill.adminNote') }}：{{ as.admin_note }}
          </span>
          <div v-if="as.evidence?.length" class="as-ev">
            <img
              v-for="(u, i) in as.evidence.slice(0, 3)"
              :key="i"
              :src="assetUrl(u)"
              alt=""
            />
          </div>
        </div>
        <span class="as-status" :class="as.status">{{ t(aftersaleStatusKey[as.status] || as.status) }}</span>
      </button>
    </section>

    <section class="card sum">
      <h3 class="sec-title">{{ t('orders.sectionAmount') }}</h3>
      <p>
        <span>{{ t('checkout.goods') }}</span>
        <em>HK$ {{ order.goods_amount.toFixed(2) }}</em>
      </p>
      <p>
        <span>{{ t('checkout.shipping') }}</span>
        <em>{{ order.shipping_amount <= 0 ? t('checkout.freeShip') : `HK$ ${order.shipping_amount.toFixed(2)}` }}</em>
      </p>
      <p v-if="promoDiscount > 0" class="discount">
        <span>{{ t('orders.promoDiscount') }}</span>
        <em>-{{ promoDiscount.toFixed(2) }}</em>
      </p>
      <p v-if="couponAmount > 0" class="discount">
        <span>{{ t('checkout.coupon') }}</span>
        <em>-{{ couponAmount.toFixed(2) }}</em>
      </p>
      <p v-if="(order.points_amount || 0) > 0" class="discount">
        <span>{{ t('checkout.pointsLabel') }}</span>
        <em>-HK$ {{ Number(order.points_amount).toFixed(2) }}</em>
      </p>
      <p v-if="(order.balance_amount || 0) > 0" class="discount">
        <span>{{ t('checkout.balanceLabel') }}</span>
        <em>-HK$ {{ Number(order.balance_amount).toFixed(2) }}</em>
      </p>
      <p class="pay">
        <span>{{ t('checkout.pay') }}</span>
        <strong>HK$ {{ order.pay_amount.toFixed(2) }}</strong>
      </p>
    </section>

    <section v-if="reviewedItems.length" class="card reviews">
      <h3 class="sec-title">{{ t('orders.sectionReviews') }}</h3>
      <div v-for="item in reviewedItems" :key="item.id" class="rev-block">
        <div class="rev-product">
          <img :src="assetUrl(item.image)" alt="" />
          <div>
            <strong>{{ text(item.name) }}</strong>
            <p v-if="text(item.spec)">{{ text(item.spec) }}</p>
          </div>
        </div>
        <div class="rev-head">
          <span class="rev-stars" aria-hidden="true">
            {{ '★'.repeat(item.review!.stars) }}{{ '☆'.repeat(Math.max(0, 5 - item.review!.stars)) }}
          </span>
          <span
            v-if="item.review!.moderation_status && item.review!.moderation_status !== 'approved'"
            class="rev-mod"
          >
            {{
              item.review!.moderation_status === 'rejected' ? t('review.rejected') : t('review.pending')
            }}
          </span>
        </div>
        <p v-if="item.review!.body" class="rev-body">{{ item.review!.body }}</p>
        <div v-if="item.review!.images?.length" class="rev-imgs">
          <button
            v-for="(img, i) in item.review!.images"
            :key="i"
            type="button"
            class="rev-img"
            @click="previewImages(item.review!.images!, i)"
          >
            <img :src="assetUrl(img)" alt="" />
          </button>
        </div>
        <p v-if="item.review!.follow" class="rev-follow">
          <em>{{ t('review.followLabel') }}</em>{{ item.review!.follow }}
        </p>
        <p v-if="item.review!.reply" class="rev-reply">
          <em>{{ t('review.replyLabel') }}</em>{{ item.review!.reply }}
        </p>
        <div v-if="item.can_follow" class="item-actions">
          <van-button
            size="mini"
            plain
            round
            @click="
              router.push({ name: 'order-review', params: { id: order.id }, query: { item: String(item.id) } })
            "
          >
            {{ t('review.goFollow') }}
          </van-button>
        </div>
      </div>
    </section>

    <section class="card chat">
      <div class="chat-head">
        <h3 class="sec-title">{{ t('orders.chatTitle') }}</h3>
        <span class="chat-legend">
          <em class="seller">{{ t('orders.chatSeller') }}</em>
          <em class="buyer">{{ t('orders.chatBuyer') }}</em>
        </span>
      </div>
      <p v-if="!messages.length" class="chat-empty">{{ t('orders.chatEmpty') }}</p>
      <ul v-else class="chat-list">
        <li
          v-for="m in messages"
          :key="m.id"
          class="bubble"
          :class="m.sender === 'buyer' ? 'is-buyer' : 'is-seller'"
        >
          <span class="who">{{ m.sender === 'buyer' ? t('orders.chatBuyer') : t('orders.chatSeller') }}</span>
          <p class="txt">{{ m.body }}</p>
          <time v-if="m.created_at">{{ m.created_at }}</time>
        </li>
      </ul>
      <van-button
        class="chat-btn"
        block
        round
        plain
        type="primary"
        @click="openChat"
      >
        {{ t('orders.chatAction') }}
      </van-button>
    </section>

    <div class="actions">
      <van-button v-if="order.status === 'pending_payment'" plain round @click="onCancel">{{ t('orders.cancel') }}</van-button>
      <van-button
        v-if="order.status === 'pending_payment'"
        type="primary"
        color="var(--sm-primary)"
        round
        @click="router.push({ name: 'payment', params: { id: order.id } })"
      >
        {{ t('orders.payNow') }}
      </van-button>
      <van-button
        v-if="order.status === 'pending_receipt' || order.shipped_at"
        plain
        round
        @click="router.push({ name: 'logistics', params: { id: order.id } })"
      >
        {{ t('fulfill.logistics') }}
      </van-button>
      <van-button v-if="order.status === 'pending_receipt'" type="primary" color="var(--sm-primary)" round @click="onConfirm">
        {{ t('fulfill.confirm') }}
      </van-button>
      <van-button
        v-if="order.can_apply_aftersale"
        plain
        round
        @click="router.push({ name: 'aftersale-apply', params: { id: order.id } })"
      >
        {{ aftersaleApplyLabel }}
      </van-button>
      <van-button
        v-if="order.needs_review"
        type="primary"
        color="var(--sm-primary)"
        round
        @click="
          router.push({
            name: 'order-review',
            params: { id: order.id },
            query: { item: String((order.items || []).find((i) => i.can_review)?.id || '') },
          })
        "
      >
        {{ t('review.go') }}
      </van-button>
    </div>

    <van-popup v-model:show="showChat" position="bottom" round :style="{ padding: '1rem 1rem 1.25rem' }">
      <h3 class="popup-title">{{ t('orders.chatPopupTitle') }}</h3>
      <van-field
        v-model="draft"
        type="textarea"
        rows="3"
        maxlength="256"
        show-word-limit
        :placeholder="t('orders.chatPlaceholder')"
      />
      <van-button
        type="primary"
        color="var(--sm-primary)"
        block
        round
        :loading="chatBusy"
        style="margin-top: 0.75rem"
        @click="sendChat"
      >
        {{ t('orders.chatSend') }}
      </van-button>
    </van-popup>
  </div>
</template>

<style scoped>
/* 配色對齊帳戶頁：深紫漸變底 + 白卡片（僅本頁，不改 Tab） */
.order-detail {
  --od-accent: #7d5bb6;
  --od-accent-soft: #b794f6;
  --od-ink: #111111;
  --od-muted: #757575;
  --od-line: #eee8f5;
  --od-card: #ffffff;
  min-height: 100%;
  padding-bottom: 1.25rem;
  background:
    radial-gradient(ellipse 80% 50% at 85% -5%, rgba(183, 148, 246, 0.35) 0%, transparent 55%),
    radial-gradient(ellipse 60% 40% at 10% 8%, rgba(125, 91, 182, 0.4) 0%, transparent 50%),
    linear-gradient(165deg, #4a2d7a 0%, #2a1848 42%, #1a0f2e 72%, #120a22 100%);
  color: var(--od-ink);
}

.order-detail :deep(.sub-nav.van-nav-bar),
.order-detail :deep(.van-nav-bar) {
  background: transparent;
}
.order-detail :deep(.van-nav-bar__placeholder) {
  background: transparent;
}
.order-detail :deep(.van-nav-bar__title),
.order-detail :deep(.van-nav-bar .van-icon),
.order-detail :deep(.van-nav-bar__text) {
  color: #fff;
}

.card {
  margin: 0.65rem 0.75rem;
  padding: 0.9rem 0.875rem;
  background: var(--od-card);
  border-radius: 1rem;
  box-shadow: 0 0.35rem 1.1rem rgba(18, 10, 34, 0.18);
}
.sec-title {
  margin: 0 0 0.7rem;
  padding-bottom: 0.55rem;
  border-bottom: 1px solid var(--od-line);
  font-size: 0.875rem;
  font-weight: 700;
  color: var(--od-ink);
  letter-spacing: 0.01em;
}
.status-card {
  margin: 0.15rem 0.9rem 0.85rem;
  padding: 0.25rem 0.15rem 0.5rem;
  text-align: left;
  background: transparent;
  box-shadow: none;
}
.order-no {
  margin: 0;
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.65);
}
.status {
  margin: 0.35rem 0 0;
  font-size: 1.35rem;
  font-weight: 800;
  color: #fff;
  letter-spacing: -0.02em;
  text-shadow: 0 0.1rem 0.45rem rgba(0, 0, 0, 0.25);
}
.ship-line {
  margin: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: baseline;
  font-size: 0.9rem;
}
.ship-line strong {
  font-weight: 700;
  color: var(--od-ink);
}
.ship-line span {
  color: var(--od-muted);
}
.ship-addr {
  margin: 0.4rem 0 0;
  font-size: 0.8125rem;
  line-height: 1.5;
  color: var(--od-muted);
}
.remark p {
  margin: 0;
  font-size: 0.875rem;
  line-height: 1.5;
  color: #555;
  white-space: pre-wrap;
  word-break: break-word;
}
.line {
  display: grid;
  grid-template-columns: 4rem minmax(0, 1fr);
  gap: 0.75rem;
  padding: 0.65rem 0;
  border-bottom: 1px solid var(--od-line);
}
.line:first-of-type {
  padding-top: 0;
}
.line:last-child {
  padding-bottom: 0;
  border-bottom: 0;
}
.line img {
  width: 4rem;
  height: 4rem;
  object-fit: cover;
  border-radius: 0.5rem;
  background: #f3eef9;
}
.item-actions {
  margin-top: 0.4rem;
}
.reviews .rev-block {
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--od-line);
}
.reviews .rev-block:first-of-type {
  padding-top: 0;
}
.reviews .rev-block:last-child {
  padding-bottom: 0;
  border-bottom: 0;
}
.rev-product {
  display: grid;
  grid-template-columns: 2.75rem minmax(0, 1fr);
  gap: 0.55rem;
  align-items: center;
  margin-bottom: 0.5rem;
}
.rev-product img {
  width: 2.75rem;
  height: 2.75rem;
  object-fit: cover;
  border-radius: 0.4rem;
  background: #f3eef9;
}
.rev-product strong {
  display: block;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.35;
  color: var(--od-ink);
}
.rev-product p {
  margin: 0.15rem 0 0;
  font-size: 0.75rem;
  color: var(--od-muted);
}
.rev-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem 0.5rem;
}
.rev-stars {
  color: #f59a23;
  letter-spacing: 0.05em;
  font-size: 0.875rem;
  line-height: 1;
}
.rev-mod {
  font-size: 0.6875rem;
  color: #6b46c1;
  background: #f3e8ff;
  padding: 0.1rem 0.35rem;
  border-radius: 0.25rem;
}
.rev-body,
.rev-follow,
.rev-reply {
  margin: 0.4rem 0 0;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: #444;
  white-space: pre-wrap;
  word-break: break-word;
}
.rev-follow em,
.rev-reply em {
  font-style: normal;
  color: var(--od-muted);
  margin-right: 0.15rem;
}
.rev-imgs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: 0.45rem;
}
.rev-img {
  display: block;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: pointer;
  line-height: 0;
}
.rev-img img {
  width: 3.5rem;
  height: 3.5rem;
  object-fit: cover;
  border-radius: 0.35rem;
  background: #f3eef9;
}
.meta {
  min-width: 0;
}
.meta h4 {
  margin: 0 0 0.25rem;
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.35;
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
  color: var(--od-ink);
}
.meta p {
  margin: 0;
  color: var(--od-muted);
  font-size: 0.8125rem;
}
.prices {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  margin-top: 0.35rem;
}
.sale {
  color: var(--od-ink);
  font-size: 0.95rem;
}
.origin {
  color: #999;
  font-size: 0.8125rem;
  font-weight: 400;
}
.sum p {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin: 0.4rem 0;
  font-size: 0.9rem;
  color: var(--od-ink);
}
.sum em {
  font-style: normal;
}
.sum .discount {
  color: #c026d3;
}
.sum .discount em {
  color: #c026d3;
  font-weight: 600;
}
.sum .pay {
  margin-top: 0.65rem;
  padding-top: 0.65rem;
  border-top: 1px solid var(--od-line);
}
.sum .pay strong {
  font-size: 1.1rem;
  color: var(--od-accent);
}
.chat-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.65rem;
}
.chat-head .sec-title {
  margin: 0;
  padding: 0;
  border: 0;
}
.chat-legend {
  display: flex;
  gap: 0.75rem;
  font-size: 0.75rem;
  color: var(--od-muted);
}
.chat-legend em {
  font-style: normal;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}
.chat-legend .seller::before,
.chat-legend .buyer::before {
  content: '';
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  flex: none;
}
.chat-legend .seller::before {
  background: var(--od-accent);
}
.chat-legend .buyer::before {
  background: #de46ad;
}
.chat-empty {
  margin: 0.5rem 0 0.75rem;
  color: #aaa;
  font-size: 0.8125rem;
  text-align: center;
}
.chat-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}
.bubble {
  max-width: 85%;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.bubble.is-seller {
  align-self: flex-start;
  align-items: flex-start;
}
.bubble.is-buyer {
  align-self: flex-end;
  align-items: flex-end;
}
.bubble .who {
  font-size: 0.6875rem;
  color: #999;
}
.bubble .txt {
  margin: 0;
  padding: 0.5rem 0.7rem;
  border-radius: 0.65rem;
  font-size: 0.875rem;
  line-height: 1.45;
  white-space: pre-wrap;
  word-break: break-word;
}
.bubble.is-seller .txt {
  background: #f3e8ff;
  color: #4c1d95;
  border-top-left-radius: 0.2rem;
}
.bubble.is-buyer .txt {
  background: #fce7f3;
  color: #9d174d;
  border-top-right-radius: 0.2rem;
}
.bubble time {
  font-size: 0.625rem;
  color: #bbb;
}
.chat-btn {
  margin-top: 0.85rem;
}
.popup-title {
  margin: 0 0 0.75rem;
  text-align: center;
  font-size: 1rem;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  padding: 1rem 0.75rem 1.25rem;
}
.actions .van-button {
  flex: 1 1 calc(50% - 0.5rem);
}
.order-detail .actions :deep(.van-button--primary) {
  background: linear-gradient(100deg, #4a2d7a 0%, #7d5bb6 55%, #a78bfa 100%);
  border: 0;
}
.order-detail .actions :deep(.van-button--plain) {
  background: rgba(255, 255, 255, 0.92);
  border-color: rgba(255, 255, 255, 0.65);
  color: var(--od-ink);
}
.order-detail .chat-btn.van-button--plain {
  border-color: var(--od-accent);
  color: var(--od-accent);
}
.as-row {
  width: 100%;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin: 0 0 0.65rem;
  padding: 0.65rem 0;
  border: 0;
  border-bottom: 1px solid var(--od-line);
  background: transparent;
  text-align: left;
  font: inherit;
  color: inherit;
}
.as-row:last-child {
  margin-bottom: 0;
  border-bottom: 0;
  padding-bottom: 0;
}
.as-main {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
}
.as-main strong {
  font-size: 0.875rem;
}
.as-meta,
.as-item,
.as-note {
  font-size: 0.75rem;
  color: var(--od-muted);
}
.as-note {
  color: #7c3aed;
}
.as-ev {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: 0.35rem;
}
.as-ev img {
  width: 2.75rem;
  height: 2.75rem;
  object-fit: cover;
  border-radius: 0.3rem;
  background: #f3eef9;
  pointer-events: none;
}
.as-status {
  flex-shrink: 0;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--od-muted);
}
.as-status.pending_review,
.as-status.awaiting_return,
.as-status.pending_refund {
  color: #7c3aed;
}
.as-status.completed {
  color: #15803d;
}
.as-status.rejected {
  color: #b91c1c;
}
</style>
