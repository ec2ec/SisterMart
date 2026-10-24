<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import { api, ApiError } from '../api/client'
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
import { markJustRegistered, shakeAgreeRow, useRequestBusy } from '../composables/useAuthUi'
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
const agreeRow = ref<HTMLElement | null>(null)
const refCode = ref(authQuery(route.query).ref || '')
const { left, start } = useCooldown()
const { busy: sending, run: runSend } = useRequestBusy()
const { busy: submitting, run: runSubmit } = useRequestBusy()

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

/** 選填：有填則先校驗格式與服務端 HMAC／存在性，通過才允許提交 */
async function assertRefOk(): Promise<string> {
  const raw = refCode.value.trim().toUpperCase()
  refCode.value = raw
  if (!raw) return ''
  if (!/^SM\d+[A-Z]$/.test(raw)) {
    throw new ApiError('BAD_REF', t('auth.badRef'), 422, 42200)
  }
  await api<{ valid: boolean }>(`/api/v1/promo-codes/verify?code=${encodeURIComponent(raw)}`)
  return raw
}

function switchMode(next: 'email' | 'phone') {
  mode.value = next
  codeSent.value = false
  whatsappFallback.value = false
  code.value = ''
}

function openArticle(id: string) {
  router.push({ name: 'article', params: { id } })
}

function remindAgree() {
  showToast(t('auth.needAgree'))
  shakeAgreeRow(agreeRow)
}

async function sendCode() {
  if (left.value > 0 || sending.value) return
  await runSend(async () => {
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
      fillDevOtpCode(code, data)
      showToast(data.debug_code ? t('auth.debugCode', { code: data.debug_code }) : t('auth.sentEmail'))
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
  await runSubmit(async () => {
    try {
      const ref = await assertRefOk()
      const data = await api<{ token: string; member: Member; bound?: boolean }>('/api/v1/members', {
        method: 'POST',
        body: JSON.stringify({
          channel: mode.value,
          email: email.value.trim(),
          phone: phone.value.trim(),
          password: password.value,
          code: code.value.trim(),
          agreed: true,
          ref,
          locale: i18n.global.locale.value,
        }),
      })
      setSession(data.token, data.member)
      if (data.member.locale === 'en' || data.member.locale === 'zh-HK') setLocale(data.member.locale)
      showToast(data.bound && ref ? t('auth.registerOkRef', { ref }) : t('auth.registerOk'))
      markJustRegistered()
      // 註冊完成固定進首頁，展示歡迎內容
      router.replace({ path: '/' })
    } catch (error) {
      showToast(authError(t, error))
    }
  })
}
</script>

<template>
  <AuthLayout :title="registrationOpen || !statusLoaded ? t('auth.registerTitle') : t('auth.register')">
    <template v-if="!statusLoaded" />
    <template v-else-if="!registrationOpen">
      <div class="closed-illu" aria-hidden="true">
        <svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="rgSky" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#f7d6e8" />
              <stop offset="55%" stop-color="#e8e0f8" />
              <stop offset="100%" stop-color="#d7ecf8" />
            </linearGradient>
            <linearGradient id="rgDoor" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#6b5b95" />
              <stop offset="100%" stop-color="#3d3560" />
            </linearGradient>
          </defs>
          <rect width="200" height="140" rx="18" fill="url(#rgSky)" />
          <ellipse cx="100" cy="118" rx="62" ry="10" fill="rgba(70,50,90,0.08)" />
          <rect x="68" y="38" width="64" height="78" rx="8" fill="url(#rgDoor)" />
          <rect x="74" y="44" width="52" height="66" rx="5" fill="#2a2440" opacity="0.35" />
          <circle cx="118" cy="78" r="4.5" fill="#f4c96a" />
          <path
            d="M92 70h16M92 82h16M92 94h16"
            stroke="#f7e9ff"
            stroke-width="3"
            stroke-linecap="round"
            opacity="0.55"
          />
          <circle cx="42" cy="48" r="10" fill="#fff6" />
          <circle cx="162" cy="56" r="7" fill="#fff5" />
          <path
            d="M148 96c8-10 18-10 26 0"
            fill="none"
            stroke="#5a4a7a"
            stroke-width="3"
            stroke-linecap="round"
            opacity="0.45"
          />
          <circle cx="161" cy="88" r="7" fill="#ffe0ef" />
          <rect x="155" y="95" width="12" height="16" rx="4" fill="#c9b6e8" />
        </svg>
      </div>
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

      <label class="lab">{{ t('auth.refLabel') }}（{{ t('auth.refOptional') }}）</label>
      <input
        v-model="refCode"
        class="field"
        type="text"
        autocomplete="off"
        spellcheck="false"
        :placeholder="t('auth.refPh')"
        @blur="refCode = refCode.trim().toUpperCase()"
      />
      <p class="ref-hint">{{ t('auth.refHint') }}</p>

      <label ref="agreeRow" class="check">
        <input v-model="agreed" type="checkbox" />
        <span>
          {{ t('auth.agreeBefore') }}<button type="button" @click.prevent="openArticle('terms')">{{ t('auth.terms') }}</button
          >{{ t('auth.agreeMid') }}<button type="button" @click.prevent="openArticle('privacy')">{{ t('auth.privacy') }}</button
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
        {{ t('auth.register') }}
      </button>
      <p class="note">{{ t('auth.registerPhoneNote') }}</p>
    </template>
  </AuthLayout>
</template>

<style scoped>
.closed-illu {
  display: flex;
  justify-content: center;
  margin: 0.25rem 0 0.85rem;
}
.closed-illu svg {
  width: min(100%, 13.5rem);
  height: auto;
  filter: drop-shadow(0 0.5rem 1rem rgba(90, 70, 120, 0.12));
}
.closed-msg {
  margin: 0 0 1.25rem;
  padding: 0.875rem 1rem;
  border-radius: 0.75rem;
  background: rgba(180, 60, 60, 0.08);
  color: #8b2e2e;
  font-size: 0.9375rem;
  line-height: 1.5;
  text-align: center;
}
.ref-hint {
  margin: 0.35rem 0 0;
  font-size: 0.75rem;
  line-height: 1.45;
  color: #6a6570;
}
</style>
