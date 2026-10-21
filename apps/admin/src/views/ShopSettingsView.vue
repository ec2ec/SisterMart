<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { NButton, NCard, NInput, NInputNumber, NRadio, NRadioGroup, useMessage } from 'naive-ui'
import { api } from '../api'

const message = useMessage()
const mode = ref<'exact' | 'fuzzy'>('fuzzy')
const form = reactive({
  aftersale_window_days: 30,
  cs_whatsapp: '',
  cs_facebook_url: '',
})

onMounted(async () => {
  const data = await api<{
    sold_display_mode: 'exact' | 'fuzzy'
    aftersale_window_days: number
    cs_whatsapp: string
    cs_facebook_url?: string
  }>('/api/v1/admin/settings/shop', {}, true)
  mode.value = data.sold_display_mode
  form.aftersale_window_days = data.aftersale_window_days
  form.cs_whatsapp = data.cs_whatsapp || ''
  form.cs_facebook_url = data.cs_facebook_url || ''
})

async function saveSold() {
  await api(
    '/api/v1/admin/settings/sold-display',
    { method: 'PUT', body: JSON.stringify({ sold_display_mode: mode.value }) },
    true,
  )
  message.success('已售展示已保存')
}

async function saveShop() {
  await api(
    '/api/v1/admin/settings/shop',
    {
      method: 'PUT',
      body: JSON.stringify({
        aftersale_window_days: form.aftersale_window_days,
        cs_whatsapp: form.cs_whatsapp,
        cs_facebook_url: form.cs_facebook_url,
      }),
    },
    true,
  )
  message.success('售後與客服參數已保存')
}
</script>

<template>
  <n-card title="店鋪參數" style="margin-bottom: 1rem">
    <p>已售數量展示方式（商品卡與詳情共用）</p>
    <n-radio-group v-model:value="mode">
      <n-radio value="exact">精確（例如 88）</n-radio>
      <n-radio value="fuzzy">模糊（例如 80+）</n-radio>
    </n-radio-group>
    <div style="margin-top: 1rem">
      <n-button type="primary" @click="saveSold">保存已售展示</n-button>
    </div>
  </n-card>

  <n-card title="售後與客服">
    <div class="row">
      <label>售後有效期（天，自訂單完成日起）</label>
      <n-input-number v-model:value="form.aftersale_window_days" :min="1" :max="365" style="width: 10rem" />
    </div>
    <div class="row">
      <label>客服 WhatsApp（E.164，如 85290001234）</label>
      <n-input v-model:value="form.cs_whatsapp" placeholder="85290001234" style="max-width: 20rem" />
    </div>
    <div class="row">
      <label>Facebook 粉絲頁（完整 URL）</label>
      <n-input
        v-model:value="form.cs_facebook_url"
        placeholder="https://www.facebook.com/…"
        style="max-width: 28rem"
      />
    </div>
    <n-button type="primary" @click="saveShop">保存售後與客服</n-button>
  </n-card>
</template>

<style scoped>
.row {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 1rem;
}
</style>
