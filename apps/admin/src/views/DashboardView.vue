<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NCard, NSpace, NTag, NSpin } from 'naive-ui'
import { api } from '../api'

type Compare = { delta: number; pct: number | null }

type Summary = {
  today: { order_count: number; paid_amount: number; new_members: number }
  yesterday: { order_count: number; paid_amount: number; new_members: number }
  compare: {
    order_count: Compare
    paid_amount: Compare
    new_members: Compare
  }
  todo: {
    pending_shipment: number
    pending_aftersale: number
    pending_withdrawal: number
    pending_review: number
  }
  shop: {
    on_sale_products: number
    low_stock_skus: number
    low_stock_threshold: number
    maintenance_enabled: boolean
  }
  generated_at: string
}

const router = useRouter()
const loading = ref(true)
const loadError = ref('')
const summary = ref<Summary | null>(null)
const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'
const health = ref('檢查中…')
const healthOk = ref(false)

async function loadHealth() {
  try {
    const res = await fetch(`${apiBase}/api/v1/health`)
    const body = await res.json()
    healthOk.value = body.code === 0 && Boolean(body.data?.db?.connected)
    health.value = healthOk.value
      ? `API 正常 · MySQL 可連`
      : String(body.data?.db?.error || body.message || '不可用')
  } catch {
    healthOk.value = false
    health.value = '接口無法連接'
  }
}

async function loadSummary() {
  loadError.value = ''
  loading.value = true
  try {
    summary.value = await api<Summary>('/api/v1/admin/dashboard/summary', {}, true)
  } catch (e: any) {
    loadError.value = e?.message || '加載失敗'
    summary.value = null
  } finally {
    loading.value = false
  }
}

async function refresh() {
  await Promise.all([loadHealth(), loadSummary()])
}

function go(path: string, query?: Record<string, string>) {
  router.push({ path, query })
}

function fmtDelta(c: Compare, money = false): string {
  const sign = c.delta > 0 ? '+' : ''
  const num = money ? c.delta.toFixed(2) : String(c.delta)
  if (c.pct === null) return `較昨 ${sign}${num}`
  return `較昨 ${sign}${num}（${c.pct > 0 ? '+' : ''}${c.pct}%）`
}

function deltaClass(c: Compare): string {
  if (c.delta > 0) return 'up'
  if (c.delta < 0) return 'down'
  return 'flat'
}

onMounted(refresh)
</script>

<template>
  <n-spin :show="loading">
    <n-card size="small" title="概覽" class="dash">
      <template #header-extra>
        <n-space align="center" :size="8">
          <n-tag :type="healthOk ? 'success' : 'error'" size="small">{{ health }}</n-tag>
          <n-button size="tiny" @click="refresh">刷新</n-button>
        </n-space>
      </template>

      <p v-if="loadError" class="err">{{ loadError }}</p>

      <template v-if="summary">
        <!-- P0 / P1 待辦 -->
        <div class="section-label">待辦</div>
        <div class="stats stats-4">
          <button
            type="button"
            class="stat"
            :class="{ warn: summary.todo.pending_shipment > 0 }"
            @click="go('/sales/orders', { status: 'pending_shipment' })"
          >
            <span class="label">待發貨</span>
            <span class="value">{{ summary.todo.pending_shipment }}</span>
          </button>
          <button
            type="button"
            class="stat"
            :class="{ warn: summary.todo.pending_aftersale > 0 }"
            @click="go('/sales/aftersales', { status: 'pending_review' })"
          >
            <span class="label">待審售後</span>
            <span class="value">{{ summary.todo.pending_aftersale }}</span>
          </button>
          <button
            type="button"
            class="stat"
            :class="{ warn: summary.todo.pending_withdrawal > 0 }"
            @click="go('/marketing/distribution', { tab: 'withdrawals', status: 'pending_review' })"
          >
            <span class="label">待審提現</span>
            <span class="value">{{ summary.todo.pending_withdrawal }}</span>
          </button>
          <button
            type="button"
            class="stat"
            :class="{ warn: summary.todo.pending_review > 0 }"
            @click="go('/sales/reviews', { moderation_status: 'pending' })"
          >
            <span class="label">待審評價</span>
            <span class="value">{{ summary.todo.pending_review }}</span>
          </button>
        </div>

        <!-- P1 今日業績 + P2 昨日對比 -->
        <div class="section-label">今日</div>
        <div class="stats stats-3">
          <button type="button" class="stat" @click="go('/sales/orders')">
            <span class="label">訂單</span>
            <span class="value">{{ summary.today.order_count }}</span>
            <span class="delta" :class="deltaClass(summary.compare.order_count)">
              {{ fmtDelta(summary.compare.order_count) }}
            </span>
          </button>
          <button type="button" class="stat" @click="go('/sales/orders')">
            <span class="label">實付 HKD</span>
            <span class="value money">{{ summary.today.paid_amount.toFixed(2) }}</span>
            <span class="delta" :class="deltaClass(summary.compare.paid_amount)">
              {{ fmtDelta(summary.compare.paid_amount, true) }}
            </span>
          </button>
          <button type="button" class="stat" @click="go('/customers/members')">
            <span class="label">新會員</span>
            <span class="value">{{ summary.today.new_members }}</span>
            <span class="delta" :class="deltaClass(summary.compare.new_members)">
              {{ fmtDelta(summary.compare.new_members) }}
            </span>
          </button>
        </div>

        <!-- P2 / P3 店況 -->
        <div class="section-label">店況</div>
        <div class="row">
          <n-space :size="8" wrap>
            <n-tag size="small" style="cursor: pointer" @click="go('/catalog/products')">
              在售商品：{{ summary.shop.on_sale_products }}
            </n-tag>
            <n-tag
              size="small"
              :type="summary.shop.low_stock_skus > 0 ? 'warning' : 'default'"
              style="cursor: pointer"
              @click="go('/catalog/products')"
            >
              低庫存 SKU ≤{{ summary.shop.low_stock_threshold }}：{{ summary.shop.low_stock_skus }}
            </n-tag>
            <n-tag
              size="small"
              :type="summary.shop.maintenance_enabled ? 'error' : 'success'"
              style="cursor: pointer"
              @click="go('/settings/maintenance')"
            >
              維護：{{ summary.shop.maintenance_enabled ? '開啟' : '關閉' }}
            </n-tag>
            <span class="muted">更新 {{ summary.generated_at }}</span>
          </n-space>
        </div>

        <!-- P1 / P3 快捷入口 -->
        <div class="quick">
          <span class="qlabel">快捷</span>
          <n-button size="tiny" quaternary @click="go('/sales/orders')">訂單</n-button>
          <n-button size="tiny" quaternary @click="go('/sales/orders', { status: 'pending_shipment' })">
            發貨
          </n-button>
          <n-button size="tiny" quaternary @click="go('/sales/aftersales', { status: 'pending_review' })">
            售後
          </n-button>
          <n-button size="tiny" quaternary @click="go('/catalog/products/new')">新建商品</n-button>
          <n-button size="tiny" quaternary @click="go('/home-ops/layout')">首頁運營</n-button>
          <n-button size="tiny" quaternary @click="go('/marketing/seckill')">秒殺</n-button>
          <n-button size="tiny" quaternary @click="go('/cms/articles')">文章</n-button>
          <n-button size="tiny" quaternary @click="go('/customers/members')">會員</n-button>
          <n-button size="tiny" quaternary @click="go('/settings/maintenance')">維護</n-button>
        </div>
      </template>
    </n-card>
  </n-spin>
</template>

<style scoped>
.dash :deep(.n-card__content) {
  padding-top: 0.65rem;
}
.err {
  margin: 0 0 0.75rem;
  color: #d03050;
  font-size: 0.875rem;
}
.section-label {
  margin: 0.55rem 0 0.35rem;
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  color: #9a948a;
  text-transform: none;
}
.section-label:first-of-type {
  margin-top: 0;
}
.stats {
  display: grid;
  gap: 0.5rem;
}
.stats-4 {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}
.stats-3 {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}
.stat {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.1rem;
  margin: 0;
  padding: 0.5rem 0.6rem;
  border: 1px solid #e8e4dc;
  border-radius: 6px;
  background: #faf8f4;
  cursor: pointer;
  text-align: left;
  font: inherit;
  transition: border-color 0.15s, background 0.15s;
}
.stat:hover {
  border-color: #c4b8a4;
  background: #f3efe6;
}
.stat.warn {
  border-color: #f0c36d;
  background: #fff8e8;
}
.label {
  font-size: 0.75rem;
  color: #6d675f;
}
.value {
  font-size: 1.3rem;
  font-weight: 650;
  line-height: 1.15;
  color: #2a2620;
}
.value.money {
  font-size: 1.1rem;
}
.delta {
  font-size: 0.68rem;
  line-height: 1.3;
  color: #9a948a;
}
.delta.up {
  color: #18a058;
}
.delta.down {
  color: #d03050;
}
.row {
  margin-top: 0.15rem;
}
.quick {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.1rem;
  margin-top: 0.65rem;
  padding-top: 0.5rem;
  border-top: 1px solid #eee;
}
.qlabel {
  font-size: 0.75rem;
  color: #6d675f;
  margin-right: 0.25rem;
}
.muted {
  font-size: 0.75rem;
  color: #9a948a;
}
@media (max-width: 900px) {
  .stats-4 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .stats-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 560px) {
  .stats-3 {
    grid-template-columns: 1fr;
  }
}
</style>
