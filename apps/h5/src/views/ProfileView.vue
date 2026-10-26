<script setup lang="ts">
import { goLogin } from '../composables/useAuth'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { api, ApiError } from '../api/client'
import { isLoggedIn, memberState, type Member } from '../composables/useSession'

const { t } = useI18n()
const router = useRouter()
const nickname = ref('')
const bindEmail = ref('')
const bindPhone = ref('')
const emailCode = ref('')
const phoneCode = ref('')
const busy = ref(false)

onMounted(async () => {
  if (!isLoggedIn()) {
    void goLogin(router, '/profile', { replace: true })
    return
  }
  try {
    const data = await api<Member>('/api/v1/members/me', {}, 'member')
    memberState.value = data
    localStorage.setItem('sm_member', JSON.stringify(data))
    nickname.value = data.nickname || ''
    bindEmail.value = data.email || ''
    bindPhone.value = data.phone || ''
  } catch {
    /* ignore */
  }
})

async function saveNickname() {
  busy.value = true
  try {
    const data = await api<Member>(
      '/api/v1/members/me',
      { method: 'PATCH', body: JSON.stringify({ nickname: nickname.value.trim() }) },
      'member',
    )
    memberState.value = data
    localStorage.setItem('sm_member', JSON.stringify(data))
    showToast(t('account.profileSaved'))
  } catch (e) {
    showToast(e instanceof ApiError && e.message ? e.message : t('account.profileFail'))
  } finally {
    busy.value = false
  }
}

async function sendBind(channel: 'email' | 'phone') {
  const target = channel === 'email' ? bindEmail.value.trim() : bindPhone.value.trim()
  if (!target) {
    showToast(channel === 'email' ? t('account.bindEmailPh') : t('account.bindPhonePh'))
    return
  }
  busy.value = true
  try {
    const data = await api<{ debug_code?: string }>(
      '/api/v1/member-bindings/code',
      { method: 'POST', body: JSON.stringify({ channel, target }) },
      'member',
    )
    // TEMP: 未接通正式發碼渠道時回填
    if (data.debug_code) {
      if (channel === 'email') emailCode.value = data.debug_code
      else phoneCode.value = data.debug_code
    }
    showToast(data.debug_code ? t('auth.debugCode', { code: data.debug_code }) : t('account.codeSent'))
  } catch (e) {
    showToast(e instanceof ApiError && e.message ? e.message : t('account.profileFail'))
  } finally {
    busy.value = false
  }
}

async function confirmBind(channel: 'email' | 'phone') {
  const target = channel === 'email' ? bindEmail.value.trim() : bindPhone.value.trim()
  const code = channel === 'email' ? emailCode.value.trim() : phoneCode.value.trim()
  if (!target || !code) {
    showToast(t('account.needCode'))
    return
  }
  busy.value = true
  try {
    const data = await api<Member>(
      '/api/v1/member-bindings',
      { method: 'POST', body: JSON.stringify({ channel, target, code }) },
      'member',
    )
    memberState.value = data
    localStorage.setItem('sm_member', JSON.stringify(data))
    showToast(t('account.bindOk'))
  } catch (e) {
    showToast(e instanceof ApiError && e.message ? e.message : t('account.profileFail'))
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('account.profileTitle')" />
    <section class="block">
      <h2>{{ t('account.nickname') }}</h2>
      <input v-model="nickname" type="text" maxlength="64" :placeholder="t('account.nicknamePh')" />
      <van-button block type="primary" color="var(--sm-primary)" :loading="busy" @click="saveNickname">
        {{ t('account.saveNickname') }}
      </van-button>
    </section>
    <section class="block">
      <h2>{{ t('account.bindEmail') }}</h2>
      <input v-model="bindEmail" type="email" :placeholder="t('account.bindEmailPh')" />
      <div class="row">
        <input v-model="emailCode" type="text" :placeholder="t('account.codePh')" />
        <van-button size="small" :disabled="busy" @click="sendBind('email')">{{ t('account.sendCode') }}</van-button>
      </div>
      <van-button block plain :loading="busy" @click="confirmBind('email')">{{ t('account.confirmBind') }}</van-button>
    </section>
    <section class="block">
      <h2>{{ t('account.bindPhone') }}</h2>
      <p class="hint">{{ t('account.bindPhoneHint') }}</p>
      <input v-model="bindPhone" type="tel" :placeholder="t('account.bindPhonePh')" />
      <div class="row">
        <input v-model="phoneCode" type="text" :placeholder="t('account.codePh')" />
        <van-button size="small" :disabled="busy" @click="sendBind('phone')">{{ t('account.sendCode') }}</van-button>
      </div>
      <van-button block plain :loading="busy" @click="confirmBind('phone')">{{ t('account.confirmBind') }}</van-button>
    </section>
  </div>
</template>

<style scoped>
.block {
  margin: 0.75rem;
  padding: 0.875rem;
  border-radius: 0.75rem;
  background: #fff;
}
.block h2 {
  margin: 0 0 0.5rem;
  font-size: 0.875rem;
}
.hint {
  margin: 0 0 0.5rem;
  font-size: 0.75rem;
  color: #999;
}
input {
  display: block;
  width: 100%;
  box-sizing: border-box;
  margin-bottom: 0.5rem;
  border: 0.0625rem solid #eee;
  border-radius: 0.5rem;
  padding: 0.625rem 0.75rem;
  font: inherit;
}
.row {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}
.row input {
  flex: 1;
  margin-bottom: 0;
}
</style>
