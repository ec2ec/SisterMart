<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'

const { t } = useI18n()
const router = useRouter()
const email = ref('')
const password = ref('')

function submit() {
  showToast(t('pages.coming'))
  router.replace({ name: 'account' })
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('pages.register')" />
    <div class="form">
      <van-field v-model="email" :label="t('pages.email')" :placeholder="t('home.emailPlaceholder')" />
      <van-field
        v-model="password"
        type="password"
        :label="t('pages.password')"
        :placeholder="t('pages.password')"
      />
      <van-button block round color="var(--sm-primary)" class="btn" @click="submit">
        {{ t('pages.registerSubmit') }}
      </van-button>
      <button type="button" class="link" @click="router.push({ name: 'login' })">
        {{ t('pages.hasAccount') }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.form {
  padding: 16px;
}

.btn {
  margin-top: 20px;
}

.link {
  display: block;
  margin: 16px auto 0;
  border: 0;
  background: transparent;
  color: var(--sm-primary);
  font-size: 13px;
}
</style>
