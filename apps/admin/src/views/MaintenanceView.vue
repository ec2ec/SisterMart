<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import {
  NButton,
  NCard,
  NFormItem,
  NInput,
  NSpace,
  NSwitch,
  useMessage,
} from 'naive-ui'
import { api } from '../api'

type Maint = {
  enabled: boolean
  starts_at: string | null
  ends_at: string | null
  title: { 'zh-HK': string; en: string }
  message: { 'zh-HK': string; en: string }
}

const message = useMessage()
const loading = ref(false)
const form = reactive({
  enabled: false,
  starts_at: '2026-12-12T12:35',
  ends_at: '2026-12-13T03:30',
  title_zh: '商店維護中',
  title_en: 'Under Maintenance',
  message_zh: '暫時不能為你提供服務。感謝你的支持，我們會盡快回來。',
  message_en: 'We are temporarily unavailable. Thank you for your support — we will be back soon.',
})

function toInput(dt: string | null) {
  if (!dt) return ''
  return dt.replace(' ', 'T').slice(0, 16)
}

function fromInput(v: string) {
  if (!v) return null
  return v.replace('T', ' ') + (v.length === 16 ? ':00' : '')
}

async function load() {
  const data = await api<Maint>('/api/v1/admin/settings/maintenance', {}, true)
  form.enabled = data.enabled
  form.starts_at = toInput(data.starts_at)
  form.ends_at = toInput(data.ends_at)
  form.title_zh = data.title['zh-HK']
  form.title_en = data.title.en
  form.message_zh = data.message['zh-HK']
  form.message_en = data.message.en
}

async function save() {
  loading.value = true
  try {
    await api(
      '/api/v1/admin/settings/maintenance',
      {
        method: 'PUT',
        body: JSON.stringify({
          enabled: form.enabled,
          starts_at: fromInput(form.starts_at),
          ends_at: fromInput(form.ends_at),
          title: { 'zh-HK': form.title_zh, en: form.title_en },
          message: { 'zh-HK': form.message_zh, en: form.message_en },
        }),
      },
      true,
    )
    message.success(form.enabled ? '維護模式已開啟（H5 將顯示維護頁）' : '維護模式已關閉')
    await load()
  } catch (e) {
    message.error(e instanceof Error ? e.message : '保存失敗')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  try {
    await load()
  } catch {
    /* 保留默認表單 */
  }
})
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="店鋪維護">
      <p class="hint">
        開啟後，H5 全站顯示維護頁（後台不受影響）。可設定預計維護時間與中英文文案。
      </p>
      <n-form-item label="開啟維護模式">
        <n-switch v-model:value="form.enabled" />
        <span class="sw">{{ form.enabled ? '已開啟 — 前台進入維護頁' : '已關閉 — 前台正常營業' }}</span>
      </n-form-item>
      <div class="grid">
        <n-form-item label="預計開始（本地時間）">
          <n-input v-model:value="form.starts_at" type="datetime-local" />
        </n-form-item>
        <n-form-item label="預計結束（本地時間）">
          <n-input v-model:value="form.ends_at" type="datetime-local" />
        </n-form-item>
        <n-form-item label="標題（繁中）">
          <n-input v-model:value="form.title_zh" maxlength="40" />
        </n-form-item>
        <n-form-item label="標題（英文）">
          <n-input v-model:value="form.title_en" maxlength="40" />
        </n-form-item>
      </div>
      <n-form-item label="說明（繁中）">
        <n-input
          v-model:value="form.message_zh"
          type="textarea"
          :autosize="{ minRows: 3, maxRows: 6 }"
        />
      </n-form-item>
      <n-form-item label="說明（英文）">
        <n-input
          v-model:value="form.message_en"
          type="textarea"
          :autosize="{ minRows: 3, maxRows: 6 }"
        />
      </n-form-item>
      <n-button type="primary" :loading="loading" @click="save">保存</n-button>
    </n-card>
  </n-space>
</template>

<style scoped>
.hint {
  margin: 0 0 1rem;
  font-size: 0.8125rem;
  color: #8a8378;
  line-height: 1.5;
}
.sw {
  margin-left: 0.75rem;
  font-size: 0.8125rem;
  color: #555;
}
.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem 1rem;
  max-width: 44rem;
}
@media (max-width: 800px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>
