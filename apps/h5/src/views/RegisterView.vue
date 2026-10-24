<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import { api, ApiError } from '../api/client'
import AuthLayout from '../components/AuthLayout.vue'
import {
  afterAuthPath,
  authError,
  authQuery,
  fillDevOtpCode,
  isCode,
  isEmail,
  isPhone,
  isStrongPassword,
  useCooldown,
} from '../composables/useAuth'
import { setSession, type Member } from '../composables/useSession'
import { i18n, setLocale } from '../i18n'

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
const registrationOpen = ref(true)
const statusLoaded = ref(false)
const { left, start } = useCooldown()

onMounted(async () => {
  try {
    const data = await api<{ registration_enabled?: boolean }>('/api/v1/shop/status')
    registrationOpen.value = data.registration_enabled !== false
  } catch {
    registrationOpen.value = true
  } finally {
    statusLoaded.value = true
  }
})

function switchMode(next: 'email' | 'phone') {
  mode.value = next
  codeSent.value = false
  whatsappFallback.value = false
  code.value = ''
}

function openArticle(id: string) {
  router.push({ name: 'article', params: { id } })
}

async function sendCode() {
  if (left.value > 0) return
  if (mode.value === 'phone' && !whatsappFallback.value) {
    if (!isPhone(phone.value)) {
      showToast(t('auth.phoneInvalid'))
      return
    }
    try {
      await api('/api/v1/verification-codes', {
        method: 'POST',
        body: JSON.stringify({ channel: 'whatsapp', purpose: 'register', target: phone.value.trim() }),
      })
    } catch (error) {
      if (error instanceof ApiError && error.code === 'WHATSAPP_UNAVAILABLE') {
        whatsappFallback.value = true
        showToast(t('auth.whatsappFailedNeedEmail'))
        return
      }
      showToast(authError(t, error))
      return
    }
  }
  if (!isEmail(email.value)) {
    showToast(t('auth.emailInvalid'))
    return
  }
  try {
    const data = await api<{ debug_code?: string }>('/api/v1/verification-codes', {
      method: 'POST',
      body: JSON.stringify({ channel: 'email', purpose: 'register', target: email.value.trim() }),
    })
    codeSent.value = true
    start()
    fillDevOtpCode(code, data) // TEMP: 未接通正式發碼渠道
    showToast(data.debug_code ? t('auth.debugCode', { code: data.debug_code }) : t('auth.sentEmail'))
  } catch (error) {
    showToast(authError(t, error))
  }
}

async function submit() {
  if (!agreed.value) {
    showToast(t('auth.needAgree'))
    return
  }
  if (mode.value === 'phone' && !isPhone(phone.value)) {
    showToast(t('auth.phoneInvalid'))
    return
  }
  if ((mode.value === 'email' || whatsappFallback.value) && !isEmail(email.value)) {
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
  try {
    const data = await api<{ token: string; member: Member; bound?: boolean }>('/api/v1/members', {
      method: 'POST',
      body: JSON.stringify({
        channel: mode.value,
        email: email.value.trim(),
        phone: phone.value.trim(),
        password: password.value,
        code: code.value.trim(),
        agreed: true,
        ref: refCode || '',
        locale: i18n.global.locale.value,
      }),
    })
    setSession(data.token, data.member)
    if (data.member.locale === 'en' || data.member.locale === 'zh-HK') setLocale(data.member.locale)
    showToast(data.bound && refCode ? t('auth.registerOkRef', { ref: refCode }) : t('auth.registerOk'))
    router.replace(afterAuthPath(route.query))
  } catch (error) {
    showToast(authError(t, error))
  }
}
</script>

<template>
  <AuthLayout :title="registrationOpen ? t('auth.registerTitle') : t('auth.register')">
    <!-- 狀態未載入前不渲染註冊表單，避免閃爍 -->
    <template v-if="!statusLoaded" />
    <template v-else-if="!registrationOpen">
      <p class="closed-msg">{{ t('auth.registerClosed') }}</p>
      <button class="submit" type="button" @click="router.replace({ name: 'login' })">{{ t('auth.backLogin') }}</button>
    </template>
    <template v-else>
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
    </template>
  </AuthLayout>
</template>

<style scoped>
.closed-msg {
  margin: 0.5rem 0 1.25rem;
  padding: 0.875rem 1rem;
  border-radius: 0.75rem;
  background: rgba(180, 60, 60, 0.08);
  color: #8b2e2e;
  font-size: 0.9375rem;
  line-height: 1.5;
  text-align: center;
}
</style>
