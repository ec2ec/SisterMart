<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { NButton, NCard, NSpace, NTag, NSpin } from 'naive-ui'
import { api } from '../api'

type Summary = {
  today: { order_count: number; paid_amount: number }
  todo: {
    pending_shipment: number
    pending_aftersale: number
    pending_withdrawal: number
    pending_review: number
  }
  alerts: {
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
        <div class="stats">
          <button type="button" class="stat" @click="go('/sales/orders')">
            <span class="label">今日訂單</span>
            <span class="value">{{ summary.today.order_count }}</span>
          </button>
          <button type="button" class="stat" @click="go('/sales/orders')">
            <span class="label">今日實付</span>
            <span class="value money">{{ summary.today.paid_amount.toFixed(2) }}</span>
          </button>
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

        <div class="row">
          <n-space :size="8" wrap>
            <n-tag
              size="small"
              :type="summary.alerts.low_stock_skus > 0 ? 'warning' : 'default'"
              style="cursor: pointer"
              @click="go('/catalog/products')"
            >
              低庫存 SKU ≤{{ summary.alerts.low_stock_threshold }}：{{ summary.alerts.low_stock_skus }}
            </n-tag>
            <n-tag
              size="small"
              :type="summary.alerts.maintenance_enabled ? 'error' : 'success'"
              style="cursor: pointer"
              @click="go('/settings/maintenance')"
            >
              維護模式：{{ summary.alerts.maintenance_enabled ? '開啟' : '關閉' }}
            </n-tag>
            <span class="muted">更新 {{ summary.generated_at }}</span>
          </n-space>
        </div>

        <div class="quick">
          <span class="qlabel">快捷</span>
          <n-button size="tiny" quaternary @click="go('/sales/orders', { status: 'pending_shipment' })">
            發貨
          </n-button>
          <n-button size="tiny" quaternary @click="go('/sales/aftersales', { status: 'pending_review' })">
            售後
          </n-button>
          <n-button
            size="tiny"
            quaternary
            @click="go('/marketing/distribution', { tab: 'withdrawals', status: 'pending_review' })"
          >
            提現
          </n-button>
          <n-button size="tiny" quaternary @click="go('/sales/reviews', { moderation_status: 'pending' })">
            評價
          </n-button>
          <n-button size="tiny" quaternary @click="go('/catalog/products')">商品</n-button>
          <n-button size="tiny" quaternary @click="go('/cms/home')">首頁運營</n-button>
          <n-button size="tiny" quaternary @click="go('/settings/shop')">店鋪參數</n-button>
        </div>
      </template>
    </n-card>
  </n-spin>
</template>

<style scoped>
.dash :deep(.n-card__content) {
  padding-top: 0.75rem;
}
.err {
  margin: 0 0 0.75rem;
  color: #d03050;
  font-size: 0.875rem;
}
.stats {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 0.5rem;
}
.stat {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.15rem;
  margin: 0;
  padding: 0.55rem 0.65rem;
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
  font-size: 1.35rem;
  font-weight: 650;
  line-height: 1.2;
  color: #2a2620;
}
.value.money {
  font-size: 1.15rem;
}
.row {
  margin-top: 0.75rem;
}
.quick {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.15rem;
  margin-top: 0.65rem;
  padding-top: 0.55rem;
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
@media (max-width: 960px) {
  .stats {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 560px) {
  .stats {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
