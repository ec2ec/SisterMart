<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { NButton, NCard, NInput, NInputNumber, NRadio, NRadioGroup, useMessage } from 'naive-ui'
import { api } from '../api'

const message = useMessage()
const mode = ref<'exact' | 'fuzzy'>('fuzzy')
const form = reactive({
  aftersale_window_days: 7,
  cs_whatsapp: '',
  cs_email: '',
})

onMounted(async () => {
  const data = await api<{
    sold_display_mode: 'exact' | 'fuzzy'
    aftersale_window_days: number
    cs_whatsapp: string
    cs_email: string
  }>('/api/v1/admin/settings/shop', {}, true)
  mode.value = data.sold_display_mode
  form.aftersale_window_days = data.aftersale_window_days
  form.cs_whatsapp = data.cs_whatsapp || ''
  form.cs_email = data.cs_email || ''
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
        cs_email: form.cs_email.trim(),
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
      <label>H5 聯絡客服郵箱</label>
      <n-input
        v-model:value="form.cs_email"
        type="text"
        placeholder="hello@sistermart.hk"
        style="max-width: 20rem"
      />
      <p class="hint">用於會員中心「聯繫客服」發郵件；留空則回退系統 MAIL_FROM／默認郵箱</p>
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
.hint {
  margin: 0;
  font-size: 0.8125rem;
  color: #888;
  line-height: 1.4;
}
</style>
