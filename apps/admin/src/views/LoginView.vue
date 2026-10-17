<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { NButton, NCard, NForm, NFormItem, NInput, useMessage } from 'naive-ui'
import { api, type AdminUser } from '../api'

const route = useRoute()
const router = useRouter()
const message = useMessage()
const email = ref('admin@sistermart.local')
const password = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  try {
    const data = await api<{ token: string; admin: AdminUser }>('/api/v1/admin/sessions', {
      method: 'POST',
      body: JSON.stringify({ email: email.value.trim(), password: password.value }),
    })
    localStorage.setItem('sm_admin_token', data.token)
    localStorage.setItem('sm_admin', JSON.stringify(data.admin))
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
    router.replace(redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/')
  } catch (error) {
    message.error(error instanceof Error ? error.message : '登錄失敗')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="page">
    <n-card title="SisterMart 後台" class="card">
      <n-form @submit.prevent="submit">
        <n-form-item label="郵箱">
          <n-input v-model:value="email" placeholder="admin@sistermart.local" />
        </n-form-item>
        <n-form-item label="密碼">
          <n-input v-model:value="password" type="password" show-password-on="click" placeholder="密碼" @keyup.enter="submit" />
        </n-form-item>
        <n-button type="primary" block :loading="loading" attr-type="submit" @click="submit">登錄</n-button>
      </n-form>
      <p class="hint">本地種子帳號 admin@sistermart.local，密碼 admin123456。</p>
    </n-card>
  </div>
</template>

<style scoped>
.page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  background: #f6f3ee;
}

.card {
  width: min(24rem, calc(100% - 2rem));
}

.hint {
  margin: 1rem 0 0;
  color: #8a8378;
  font-size: 0.8125rem;
}
</style>
