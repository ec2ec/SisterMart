<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  NButton,
  NCard,
  NDescriptions,
  NDescriptionsItem,
  NForm,
  NFormItem,
  NInput,
  NInputNumber,
  NModal,
  NSpace,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import { assetUrl } from '../utils/assetUrl'
import { formatRelativeZh } from '../utils/relativeTime'
import { orderStatusLabel } from '../utils/orderStatus'

type Member = {
  id: number
  email: string | null
  phone: string | null
  nickname: string | null
  avatar_url?: string | null
  promo_code: string
  balance: number
  points: number
  total_spent: number
  status: number
  locale?: string
  created_at?: string | null
  last_active_at?: string | null
  level?: { stars: number; name: { 'zh-HK': string } }
  coupon_count?: number
}

type Address = {
  id: number
  receiver: string
  phone: string
  region: string
  district: string
  area?: string
  detail: string
  is_default?: number | boolean
}

type OrderRow = {
  id: number
  order_no: string
  status: string
  pay_amount: number
  created_at: string
  title?: string
}

type LogRow = {
  id: number
  delta: number
  balance_after?: number
  type: string
  remark?: string
  created_at: string
}

const route = useRoute()
const router = useRouter()
const message = useMessage()

const loading = ref(true)
const loadError = ref('')
const member = ref<Member | null>(null)
const addresses = ref<Address[]>([])
const recentOrders = ref<OrderRow[]>([])
const pointLogs = ref<LogRow[]>([])
const balanceLogs = ref<LogRow[]>([])

const showAdjust = ref(false)
const adjust = reactive({ points_delta: 0, balance_delta: 0, reason: '' })
const statusBusy = ref(false)

const memberId = computed(() => Number(route.params.id || 0))

const accountLabel = computed(() => {
  const m = member.value
  if (!m) return ''
  const parts = [m.nickname, m.email, m.phone].filter(Boolean)
  return parts.join(' · ') || `會員 #${m.id}`
})

async function load() {
  const id = memberId.value
  if (!id) {
    loadError.value = '無效的會員 ID'
    loading.value = false
    return
  }
  loading.value = true
  loadError.value = ''
  try {
    const data = await api<{
      member: Member
      addresses?: Address[]
      recent_orders?: OrderRow[]
      point_logs?: LogRow[]
      balance_logs?: LogRow[]
    }>(`/api/v1/admin/members/${id}`, {}, true)
    member.value = data.member
    addresses.value = data.addresses || []
    recentOrders.value = data.recent_orders || []
    pointLogs.value = data.point_logs || []
    balanceLogs.value = data.balance_logs || []
  } catch (e: any) {
    member.value = null
    loadError.value = e?.message || '加載失敗'
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(memberId, load)

function openAdjust() {
  adjust.points_delta = 0
  adjust.balance_delta = 0
  adjust.reason = ''
  showAdjust.value = true
}

async function submitAdjust() {
  if (!member.value) return
  await api(
    `/api/v1/admin/members/${member.value.id}/asset-adjustments`,
    { method: 'POST', body: JSON.stringify(adjust) },
    true,
  )
  message.success('已調整')
  showAdjust.value = false
  await load()
}

async function toggleStatus() {
  if (!member.value) return
  const next = member.value.status === 1 ? 0 : 1
  const label = next === 1 ? '啟用' : '禁用'
  if (!window.confirm(`確定要${label}會員 #${member.value.id}？`)) return
  statusBusy.value = true
  try {
    const data = await api<{ member: Member }>(
      `/api/v1/admin/members/${member.value.id}/status`,
      { method: 'PUT', body: JSON.stringify({ status: next }) },
      true,
    )
    member.value = { ...member.value, ...data.member }
    message.success(`已${label}`)
  } finally {
    statusBusy.value = false
  }
}

function activeLabel(at?: string | null) {
  if (!at) return '-'
  return formatRelativeZh(at) || at
}
</script>

<template>
  <n-card v-if="loading" title="會員詳情">加載中…</n-card>
  <n-card v-else-if="loadError" title="會員詳情">
    <p style="color: #d03050">{{ loadError }}</p>
    <n-button style="margin-top: 1rem" @click="router.push('/customers/members')">返回列表</n-button>
  </n-card>
  <template v-else-if="member">
    <n-card :title="`會員 #${member.id}`">
      <template #header-extra>
        <n-space>
          <n-button @click="router.push('/customers/members')">返回列表</n-button>
          <n-button type="warning" color="#f0851a" @click="openAdjust">調整資產</n-button>
          <n-button
            :type="member.status === 1 ? 'error' : 'primary'"
            :loading="statusBusy"
            @click="toggleStatus"
          >
            {{ member.status === 1 ? '禁用帳號' : '啟用帳號' }}
          </n-button>
        </n-space>
      </template>

      <div class="head">
        <img
          v-if="member.avatar_url"
          class="avatar"
          :src="assetUrl(member.avatar_url)"
          alt=""
        />
        <div v-else class="avatar placeholder">{{ (member.nickname || member.email || '#')[0] }}</div>
        <div>
          <div class="name">{{ accountLabel }}</div>
          <n-space size="small" style="margin-top: 0.35rem">
            <n-tag size="small" :type="member.status === 1 ? 'success' : 'error'">
              {{ member.status === 1 ? '正常' : '已禁用' }}
            </n-tag>
            <n-tag size="small">
              {{ '★'.repeat(member.level?.stars || 1) }}
            </n-tag>
            <n-tag size="small" :bordered="false">推廣碼 {{ member.promo_code }}</n-tag>
          </n-space>
        </div>
      </div>

      <n-descriptions label-placement="left" :column="2" style="margin-top: 1rem">
        <n-descriptions-item label="暱稱">{{ member.nickname || '-' }}</n-descriptions-item>
        <n-descriptions-item label="郵件">{{ member.email || '-' }}</n-descriptions-item>
        <n-descriptions-item label="手機">{{ member.phone || '-' }}</n-descriptions-item>
        <n-descriptions-item label="語言">{{ member.locale || '-' }}</n-descriptions-item>
        <n-descriptions-item label="餘額">{{ Number(member.balance).toFixed(2) }}</n-descriptions-item>
        <n-descriptions-item label="積分">{{ member.points }}</n-descriptions-item>
        <n-descriptions-item label="累計實付">{{ Number(member.total_spent || 0).toFixed(2) }}</n-descriptions-item>
        <n-descriptions-item label="未用券">{{ member.coupon_count ?? 0 }}</n-descriptions-item>
        <n-descriptions-item label="註冊時間">{{ member.created_at || '-' }}</n-descriptions-item>
        <n-descriptions-item label="最後活躍">
          <span :title="member.last_active_at || ''">{{ activeLabel(member.last_active_at) }}</span>
        </n-descriptions-item>
      </n-descriptions>
    </n-card>

    <n-card title="近期訂單" size="small" style="margin-top: 1rem">
      <template #header-extra>
        <n-button
          size="tiny"
          @click="router.push({ path: '/sales/orders', query: { member_id: String(member.id) } })"
        >
          查看全部
        </n-button>
      </template>
      <p v-if="!recentOrders.length" class="muted">暫無訂單</p>
      <table v-else class="table">
        <thead>
          <tr>
            <th>單號</th>
            <th>商品</th>
            <th>實付</th>
            <th>狀態</th>
            <th>時間</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in recentOrders" :key="row.id">
            <td>{{ row.order_no }}</td>
            <td>{{ row.title || '-' }}</td>
            <td>{{ row.pay_amount }}</td>
            <td><n-tag size="small">{{ orderStatusLabel(row.status) }}</n-tag></td>
            <td>{{ row.created_at }}</td>
            <td>
              <n-button size="tiny" @click="router.push(`/sales/orders/${row.id}`)">詳情</n-button>
            </td>
          </tr>
        </tbody>
      </table>
    </n-card>

    <n-card title="收貨地址" size="small" style="margin-top: 1rem">
      <p v-if="!addresses.length" class="muted">暫無地址</p>
      <div v-for="a in addresses" :key="a.id" class="addr">
        <div>
          <strong>{{ a.receiver }}</strong> {{ a.phone }}
          <n-tag v-if="a.is_default" size="tiny" type="info" style="margin-left: 0.35rem">默認</n-tag>
        </div>
        <div class="muted">
          {{ a.region }} {{ a.district }} {{ a.area }} {{ a.detail }}
        </div>
      </div>
    </n-card>

    <div class="logs">
      <n-card title="積分流水" size="small">
        <p v-if="!pointLogs.length" class="muted">暫無記錄</p>
        <table v-else class="table compact">
          <thead>
            <tr>
              <th>變動</th>
              <th>餘額</th>
              <th>類型</th>
              <th>時間</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in pointLogs" :key="row.id">
              <td :class="row.delta >= 0 ? 'pos' : 'neg'">{{ row.delta > 0 ? '+' : '' }}{{ row.delta }}</td>
              <td>{{ row.balance_after }}</td>
              <td>{{ row.remark || row.type }}</td>
              <td>{{ row.created_at }}</td>
            </tr>
          </tbody>
        </table>
      </n-card>
      <n-card title="餘額流水" size="small">
        <p v-if="!balanceLogs.length" class="muted">暫無記錄</p>
        <table v-else class="table compact">
          <thead>
            <tr>
              <th>變動</th>
              <th>餘額</th>
              <th>類型</th>
              <th>時間</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in balanceLogs" :key="row.id">
              <td :class="row.delta >= 0 ? 'pos' : 'neg'">
                {{ row.delta > 0 ? '+' : '' }}{{ Number(row.delta).toFixed(2) }}
              </td>
              <td>{{ Number(row.balance_after || 0).toFixed(2) }}</td>
              <td>{{ row.remark || row.type }}</td>
              <td>{{ row.created_at }}</td>
            </tr>
          </tbody>
        </table>
      </n-card>
    </div>

    <n-modal v-model:show="showAdjust" preset="card" title="調整會員資產" style="width: 28rem" :mask-closable="false" :close-on-esc="false">
      <p>會員 #{{ member.id }} · {{ accountLabel }}</p>
      <n-form label-placement="left" label-width="6rem">
        <n-form-item label="積分變動"><n-input-number v-model:value="adjust.points_delta" /></n-form-item>
        <n-form-item label="餘額變動"><n-input-number v-model:value="adjust.balance_delta" :step="0.01" /></n-form-item>
        <n-form-item label="原因"><n-input v-model:value="adjust.reason" type="textarea" placeholder="必填" /></n-form-item>
      </n-form>
      <n-button type="primary" @click="submitAdjust">確認調整</n-button>
    </n-modal>
  </template>
</template>

<style scoped>
.head {
  display: flex;
  gap: 0.85rem;
  align-items: center;
}
.avatar {
  width: 3.5rem;
  height: 3.5rem;
  border-radius: 50%;
  object-fit: cover;
  background: #f3f0ea;
  flex-shrink: 0;
}
.avatar.placeholder {
  display: grid;
  place-items: center;
  font-weight: 600;
  color: #666;
  text-transform: uppercase;
}
.name {
  font-size: 1rem;
  font-weight: 600;
}
.muted {
  color: #999;
  font-size: 0.8125rem;
  margin: 0.25rem 0;
}
.addr {
  padding: 0.5rem 0;
  border-bottom: 1px solid #f0ebe4;
}
.addr:last-child {
  border-bottom: 0;
}
.logs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-top: 1rem;
}
@media (max-width: 900px) {
  .logs {
    grid-template-columns: 1fr;
  }
}
.table {
  width: 100%;
  border-collapse: collapse;
}
.table th,
.table td {
  border-bottom: 1px solid #eee;
  padding: 0.45rem 0.35rem;
  text-align: left;
  font-size: 0.8125rem;
}
.table.compact th,
.table.compact td {
  font-size: 0.75rem;
}
.pos {
  color: #15803d;
}
.neg {
  color: #b91c1c;
}
</style>
