<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NConfigProvider,
  NDescriptions,
  NDescriptionsItem,
  NSpace,
  NTag,
  NText,
} from 'naive-ui'

const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'
const healthText = ref('checking…')
const healthOk = ref(false)

const statusType = computed(() => (healthOk.value ? 'success' : 'error'))

onMounted(async () => {
  try {
    const res = await fetch(`${apiBase}/health`)
    const data = await res.json()
    healthOk.value = Boolean(data.ok)
    healthText.value = data.ok
      ? `ThinkPHP ${data.thinkphp} · MySQL ${data.db?.ok ? 'ok' : 'fail'}`
      : String(data.db?.error ?? 'unhealthy')
  } catch {
    healthOk.value = false
    healthText.value = `unreachable: ${apiBase}`
  }
})
</script>

<template>
  <n-config-provider>
    <div class="shell">
      <header class="top">
        <div>
          <p class="brand">SisterMart</p>
          <n-text depth="3">Admin scaffold · Vue 3 + Naive UI</n-text>
        </div>
        <n-tag :type="statusType" size="small">M1</n-tag>
      </header>

      <n-card title="Environment" size="small" class="card">
        <n-descriptions label-placement="left" :column="1">
          <n-descriptions-item label="API">{{ apiBase }}</n-descriptions-item>
          <n-descriptions-item label="Health">{{ healthText }}</n-descriptions-item>
          <n-descriptions-item label="i18n">zh-HK / en（自 M2）</n-descriptions-item>
        </n-descriptions>
        <n-space style="margin-top: 1rem">
          <n-button type="primary">Naive UI ready</n-button>
          <n-button quaternary>No business modules yet</n-button>
        </n-space>
      </n-card>
    </div>
  </n-config-provider>
</template>

<style scoped>
.shell {
  min-height: 100vh;
  padding: 2rem clamp(1rem, 4vw, 3rem);
  background:
    linear-gradient(135deg, #eef2f6 0%, #e7ece8 48%, #f4efe6 100%);
  font-family: "IBM Plex Sans", "Noto Sans", "Helvetica Neue", sans-serif;
}

.top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.brand {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  color: #1c2430;
}

.card {
  max-width: 40rem;
}
</style>
