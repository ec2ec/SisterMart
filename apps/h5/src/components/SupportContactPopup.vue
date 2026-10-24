<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { showToast } from 'vant'
import { api } from '../api/client'

const show = defineModel<boolean>('show', { default: false })

const { t } = useI18n()
const whatsapp = ref('')
const email = ref('')
const loaded = ref(false)

const whatsappDigits = computed(() => whatsapp.value.replace(/\D/g, ''))

async function loadCs() {
  if (loaded.value) return
  try {
    const data = await api<{ cs_whatsapp?: string; cs_email?: string }>('/api/v1/shop/status')
    whatsapp.value = String(data.cs_whatsapp || '')
    email.value = String(data.cs_email || '').trim()
    loaded.value = true
  } catch {
    /* 仍可展示按鈕，點擊時再提示 */
  }
}

watch(show, (open) => {
  if (open) void loadCs()
})

function openWhatsapp() {
  if (!whatsappDigits.value) {
    showToast(t('account.supportWaMissing'))
    return
  }
  const text = encodeURIComponent(t('account.supportWaPrefill'))
  window.open(`https://wa.me/${whatsappDigits.value}?text=${text}`, '_blank', 'noopener')
}

function openEmail() {
  const to = email.value.trim()
  if (!to || !to.includes('@')) {
    showToast(t('account.supportEmailMissing'))
    return
  }
  const subject = encodeURIComponent(t('account.supportEmailSubject'))
  const body = encodeURIComponent(t('account.supportEmailBody'))
  window.location.href = `mailto:${to}?subject=${subject}&body=${body}`
}
</script>

<template>
  <van-popup v-model:show="show" position="bottom" round :style="{ padding: '1.25rem 1rem 1.5rem' }">
    <h2 class="title">{{ t('account.support') }}</h2>
    <p class="lead">{{ t('account.supportLead') }}</p>

    <button type="button" class="btn wa" @click="openWhatsapp">
      <van-icon name="chat-o" size="1.25rem" />
      <span>{{ t('account.supportWhatsapp') }}</span>
    </button>
    <p class="hours">{{ t('account.supportHours') }}</p>

    <button type="button" class="btn mail" @click="openEmail">
      <van-icon name="envelop-o" size="1.25rem" />
      <span>{{ t('account.supportEmail') }}</span>
    </button>
  </van-popup>
</template>

<style scoped>
.title {
  margin: 0 0 0.35rem;
  font-size: 1.0625rem;
  text-align: center;
}
.lead {
  margin: 0 0 1rem;
  font-size: 0.8125rem;
  color: #888;
  text-align: center;
  line-height: 1.45;
}
.btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  width: 100%;
  border: 0;
  border-radius: 0.75rem;
  padding: 0.875rem 1rem;
  font-size: 0.9375rem;
  font-weight: 600;
  color: #fff;
}
.btn.wa {
  background: #25d366;
}
.btn.mail {
  margin-top: 0.75rem;
  background: #3d3560;
}
.hours {
  margin: 0.5rem 0 0;
  padding: 0 0.25rem;
  font-size: 0.75rem;
  line-height: 1.45;
  color: #888;
  text-align: center;
}
</style>
