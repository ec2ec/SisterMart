<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import AuthLayout from '../components/AuthLayout.vue'
import { afterAuthPath, authQuery, isCode, isEmail, isPhone, useCooldown } from '../composables/useAuth'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const mode = ref<'email' | 'phone'>('email')
const email = ref('')
const phone = ref('')
const password = ref('')
const code = ref('')
const showPassword = ref(false)
const agreed = ref(false)
const codeSent = ref(false)
const whatsappFallback = ref(false)
const { left, start } = useCooldown()

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

function sendCode() {
  if (left.value > 0) return
  if (!isPhone(phone.value)) {
    showToast(t('auth.phoneInvalid'))
    return
  }
  whatsappFallback.value = true
  codeSent.value = true
  start()
  showToast(t('auth.whatsappFailedBound'))
}

function submit() {
  if (!agreed.value) {
    showToast(t('auth.needAgree'))
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
  showToast(t('auth.loginOk'))
  router.replace(afterAuthPath(route.query))
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
        <button class="send" type="button" :disabled="left > 0" @click="sendCode">
          {{ left > 0 ? t('auth.resend', { n: left }) : t('auth.send') }}
        </button>
      </div>
      <p v-if="whatsappFallback" class="note">{{ t('auth.sentBoundEmail') }}</p>
    </template>

    <label class="check">
      <input v-model="agreed" type="checkbox" />
      <span>
        {{ t('auth.agreeBefore') }}<button type="button" @click.prevent="go('article', 'terms')">{{ t('auth.terms') }}</button
        >{{ t('auth.agreeMid') }}<button type="button" @click.prevent="go('article', 'privacy')">{{ t('auth.privacy') }}</button
        >{{ t('auth.agreeAfter') }}
      </span>
    </label>
    <button class="submit" type="button" @click="submit">{{ t('auth.login') }}</button>
    <p class="center">
      {{ t('auth.newUser') }}
      <button type="button" @click="go('register')">{{ t('auth.createAccount') }}</button>
    </p>
    <p class="slogan">{{ t('home.slogan') }}</p>
  </AuthLayout>
</template>
