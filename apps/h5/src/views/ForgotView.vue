<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import { api } from '../api/client'
import AuthLayout from '../components/AuthLayout.vue'
import {
  authError,
  authQuery,
  fillDevOtpCode,
  isCode,
  isEmail,
  isPhone,
  isStrongPassword,
  useCooldown,
} from '../composables/useAuth'
import { useRequestBusy } from '../composables/useAuthUi'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const identity = ref('')
const code = ref('')
const password = ref('')
const confirm = ref('')
const showPassword = ref(false)
const codeSent = ref(false)
const whatsappFallback = ref(false)
const { left, start } = useCooldown()
const { busy: sending, run: runSend } = useRequestBusy()
const { busy: submitting, run: runSubmit } = useRequestBusy()

async function sendCode() {
  if (left.value > 0 || sending.value) return
  await runSend(async () => {
    const value = identity.value.trim()
    if (!isEmail(value) && !isPhone(value)) {
      showToast(t('auth.identityInvalid'))
      return
    }
    const channel = isEmail(value) ? 'email' : 'whatsapp'
    try {
      const data = await api<{ fallback?: boolean; debug_code?: string }>('/api/v1/verification-codes', {
        method: 'POST',
        body: JSON.stringify({ channel, purpose: 'reset', target: value }),
      })
      whatsappFallback.value = Boolean(data.fallback)
      codeSent.value = true
      start()
      fillDevOtpCode(code, data)
      if (data.debug_code) showToast(t('auth.debugCode', { code: data.debug_code }))
      else showToast(data.fallback ? t('auth.whatsappFailedBound') : t('auth.sentEmail'))
    } catch (error) {
      showToast(authError(t, error))
    }
  })
}

async function submit() {
  if (submitting.value) return
  const value = identity.value.trim()
  if (!isEmail(value) && !isPhone(value)) {
    showToast(t('auth.identityInvalid'))
    return
  }
  if (!codeSent.value) {
    showToast(t('auth.codeFirst'))
    return
  }
  if (!isCode(code.value)) {
    showToast(t('auth.codeRequired'))
    return
  }
  if (!isStrongPassword(password.value)) {
    showToast(t('auth.passwordRule'))
    return
  }
  if (password.value !== confirm.value) {
    showToast(t('auth.passwordMismatch'))
    return
  }
  await runSubmit(async () => {
    try {
      await api('/api/v1/member-passwords', {
        method: 'PATCH',
        body: JSON.stringify({ identity: value, code: code.value.trim(), password: password.value }),
      })
      showToast(t('auth.resetOk'))
      router.replace({ name: 'login', query: authQuery(route.query) })
    } catch (error) {
      showToast(authError(t, error))
    }
  })
}
</script>

<template>
  <AuthLayout :title="t('auth.forgotTitle')" :hint="t('auth.forgotHint')">
    <label class="lab">{{ t('auth.identity') }} *</label>
    <input v-model="identity" class="field" autocomplete="username" :placeholder="t('auth.identityPh')" />
    <label class="lab">{{ t('auth.code') }} *</label>
    <div class="field">
      <input v-model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" :placeholder="t('auth.codePh')" />
      <button
        class="send"
        type="button"
        :class="{ 'is-busy': sending }"
        :disabled="left > 0 || sending"
        @click="sendCode"
      >
        <span v-if="sending" class="btn-spin" aria-hidden="true" />
        {{ left > 0 ? t('auth.resend', { n: left }) : t('auth.send') }}
      </button>
    </div>
    <p v-if="whatsappFallback" class="note">{{ t('auth.sentBoundEmail') }}</p>
    <label class="lab">{{ t('auth.newPassword') }} *</label>
    <div class="field">
      <input
        v-model="password"
        :type="showPassword ? 'text' : 'password'"
        autocomplete="new-password"
        :placeholder="t('auth.newPasswordPh')"
      />
      <button class="eye" type="button" @click="showPassword = !showPassword">
        <van-icon :name="showPassword ? 'eye-o' : 'closed-eye'" size="1.125rem" />
      </button>
    </div>
    <label class="lab">{{ t('auth.confirmPassword') }} *</label>
    <input v-model="confirm" class="field" type="password" autocomplete="new-password" :placeholder="t('auth.confirmPh')" />
    <button
      class="submit"
      type="button"
      :class="{ 'is-busy': submitting }"
      :disabled="submitting"
      @click="submit"
    >
      <span v-if="submitting" class="btn-spin" aria-hidden="true" />
      {{ t('auth.reset') }}
    </button>
    <p class="center">
      <button type="button" @click="router.push({ name: 'login', query: authQuery(route.query) })">
        {{ t('auth.backLogin') }}
      </button>
    </p>
  </AuthLayout>
</template>
