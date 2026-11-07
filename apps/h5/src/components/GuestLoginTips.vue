<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { goLogin } from '../composables/useAuth'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const shown = ref(false)
let timer = 0

onMounted(() => {
  document.documentElement.classList.add('sm-guest-login-tips')
  timer = window.setTimeout(() => {
    shown.value = true
  }, 2000)
})

onUnmounted(() => {
  window.clearTimeout(timer)
  document.documentElement.classList.remove('sm-guest-login-tips')
})

function onLogin() {
  void goLogin(router, route.fullPath)
}
</script>

<template>
  <div class="login-tips" :class="{ shown }" role="complementary" aria-label="login tips">
    <p class="tip-text">{{ t('tab.loginTips') }}</p>
    <button type="button" class="tip-btn sm-pressable" @click="onLogin">
      {{ t('auth.login') }}
    </button>
  </div>
</template>

<style scoped>
/* 夾在主畫面與 tabbar 之間：bottom 貼 tabbar 頂，z 低於 tabbar */
.login-tips {
  position: fixed;
  left: 0;
  right: 0;
  bottom: calc(var(--sm-tabbar-h) + env(safe-area-inset-bottom, 0rem));
  z-index: 90;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  height: var(--sm-login-tips-h);
  padding: 0 1rem;
  box-sizing: border-box;
  background: #16162b;
  color: #f6f3ee;
  transform: translateY(100%);
  opacity: 0;
  pointer-events: none;
  transition: transform 0.38s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.28s ease;
}

.login-tips.shown {
  transform: translateY(0);
  opacity: 1;
  pointer-events: auto;
}

.tip-text {
  margin: 0;
  flex: 1 1 auto;
  min-width: 0;
  font-size: 0.875rem;
  line-height: 1.35;
  font-weight: 600;
  letter-spacing: 0.01em;
  color: #f0ebe4;
}

.tip-btn {
  flex: 0 0 auto;
  border: 0.0625rem solid rgba(246, 243, 238, 0.55);
  border-radius: 999px;
  background: transparent;
  color: #f6f3ee;
  font-size: 0.8125rem;
  font-weight: 700;
  line-height: 1;
  padding: 0.45rem 0.95rem;
  min-height: 2rem;
}
</style>
