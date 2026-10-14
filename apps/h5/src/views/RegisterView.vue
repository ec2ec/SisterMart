<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import AuthLayout from '../components/AuthLayout.vue'
import {
  afterAuthPath,
  authQuery,
  isCode,
  isEmail,
  isPhone,
  isStrongPassword,
  useCooldown,
} from '../composables/useAuth'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const mode = ref<'email' | 'phone'>('email')
const email = ref('')
const phone = ref('')
const code = ref('')
const password = ref('')
const showPassword = ref(false)
const agreed = ref(false)
const codeSent = ref(false)
const whatsappFallback = ref(false)
const { left, start } = useCooldown()

function switchMode(next: 'email' | 'phone') {
  mode.value = next
  codeSent.value = false
  whatsappFallback.value = false
  code.value = ''
}

function openArticle(id: string) {
  router.push({ name: 'article', params: { id } })
}

function sendCode() {
  if (left.value > 0) return
  if (mode.value === 'email') {
    if (!isEmail(email.value)) {
      showToast(t('auth.emailInvalid'))
      return
    }
    codeSent.value = true
    start()
    showToast(t('auth.sentEmail'))
    return
  }
  if (!isPhone(phone.value)) {
    showToast(t('auth.phoneInvalid'))
    return
  }
  if (!whatsappFallback.value) {
    whatsappFallback.value = true
    showToast(t('auth.whatsappFailedNeedEmail'))
    return
  }
  if (!isEmail(email.value)) {
    showToast(t('auth.emailInvalid'))
    return
  }
  codeSent.value = true
  start()
  showToast(t('auth.sentEmail'))
}

function submit() {
  if (!agreed.value) {
    showToast(t('auth.needAgree'))
    return
  }
  if (mode.value === 'phone' && !isPhone(phone.value)) {
    showToast(t('auth.phoneInvalid'))
    return
  }
  if (mode.value === 'email' && !isEmail(email.value)) {
    showToast(t('auth.emailInvalid'))
    return
  }
  if (mode.value === 'phone' && whatsappFallback.value && !isEmail(email.value)) {
    showToast(t('auth.emailInvalid'))
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
  const refCode = authQuery(route.query).ref
  showToast(refCode ? t('auth.registerOkRef', { ref: refCode }) : t('auth.registerOk'))
  router.replace(afterAuthPath(route.query))
}
</script>

<template>
  <AuthLayout :title="t('auth.registerTitle')">
    <div class="seg">
      <button type="button" :class="{ on: mode === 'email' }" @click="switchMode('email')">
        {{ t('auth.emailTab') }}
      </button>
      <button type="button" :class="{ on: mode === 'phone' }" @click="switchMode('phone')">
        {{ t('auth.phoneTab') }}
      </button>
    </div>

    <template v-if="mode === 'phone'">
      <label class="lab">{{ t('auth.phone') }} *</label>
      <input v-model="phone" class="field" type="tel" autocomplete="tel" :placeholder="t('auth.phonePh')" />
    </template>
    <template v-if="mode === 'email' || whatsappFallback">
      <label class="lab">{{ mode === 'phone' ? t('auth.fallbackEmail') : t('auth.email') }} *</label>
      <input v-model="email" class="field" type="email" autocomplete="email" :placeholder="t('auth.emailPh')" />
    </template>

    <label class="lab">{{ t('auth.code') }} *</label>
    <div class="field">
      <input v-model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" :placeholder="t('auth.codePh')" />
      <button class="send" type="button" :disabled="left > 0" @click="sendCode">
        {{ left > 0 ? t('auth.resend', { n: left }) : t('auth.send') }}
      </button>
    </div>
    <label class="lab">{{ t('auth.password') }} *</label>
    <div class="field">
      <input
        v-model="password"
        :type="showPassword ? 'text' : 'password'"
        autocomplete="new-password"
        :placeholder="t('auth.passwordPh')"
      />
      <button class="eye" type="button" @click="showPassword = !showPassword">
        <van-icon :name="showPassword ? 'eye-o' : 'closed-eye'" size="1.125rem" />
      </button>
    </div>

    <label class="check">
      <input v-model="agreed" type="checkbox" />
      <span>
        {{ t('auth.agreeBefore') }}<button type="button" @click.prevent="openArticle('terms')">{{ t('auth.terms') }}</button
        >{{ t('auth.agreeMid') }}<button type="button" @click.prevent="openArticle('privacy')">{{ t('auth.privacy') }}</button
        >{{ t('auth.agreeAfter') }}
      </span>
    </label>
    <button class="submit" type="button" @click="submit">{{ t('auth.register') }}</button>
    <p class="note">{{ t('auth.registerPhoneNote') }}</p>
  </AuthLayout>
</template>
