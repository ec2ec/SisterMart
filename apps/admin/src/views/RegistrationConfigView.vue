<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import {
  NButton,
  NCard,
  NFormItem,
  NInputNumber,
  NSelect,
  NSpace,
  NSwitch,
  NTabPane,
  NTabs,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import WangSimpleEditor from '../components/WangSimpleEditor.vue'

type CouponOpt = {
  id: number
  code: string
  name: { 'zh-HK': string; en: string }
  status: number
}

type RegConfig = {
  registration_enabled: boolean
  auto_claim_coupons: boolean
  coupon_ids: number[]
  coupons: CouponOpt[]
  gift_balance: boolean
  gift_balance_amount: number
  gift_points: boolean
  gift_points_amount: number
  welcome_html: { 'zh-HK': string; en: string }
}

const message = useMessage()
const loading = ref(false)
const couponOptions = ref<{ label: string; value: number }[]>([])
const welcomeTab = ref<'zh-HK' | 'en'>('zh-HK')
const form = reactive({
  registration_enabled: true,
  auto_claim_coupons: false,
  coupon_ids: [] as number[],
  gift_balance: false,
  gift_balance_amount: 0,
  gift_points: false,
  gift_points_amount: 0,
  welcome_html_zh: '',
  welcome_html_en: '',
})

const couponHint = computed(() =>
  form.auto_claim_coupons ? '可多選；庫存不足或已停用的券在註冊時會自動跳過' : '開啟後可選擇要自動發放的優惠券',
)

async function loadCoupons() {
  const data = await api<{ items: CouponOpt[] }>('/api/v1/admin/coupons', {}, true)
  couponOptions.value = data.items.map((c) => ({
    value: c.id,
    label: `${c.code} · ${c.name['zh-HK'] || c.name.en}${c.status !== 1 ? '（停用）' : ''}`,
  }))
}

async function load() {
  const data = await api<RegConfig>('/api/v1/admin/settings/registration', {}, true)
  form.registration_enabled = data.registration_enabled
  form.auto_claim_coupons = data.auto_claim_coupons
  form.coupon_ids = [...(data.coupon_ids || [])]
  form.gift_balance = data.gift_balance
  form.gift_balance_amount = data.gift_balance_amount
  form.gift_points = data.gift_points
  form.gift_points_amount = data.gift_points_amount
  form.welcome_html_zh = data.welcome_html?.['zh-HK'] || ''
  form.welcome_html_en = data.welcome_html?.en || ''
}

async function save() {
  loading.value = true
  try {
    await api(
      '/api/v1/admin/settings/registration',
      {
        method: 'PUT',
        body: JSON.stringify({
          registration_enabled: form.registration_enabled,
          auto_claim_coupons: form.auto_claim_coupons,
          coupon_ids: form.auto_claim_coupons ? form.coupon_ids : [],
          gift_balance: form.gift_balance,
          gift_balance_amount: form.gift_balance ? form.gift_balance_amount : 0,
          gift_points: form.gift_points,
          gift_points_amount: form.gift_points ? form.gift_points_amount : 0,
          welcome_html: {
            'zh-HK': form.welcome_html_zh,
            en: form.welcome_html_en,
          },
        }),
      },
      true,
    )
    message.success('新用戶配置已保存')
    await load()
  } catch (e) {
    message.error(e instanceof Error ? e.message : '保存失敗')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  try {
    await Promise.all([loadCoupons(), load()])
  } catch {
    /* 保留默認 */
  }
})
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="新用戶配置">
      <p class="hint">
        控制前台是否開放註冊，以及新用戶註冊成功後是否自動領券、贈送餘額與積分。未開啟贈送時，新用戶餘額、積分與優惠券均為空。關閉註冊時入口仍可見，進入註冊頁會提示通道關閉。
      </p>

      <n-form-item label="開放註冊">
        <n-switch v-model:value="form.registration_enabled" />
        <span class="sw">{{ form.registration_enabled ? '已開放 — 前台可註冊' : '已關閉 — 註冊頁提示稍後再試' }}</span>
      </n-form-item>

      <n-form-item label="註冊後自動領取優惠券">
        <n-switch v-model:value="form.auto_claim_coupons" />
        <span class="sw">{{ form.auto_claim_coupons ? '已開啟' : '已關閉' }}</span>
      </n-form-item>
      <n-form-item v-if="form.auto_claim_coupons" :label="couponHint">
        <n-select
          v-model:value="form.coupon_ids"
          multiple
          filterable
          clearable
          placeholder="選擇一張或多張優惠券"
          :options="couponOptions"
          style="max-width: 36rem"
        />
      </n-form-item>

      <n-form-item label="贈送餘額">
        <n-switch v-model:value="form.gift_balance" />
        <span class="sw">{{ form.gift_balance ? '已開啟' : '已關閉' }}</span>
      </n-form-item>
      <n-form-item v-if="form.gift_balance" label="贈送餘額金額（HKD）">
        <n-input-number
          v-model:value="form.gift_balance_amount"
          :min="0"
          :max="999999"
          :precision="2"
          style="width: 12rem"
        />
      </n-form-item>

      <n-form-item label="贈送積分">
        <n-switch v-model:value="form.gift_points" />
        <span class="sw">{{ form.gift_points ? '已開啟' : '已關閉' }}</span>
      </n-form-item>
      <n-form-item v-if="form.gift_points" label="贈送積分數量">
        <n-input-number
          v-model:value="form.gift_points_amount"
          :min="0"
          :max="99999999"
          :precision="0"
          style="width: 12rem"
        />
      </n-form-item>
    </n-card>

    <n-card title="註冊歡迎內容">
      <p class="hint">新用戶註冊成功並進入首頁後彈出展示。支援富文本（圖文、列表、連結）。</p>
      <n-tabs v-model:value="welcomeTab" type="line" animated>
        <n-tab-pane name="zh-HK" tab="繁體中文">
          <WangSimpleEditor v-model="form.welcome_html_zh" placeholder="歡迎文案（繁中）…" :height="260" preset="admin" />
        </n-tab-pane>
        <n-tab-pane name="en" tab="English">
          <WangSimpleEditor v-model="form.welcome_html_en" placeholder="Welcome copy (EN)…" :height="260" preset="admin" />
        </n-tab-pane>
      </n-tabs>
    </n-card>

    <n-button type="primary" :loading="loading" @click="save">保存</n-button>
  </n-space>
</template>

<style scoped>
.hint {
  margin: 0 0 1rem;
  color: var(--n-text-color-3);
  font-size: 0.9rem;
  line-height: 1.5;
}
.sw {
  margin-left: 0.75rem;
  color: var(--n-text-color-2);
  font-size: 0.875rem;
}
</style>
