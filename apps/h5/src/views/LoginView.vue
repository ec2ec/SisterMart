<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import { api } from '../api/client'
import AuthLayout from '../components/AuthLayout.vue'
import {
  afterAuthPath,
  authError,
  authQuery,
  fillDevOtpCode,
  isCode,
  isEmail,
  isPhone,
  markPostAuthBack,
  useCooldown,
} from '../composables/useAuth'
import { loggedInAuthBouncePath } from '../composables/useNavBack'
import { shakeAgreeRow, useRequestBusy } from '../composables/useAuthUi'
import { useMaintenance } from '../composables/useMaintenance'
import { setSession, type Member } from '../composables/useSession'
import { setLocale } from '../i18n'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { refresh: refreshMaintenance } = useMaintenance()

const mode = ref<'email' | 'phone'>('email')
const email = ref('')
const phone = ref('')
const password = ref('')
const code = ref('')
const showPassword = ref(false)
const agreed = ref(false)
const codeSent = ref(false)
const whatsappFallback = ref(false)
const agreeRow = ref<HTMLElement | null>(null)
const { left, start } = useCooldown()
const { busy: sending, run: runSend } = useRequestBusy()
const { busy: submitting, run: runSubmit } = useRequestBusy()

const hint = computed(() => (mode.value === 'email' ? t('auth.loginEmailHint') : t('auth.loginPhoneHint')))

function switchMode(next: 'email' | 'phone') {
  mode.value = next
  codeSent.value = false
  whatsappFallback.value = false
  code.value = ''
}

function go(name: 'register' | 'forgot' | 'article', id?: string) {
  if (name === 'article') {
    router.push({ name: 'article', params: { id } })
    return
  }
  router.push({ name, query: authQuery(route.query) })
}

function remindAgree() {
  showToast(t('auth.needAgree'))
  shakeAgreeRow(agreeRow)
}

async function sendCode() {
  if (left.value > 0 || sending.value) return
  await runSend(async () => {
    if (!isPhone(phone.value)) {
      showToast(t('auth.phoneInvalid'))
      return
    }
    try {
      const data = await api<{ fallback?: boolean; debug_code?: string }>('/api/v1/verification-codes', {
        method: 'POST',
        body: JSON.stringify({ channel: 'whatsapp', purpose: 'login', target: phone.value.trim() }),
      })
      whatsappFallback.value = Boolean(data.fallback)
      codeSent.value = true
      start()
      fillDevOtpCode(code, data)
      showToast(data.debug_code ? t('auth.debugCode', { code: data.debug_code }) : t('auth.whatsappFailedBound'))
    } catch (error) {
      showToast(authError(t, error))
    }
  })
}

async function submit() {
  if (submitting.value) return
  if (!agreed.value) {
    remindAgree()
    return
  }
  if (mode.value === 'email') {
    if (!isEmail(email.value)) {
      showToast(t('auth.emailInvalid'))
      return
    }
    if (!password.value) {
      showToast(t('auth.passwordRequired'))
      return
    }
  } else {
    if (!isPhone(phone.value)) {
      showToast(t('auth.phoneInvalid'))
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
  }
  await runSubmit(async () => {
    try {
      const data = await api<{ token: string; member: Member }>('/api/v1/member-sessions', {
        method: 'POST',
        body: JSON.stringify({
          channel: mode.value,
          email: email.value.trim(),
          phone: phone.value.trim(),
          password: password.value,
          code: code.value.trim(),
          agreed: true,
        }),
      })
      setSession(data.token, data.member)
      if (data.member.locale === 'en' || data.member.locale === 'zh-HK') setLocale(data.member.locale)
      // 先刷新維護狀態（member_bypass），避免進首頁被公開接口 503 蓋回維護頁
      await refreshMaintenance()
      showToast(t('auth.loginOk'))
      // 目標頁點返回應回到來源 Tab／首頁，而非登錄棧
      markPostAuthBack(loggedInAuthBouncePath())
      await router.replace(afterAuthPath(route.query))
    } catch (error) {
      showToast(authError(t, error))
    }
  })
}
</script>

<template>
  <AuthLayout :title="t('auth.loginTitle')" :hint="hint">
    <div class="seg">
      <button type="button" :class="{ on: mode === 'email' }" @click="switchMode('email')">
        {{ t('auth.emailTab') }}
      </button>
      <button type="button" :class="{ on: mode === 'phone' }" @click="switchMode('phone')">
        {{ t('auth.phoneTab') }}
      </button>
    </div>

    <template v-if="mode === 'email'">
      <label class="lab">{{ t('auth.email') }} *</label>
      <input v-model="email" class="field" type="email" autocomplete="username" :placeholder="t('auth.emailPh')" />
      <label class="lab">{{ t('auth.password') }} *</label>
      <div class="field">
        <input
          v-model="password"
          :type="showPassword ? 'text' : 'password'"
          autocomplete="current-password"
          :placeholder="t('auth.password')"
        />
        <button class="eye" type="button" @click="showPassword = !showPassword">
          <van-icon :name="showPassword ? 'eye-o' : 'closed-eye'" size="1.125rem" />
        </button>
      </div>
      <button class="forgot" type="button" @click="go('forgot')">{{ t('auth.forgot') }}</button>
    </template>

    <template v-else>
      <label class="lab">{{ t('auth.phone') }} *</label>
      <input v-model="phone" class="field" type="tel" autocomplete="tel" :placeholder="t('auth.phonePh')" />
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
    </template>

    <label ref="agreeRow" class="check">
      <input v-model="agreed" type="checkbox" />
      <span>
        {{ t('auth.agreeBefore') }}<button type="button" @click.prevent="go('article', 'terms')">{{ t('auth.terms') }}</button
        >{{ t('auth.agreeMid') }}<button type="button" @click.prevent="go('article', 'privacy')">{{ t('auth.privacy') }}</button
        >{{ t('auth.agreeAfter') }}
      </span>
    </label>
    <button
      class="submit"
      type="button"
      :class="{ 'is-busy': submitting }"
      :disabled="submitting"
      @click="submit"
    >
      <span v-if="submitting" class="btn-spin" aria-hidden="true" />
      {{ t('auth.login') }}
    </button>
    <p class="center">
      {{ t('auth.newUser') }}
      <button type="button" @click="go('register')">{{ t('auth.createAccount') }}</button>
    </p>
    <p class="slogan">{{ t('home.slogan') }}</p>
  </AuthLayout>
</template>
