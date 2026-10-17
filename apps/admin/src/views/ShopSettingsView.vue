<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { NButton, NCard, NRadio, NRadioGroup, useMessage } from 'naive-ui'
import { api } from '../api'

const message = useMessage()
const mode = ref<'exact' | 'fuzzy'>('fuzzy')

onMounted(async () => {
  const data = await api<{ sold_display_mode: 'exact' | 'fuzzy' }>('/api/v1/admin/settings/sold-display', {}, true)
  mode.value = data.sold_display_mode
})

async function save() {
  await api(
    '/api/v1/admin/settings/sold-display',
    { method: 'PUT', body: JSON.stringify({ sold_display_mode: mode.value }) },
    true,
  )
  message.success('已售展示已保存')
}
</script>

<template>
  <n-card title="店鋪參數">
    <p>已售數量展示方式（商品卡與詳情共用）</p>
    <n-radio-group v-model:value="mode">
      <n-radio value="exact">精確（例如 88）</n-radio>
      <n-radio value="fuzzy">模糊（例如 80+）</n-radio>
    </n-radio-group>
    <div style="margin-top: 1rem">
      <n-button type="primary" @click="save">保存</n-button>
    </div>
  </n-card>
</template>
