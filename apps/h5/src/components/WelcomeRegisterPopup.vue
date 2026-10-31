<script setup lang="ts">
import { computed, onActivated, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '../api/client'
import { consumeJustRegistered, peekJustRegistered } from '../composables/useAuthUi'
import { welcomeOverlayOpen } from '../composables/useHomePopupGate'
import { assetUrl } from '../utils/assetUrl'

const { t, locale } = useI18n()
const show = ref(false)
const html = ref('')
let checking = false

function rewriteAssets(raw: string) {
  return raw.replace(/(src=["'])(\/[^"']+)(["'])/gi, (_, a, path, b) => `${a}${assetUrl(path)}${b}`)
}

const bodyHtml = computed(() => rewriteAssets(html.value || ''))

async function maybeShow() {
  if (show.value || checking || !peekJustRegistered()) return
  checking = true
  try {
    const data = await api<{ welcome_html?: { 'zh-HK'?: string; en?: string } }>('/api/v1/shop/status')
    const loc = String(locale.value || 'zh-HK')
    const pack = data.welcome_html || {}
    const content = loc.startsWith('en')
      ? pack.en || pack['zh-HK'] || ''
      : pack['zh-HK'] || pack.en || ''
    if (!content.trim()) {
      consumeJustRegistered()
      return
    }
    consumeJustRegistered()
    html.value = content
    show.value = true
    welcomeOverlayOpen.value = true
  } catch {
    /* 歡迎失敗不擋首頁；保留 flag 下次再試 */
  } finally {
    checking = false
  }
}

watch(show, (v) => {
  if (!v) welcomeOverlayOpen.value = false
})

onMounted(() => {
  void maybeShow()
})

onActivated(() => {
  void maybeShow()
})

watch(locale, () => {
  /* 彈窗開啟期間切語言時刷新文案 */
  if (!show.value) return
  void api<{ welcome_html?: { 'zh-HK'?: string; en?: string } }>('/api/v1/shop/status').then((data) => {
    const loc = String(locale.value || 'zh-HK')
    const pack = data.welcome_html || {}
    html.value = loc.startsWith('en') ? pack.en || pack['zh-HK'] || '' : pack['zh-HK'] || pack.en || ''
  })
})
</script>

<template>
  <van-popup
    v-model:show="show"
    round
    closeable
    position="center"
    :style="{ width: '86%', maxWidth: '22rem' }"
    class="welcome-pop"
  >
    <div class="welcome-card">
      <div class="welcome-badge" aria-hidden="true">
        <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
          <circle cx="32" cy="32" r="30" fill="#ffe6f1" />
          <path
            d="M20 34l8 8 16-18"
            fill="none"
            stroke="#c45b8a"
            stroke-width="4"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </div>
      <h2 class="welcome-title">{{ t('auth.welcomeTitle') }}</h2>
      <div class="welcome-body rich" v-html="bodyHtml" />
      <button type="button" class="welcome-ok" @click="show = false">{{ t('auth.welcomeOk') }}</button>
    </div>
  </van-popup>
</template>

<style scoped>
.welcome-card {
  padding: 1.35rem 1.15rem 1.2rem;
  text-align: center;
}
.welcome-badge {
  display: flex;
  justify-content: center;
  margin-bottom: 0.55rem;
}
.welcome-badge svg {
  width: 3.25rem;
  height: 3.25rem;
}
.welcome-title {
  margin: 0 0 0.65rem;
  font-size: 1.15rem;
  font-weight: 800;
  color: #1c1c28;
}
.welcome-body {
  text-align: left;
  font-size: 0.9rem;
  line-height: 1.55;
  color: #3a3540;
  max-height: 14rem;
  overflow: auto;
}
.welcome-body :deep(p) {
  margin: 0 0 0.55rem;
}
.welcome-body :deep(img) {
  max-width: 100%;
  border-radius: 0.5rem;
}
.welcome-ok {
  width: 100%;
  height: 2.75rem;
  margin-top: 1rem;
  border: 0;
  border-radius: 999px;
  background: linear-gradient(100deg, #1a1a3a 0%, #16162b 45%, #0c0c14 100%);
  color: #fff;
  font-size: 0.95rem;
  font-weight: 700;
}
</style>
