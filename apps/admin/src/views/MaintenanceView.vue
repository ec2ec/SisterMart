<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import {
  NButton,
  NCard,
  NFormItem,
  NInput,
  NSpace,
  NSwitch,
  NTag,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import MemberPickerDialog, { type MemberPickResult } from '../components/MemberPickerDialog.vue'

type MaintMember = {
  id: number
  email: string | null
  phone: string | null
  nickname: string | null
  promo_code: string
  status: number
}

type Maint = {
  enabled: boolean
  starts_at: string | null
  ends_at: string | null
  title: { 'zh-HK': string; en: string }
  message: { 'zh-HK': string; en: string }
  whitelist?: string[]
  member_ids?: number[]
  members?: MaintMember[]
  builtin_allow?: string[]
}

const message = useMessage()
const loading = ref(false)
const pickerOpen = ref(false)
const builtinAllow = ref<string[]>([])
const members = ref<MaintMember[]>([])
const form = reactive({
  enabled: false,
  starts_at: '2026-12-12T12:35',
  ends_at: '2026-12-13T03:30',
  title_zh: '商店維護中',
  title_en: 'Under Maintenance',
  message_zh: '暫時不能為你提供服務。感謝你的支持，我們會盡快回來。',
  message_en: 'We are temporarily unavailable. Thank you for your support — we will be back soon.',
  whitelist_text: '',
})

const excludeIds = computed(() => members.value.map((m) => m.id))

function toInput(dt: string | null) {
  if (!dt) return ''
  return dt.replace(' ', 'T').slice(0, 16)
}

function fromInput(v: string) {
  if (!v) return null
  return v.replace('T', ' ') + (v.length === 16 ? ':00' : '')
}

function parseWhitelist(text: string): string[] {
  return text
    .split(/\r?\n/)
    .map((l) => l.trim())
    .filter((l) => l && !l.startsWith('#'))
}

function memberLabel(m: MaintMember) {
  return m.email || m.phone || m.nickname || m.promo_code || `#${m.id}`
}

function addMember(m: MemberPickResult) {
  if (members.value.some((x) => x.id === m.id)) {
    message.info('該會員已在白名單')
    return
  }
  members.value = [
    ...members.value,
    {
      id: m.id,
      email: m.email,
      phone: m.phone,
      nickname: m.nickname,
      promo_code: m.promo_code || '',
      status: m.status,
    },
  ]
}

function removeMember(id: number) {
  members.value = members.value.filter((m) => m.id !== id)
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
  form.whitelist_text = (data.whitelist || []).join('\n')
  builtinAllow.value = data.builtin_allow || []
  members.value = data.members || []
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
          whitelist: parseWhitelist(form.whitelist_text),
          member_ids: members.value.map((m) => m.id),
        }),
      },
      true,
    )
    message.success(form.enabled ? '維護模式已開啟（H5 業務接口將回維護狀態）' : '維護模式已關閉')
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
        開啟後，H5 業務接口統一返回維護狀態，前台攔截後顯示維護頁（後台不受影響）。可設定預計維護時間、中英文文案、API
        白名單，以及可正常使用前台的用戶白名單。
      </p>
      <n-form-item label="開啟維護模式">
        <n-switch v-model:value="form.enabled" />
        <span class="sw">{{ form.enabled ? '已開啟 — 前台進入維護頁' : '已關閉 — 前台正常營業' }}</span>
      </n-form-item>
      <div class="grid">
        <n-form-item label="預計開始（本地時間）">
          <input v-model="form.starts_at" type="datetime-local" class="dt" />
        </n-form-item>
        <n-form-item label="預計結束（本地時間）">
          <input v-model="form.ends_at" type="datetime-local" class="dt" />
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

      <n-form-item label="用戶白名單">
        <div class="mw">
          <n-space align="center" style="margin-bottom: 0.5rem">
            <n-button type="primary" secondary @click="pickerOpen = true">搜索加入用戶</n-button>
            <span class="mw-count">已選 {{ members.length }} 人</span>
          </n-space>
          <p class="wl-hint">白名單用戶在維護期間仍可正常使用 H5（需已登錄）。最多 200 人。</p>
          <div v-if="members.length" class="mw-list">
            <n-tag
              v-for="m in members"
              :key="m.id"
              closable
              type="info"
              :bordered="false"
              style="margin: 0 0.35rem 0.35rem 0"
              @close="removeMember(m.id)"
            >
              #{{ m.id }} {{ memberLabel(m) }}
            </n-tag>
          </div>
          <p v-else class="mw-empty">尚未加入用戶</p>
        </div>
      </n-form-item>

      <n-form-item label="API 白名單（可選）">
        <div class="wl">
          <n-input
            v-model:value="form.whitelist_text"
            type="textarea"
            placeholder="/api/v1/articles&#10;/api/v1/tabbar"
            :autosize="{ minRows: 4, maxRows: 12 }"
          />
          <p class="wl-hint">
            每行一條路徑前綴（須以 <code>/api/</code> 開頭）。匹配規則：完全相等，或以「前綴/」開頭。
            以 <code>#</code> 開頭的行為註釋。最多 80 條。
          </p>
          <details v-if="builtinAllow.length" class="builtin">
            <summary>內建放行（不可刪，始終生效）</summary>
            <ul>
              <li v-for="p in builtinAllow" :key="p"><code>{{ p }}</code></li>
            </ul>
          </details>
        </div>
      </n-form-item>

      <n-button type="primary" :loading="loading" @click="save">保存</n-button>
    </n-card>

    <MemberPickerDialog
      v-model:show="pickerOpen"
      title="搜索加入白名單用戶"
      :exclude-ids="excludeIds"
      @select="addMember"
    />
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
.dt {
  width: 100%;
  box-sizing: border-box;
  height: 34px;
  padding: 0 0.75rem;
  border: 1px solid #e0e0e6;
  border-radius: 3px;
  background: #fff;
  font: inherit;
  color: #333;
}
.dt:focus {
  outline: none;
  border-color: #36ad6a;
}
.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem 1rem;
  max-width: 44rem;
}
.wl,
.mw {
  width: 100%;
  max-width: 44rem;
}
.wl-hint {
  margin: 0.5rem 0 0;
  font-size: 0.75rem;
  color: #8a8378;
  line-height: 1.5;
}
.wl-hint code {
  font-size: 0.7rem;
  background: #f5f2ec;
  padding: 0.05rem 0.25rem;
  border-radius: 0.2rem;
}
.mw-count {
  font-size: 0.8125rem;
  color: #666;
}
.mw-list {
  margin-top: 0.5rem;
}
.mw-empty {
  margin: 0.35rem 0 0;
  font-size: 0.8125rem;
  color: #aaa;
}
.builtin {
  margin-top: 0.75rem;
  font-size: 0.75rem;
  color: #666;
}
.builtin summary {
  cursor: pointer;
  user-select: none;
}
.builtin ul {
  margin: 0.4rem 0 0;
  padding-left: 1.25rem;
}
.builtin code {
  font-size: 0.7rem;
}
@media (max-width: 800px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>
