<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { NCard, NDescriptions, NDescriptionsItem, NTag } from 'naive-ui'

const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'
const health = ref('檢查中…')
const ok = ref(false)

onMounted(async () => {
  try {
    const res = await fetch(`${apiBase}/api/v1/health`)
    const body = await res.json()
    ok.value = body.code === 0 && Boolean(body.data?.db?.connected)
    health.value = ok.value
      ? `ThinkPHP ${body.data.thinkphp} · MySQL 可連接`
      : String(body.data?.db?.error || body.message || '不可用')
  } catch {
    ok.value = false
    health.value = '接口無法連接'
  }
})
</script>

<template>
  <n-card title="概覽">
    <n-descriptions label-placement="left" :column="1">
      <n-descriptions-item label="接口">{{ apiBase }}</n-descriptions-item>
      <n-descriptions-item label="健康">
        <n-tag :type="ok ? 'success' : 'error'" size="small">{{ health }}</n-tag>
      </n-descriptions-item>
    </n-descriptions>
    <p class="note">訂單、待發貨、售後和提現數字在交易模塊接上之後顯示。這一輪已開放底部導航配置。</p>
  </n-card>
</template>

<style scoped>
.note {
  margin: 1rem 0 0;
  color: #6d675f;
}
</style>
