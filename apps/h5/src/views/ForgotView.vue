<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import AuthLayout from '../components/AuthLayout.vue'
import { authQuery, isCode, isEmail, isPhone, isStrongPassword, useCooldown } from '../composables/useAuth'

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

function sendCode() {
  if (left.value > 0) return
  const value = identity.value.trim()
  if (isEmail(value)) {
    whatsappFallback.value = false
    codeSent.value = true
    start()
    showToast(t('auth.sentEmail'))
    return
  }
  if (!isPhone(value)) {
    showToast(t('auth.identityInvalid'))
    return
  }
  whatsappFallback.value = true
  codeSent.value = true
  start()
  showToast(t('auth.whatsappFailedBound'))
}

function submit() {
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
  showToast(t('auth.resetOk'))
  router.replace({ name: 'login', query: authQuery(route.query) })
}
</script>

<template>
  <AuthLayout :title="t('auth.forgotTitle')" :hint="t('auth.forgotHint')">
    <label class="lab">{{ t('auth.identity') }} *</label>
    <input v-model="identity" class="field" autocomplete="username" :placeholder="t('auth.identityPh')" />
    <label class="lab">{{ t('auth.code') }} *</label>
    <div class="field">
      <input v-model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" :placeholder="t('auth.codePh')" />
      <button class="send" type="button" :disabled="left > 0" @click="sendCode">
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
    <button class="submit" type="button" @click="submit">{{ t('auth.reset') }}</button>
    <p class="center">
      <button type="button" @click="router.push({ name: 'login', query: authQuery(route.query) })">
        {{ t('auth.backLogin') }}
      </button>
    </p>
  </AuthLayout>
</template>
