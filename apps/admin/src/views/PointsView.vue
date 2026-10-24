<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NCard, NForm, NFormItem, NInputNumber, NTabPane, NTabs, useMessage } from 'naive-ui'
import { api } from '../api'
import PointsMallAdminView from './PointsMallAdminView.vue'

const route = useRoute()
const router = useRouter()
const message = useMessage()
const tab = ref(route.query.tab === 'mall' ? 'mall' : 'settings')
const form = reactive({
  per_hkd: 100,
  max_percent: 50,
  order_earn_per_hkd: 1,
})

watch(tab, (v) => {
  const next = v === 'mall' ? { tab: 'mall' } : {}
  router.replace({ path: '/marketing/points', query: next })
})

watch(
  () => route.query.tab,
  (v) => {
    tab.value = v === 'mall' ? 'mall' : 'settings'
  },
)

onMounted(async () => {
  const data = await api<typeof form>('/api/v1/admin/points/settings', {}, true)
  Object.assign(form, data)
})

async function save() {
  await api('/api/v1/admin/points/settings', { method: 'PUT', body: JSON.stringify(form) }, true)
  message.success('積分設置已保存')
}
</script>

<template>
  <n-tabs v-model:value="tab" type="line">
    <n-tab-pane name="settings" tab="積分設置">
      <n-card title="積分設置">
        <p style="color: #666; margin-bottom: 1rem">抵扣比例、上限與下單完成發放（運費不計）。無充值入口。</p>
        <n-form label-placement="left" label-width="12rem" style="max-width: 28rem">
          <n-form-item label="抵扣：每 HKD 需積分">
            <n-input-number v-model:value="form.per_hkd" :min="1" />
          </n-form-item>
          <n-form-item label="最多抵扣商品 %">
            <n-input-number v-model:value="form.max_percent" :min="0" :max="100" />
          </n-form-item>
          <n-form-item label="完成訂單每 HKD 發積分">
            <n-input-number v-model:value="form.order_earn_per_hkd" :min="0" />
          </n-form-item>
          <n-button type="primary" @click="save">保存</n-button>
        </n-form>
      </n-card>
    </n-tab-pane>
    <n-tab-pane name="mall" tab="積分商城商品">
      <PointsMallAdminView />
    </n-tab-pane>
  </n-tabs>
</template>
