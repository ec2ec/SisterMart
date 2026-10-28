<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  NButton,
  NCard,
  NDescriptions,
  NDescriptionsItem,
  NImage,
  NImageGroup,
  NInput,
  NSpace,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import { orderStatusLabel } from '../utils/orderStatus'
import { assetUrl } from '../utils/assetUrl'

type OrderMsg = { id: number; sender: 'seller' | 'buyer'; body: string; created_at: string }

const route = useRoute()
const router = useRouter()
const message = useMessage()
const order = ref<any>(null)
const shipment = ref<any>(null)
const company = ref('順豐速運')
const tracking = ref('')
const nodeNote = ref('')
const buyerNote = ref('')
const noteBusy = ref(false)
const loadError = ref('')
const loading = ref(true)
const chat = ref<OrderMsg[]>([])

async function loadChat() {
  try {
    const data = await api<{ items: OrderMsg[] }>(`/api/v1/admin/orders/${route.params.id}/messages`, {}, true)
    chat.value = data.items || []
  } catch {
    chat.value = []
  }
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    order.value = await api(`/api/v1/admin/orders/${route.params.id}`, {}, true)
  } catch (e: any) {
    order.value = null
    loadError.value = e?.message || '訂單詳情加載失敗'
    loading.value = false
    return
  }
  try {
    // 未發貨時接口返回 null（不再 404），避免全局錯誤彈窗
    shipment.value = (await api(`/api/v1/admin/orders/${route.params.id}/shipment`, {}, true)) || null
  } catch {
    shipment.value = null
  }
  await loadChat()
  loading.value = false
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

async function sendBuyerNote() {
  const body = buyerNote.value.trim()
  if (!body) {
    message.warning('請填寫留言')
    return
  }
  if (body.length > 256) {
    message.warning('留言最多 256 字')
    return
  }
  noteBusy.value = true
  try {
    await api(
      `/api/v1/admin/orders/${route.params.id}/buyer-messages`,
      { method: 'POST', body: JSON.stringify({ body_zh: body, body_en: body }) },
      true,
    )
    buyerNote.value = ''
    message.success('已發送給買家')
    await loadChat()
  } finally {
    noteBusy.value = false
  }
}

function itemName(item: any) {
  const n = item?.name
  if (n && typeof n === 'object') return n['zh-HK'] || n.en || ''
  return String(n || '')
}

function itemSpec(item: any) {
  const s = item?.spec
  if (s && typeof s === 'object') return s['zh-HK'] || s.en || ''
  return String(s || '')
}

function aftersaleTypeLabel(t: string) {
  return t === 'return_refund' ? '退貨退款' : '僅退款'
}

function aftersaleStatusLabel(s: string) {
  return (
    {
      pending_review: '待審核',
      awaiting_return: '待退貨',
      pending_refund: '待退款',
      completed: '已完成',
      rejected: '已駁回',
      cancelled: '已撤銷',
    } as Record<string, string>
  )[s] || s
}

function openAftersale(id: number) {
  router.push({ path: '/sales/aftersales', query: { open: String(id) } })
}

const member = computed(() => {
  const o = order.value
  if (!o) return null
  if (o.member && typeof o.member === 'object') return o.member
  if (o.member_id) return { id: o.member_id, nickname: null, email: null, phone: null }
  return null
})

const memberLabel = computed(() => {
  const m = member.value
  if (!m) return '-'
  const parts = [m.nickname, m.email, m.phone].filter((x: unknown) => !!x)
  return parts.length ? parts.join(' / ') : '（無暱稱／郵件）'
})

function goMember(id: number) {
  router.push(`/customers/members/${id}`)
}
</script>

<template>
  <n-card v-if="loading" title="訂單詳情">加載中…</n-card>
  <n-card v-else-if="loadError" title="訂單詳情">
    <p style="color: #d03050">{{ loadError }}</p>
    <n-button style="margin-top: 1rem" @click="router.push('/sales/orders')">返回列表</n-button>
  </n-card>
  <n-card v-else-if="order" :title="`訂單 ${order.order_no}`">
    <n-descriptions label-placement="left" :column="2">
      <n-descriptions-item label="狀態">{{ orderStatusLabel(order.status) }}</n-descriptions-item>
      <n-descriptions-item label="實付">{{ order.pay_amount }}</n-descriptions-item>
      <n-descriptions-item label="會員 ID">
        <button
          v-if="member?.id"
          type="button"
          class="member-link"
          @click="goMember(member.id)"
        >
          #{{ member.id }}
        </button>
        <span v-else>-</span>
      </n-descriptions-item>
      <n-descriptions-item label="會員暱稱／郵件">{{ memberLabel }}</n-descriptions-item>
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
    <div v-for="(item, idx) in order.items || []" :key="idx" class="line">
      <img class="thumb" :src="assetUrl(item.image)" alt="" />
      <div>
        <div>{{ itemName(item) }} / {{ itemSpec(item) }}</div>
        <div>HK$ {{ item.unit_price }} × {{ item.qty }}（已退 {{ item.refunded_qty || 0 }}）= {{ item.line_amount }}</div>
        <div v-if="item.review" class="item-review">
          <div class="rev-stars">
            評價 {{ '★'.repeat(item.review.stars || 0) }}{{ '☆'.repeat(Math.max(0, 5 - (item.review.stars || 0))) }}
            <span v-if="item.review.moderation_status && item.review.moderation_status !== 'approved'" class="rev-mod">
              {{ item.review.moderation_status === 'rejected' ? '未通過' : '審核中' }}
            </span>
          </div>
          <div v-if="item.review.body" class="rev-body">{{ item.review.body }}</div>
          <n-image-group v-if="item.review.images?.length">
            <div class="rev-imgs">
              <n-image
                v-for="(img, i) in item.review.images"
                :key="i"
                :src="assetUrl(img)"
                width="56"
                height="56"
                object-fit="cover"
              />
            </div>
          </n-image-group>
          <div v-if="item.review.follow" class="rev-extra">追評：{{ item.review.follow }}</div>
          <div v-if="item.review.reply" class="rev-extra">商家回覆：{{ item.review.reply }}</div>
        </div>
        <div v-else-if="item.reviewed" class="rev-extra">已評價</div>
      </div>
    </div>

    <n-card
      v-if="(order.aftersales || []).length"
      size="small"
      title="售後記錄"
      style="margin-top: 1rem"
    >
      <button
        v-for="as in order.aftersales"
        :key="as.id"
        type="button"
        class="as-row"
        @click="openAftersale(as.id)"
      >
        <div class="as-main">
          <strong>{{ as.aftersale_no }}</strong>
          <span class="as-meta">
            {{ aftersaleTypeLabel(as.type) }} · HK$ {{ Number(as.amount).toFixed(2) }}
          </span>
          <span v-if="as.item" class="as-item">{{ itemName(as.item) }}</span>
          <span v-if="as.description" class="as-item">{{ as.description }}</span>
          <span v-if="as.admin_note" class="as-note">審核備註：{{ as.admin_note }}</span>
          <div v-if="as.evidence?.length" class="as-ev">
            <img
              v-for="(u, i) in as.evidence.slice(0, 3)"
              :key="i"
              :src="assetUrl(u)"
              alt=""
            />
          </div>
        </div>
        <n-tag size="small" :type="as.status === 'rejected' ? 'error' : as.status === 'completed' ? 'success' : 'warning'">
          {{ aftersaleStatusLabel(as.status) }}
        </n-tag>
      </button>
    </n-card>

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

    <n-card size="small" title="留言信息" style="margin-top: 1rem">
      <p class="chat-hint">左側為賣家留言，右側為買家留言。發送後會同步通知買家「訂單消息」。</p>
      <div v-if="!chat.length" class="chat-empty">暫無留言</div>
      <div v-else class="chat-list">
        <div
          v-for="m in chat"
          :key="m.id"
          class="bubble"
          :class="m.sender === 'buyer' ? 'is-buyer' : 'is-seller'"
        >
          <div class="who">{{ m.sender === 'buyer' ? '買家' : '賣家' }}</div>
          <div class="txt">{{ m.body }}</div>
          <div class="when">{{ m.created_at }}</div>
        </div>
      </div>
      <n-input
        v-model:value="buyerNote"
        type="textarea"
        placeholder="輸入留言（最多 256 字）"
        :rows="3"
        maxlength="256"
        show-count
        style="margin: 0.75rem 0 0.5rem"
      />
      <n-button type="primary" :loading="noteBusy" @click="sendBuyerNote">發送留言</n-button>
    </n-card>

    <n-button style="margin-top: 1rem" @click="router.push('/sales/orders')">返回列表</n-button>
  </n-card>
</template>

<style scoped>
.item-review {
  margin-top: 0.4rem;
  padding-top: 0.4rem;
  border-top: 1px dashed #e8e8e8;
  font-size: 0.8125rem;
  color: #444;
}
.rev-stars {
  color: #f59a23;
  letter-spacing: 0.04em;
}
.rev-mod {
  margin-left: 0.35rem;
  color: #b45309;
  font-size: 0.75rem;
}
.rev-body {
  margin-top: 0.25rem;
  white-space: pre-wrap;
  word-break: break-word;
}
.rev-imgs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: 0.35rem;
}
.rev-imgs :deep(.n-image) {
  border-radius: 0.25rem;
  overflow: hidden;
  background: #f3f0ea;
}
.rev-extra {
  margin-top: 0.25rem;
  color: #666;
}
.line {
  display: flex;
  gap: 0.75rem;
  margin: 0.5rem 0;
  align-items: flex-start;
}
.line .thumb {
  width: 3rem;
  height: 3rem;
  object-fit: cover;
  border-radius: 0.375rem;
  flex-shrink: 0;
  background: #f3f0ea;
}
.member-link {
  border: 0;
  background: transparent;
  color: #24315c;
  font: inherit;
  padding: 0;
  cursor: pointer;
  text-decoration: underline;
}
ul {
  padding-left: 1.25rem;
  margin: 0.5rem 0;
}
.chat-hint {
  color: #888;
  font-size: 0.8125rem;
  margin: 0 0 0.75rem;
}
.chat-empty {
  color: #aaa;
  font-size: 0.8125rem;
  text-align: center;
  padding: 0.75rem 0;
}
.chat-list {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  max-height: 22rem;
  overflow: auto;
  padding: 0.25rem 0.15rem;
  background: #fafafa;
  border-radius: 0.5rem;
}
.bubble {
  max-width: 78%;
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
  font-size: 0.75rem;
  color: #999;
}
.bubble .txt {
  padding: 0.5rem 0.7rem;
  border-radius: 0.55rem;
  font-size: 0.875rem;
  line-height: 1.45;
  white-space: pre-wrap;
  word-break: break-word;
}
.bubble.is-seller .txt {
  background: #e8eeff;
  color: #24315c;
}
.bubble.is-buyer .txt {
  background: #ffe8f1;
  color: #5a2340;
}
.bubble .when {
  font-size: 0.6875rem;
  color: #bbb;
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
  border-bottom: 1px solid #f0ebe4;
  background: transparent;
  text-align: left;
  font: inherit;
  color: inherit;
  cursor: pointer;
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
.as-item {
  font-size: 0.75rem;
  color: #888;
}
.as-note {
  font-size: 0.75rem;
  color: #b45309;
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
  background: #f3f0ea;
  pointer-events: none;
}
</style>
