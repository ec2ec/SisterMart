<script setup lang="ts">
/**
 * TMP_SITE_GATE — 臨時測試門禁 UI。正式發佈前與 useTmpSiteGate 一併刪除。
 */
import { nextTick, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { markTmpSiteGatePassed, TMP_SITE_GATE_PASSWORD } from '../composables/useTmpSiteGate'

const emit = defineEmits<{ passed: [] }>()

const { t } = useI18n()
const password = ref('')
const error = ref('')
const inputEl = ref<HTMLInputElement | null>(null)

onMounted(() => {
  void nextTick(() => inputEl.value?.focus())
})

function submit() {
  error.value = ''
  const value = password.value.trim()
  if (value === TMP_SITE_GATE_PASSWORD) {
    markTmpSiteGatePassed()
    emit('passed')
    return
  }
  error.value = t('tmpGate.badPassword')
  password.value = ''
  void nextTick(() => inputEl.value?.focus())
}
</script>

<template>
  <div class="tmp-gate" role="dialog" aria-modal="true" :aria-label="t('tmpGate.title')">
    <div class="tmp-gate-card">
      <h1 class="tmp-gate-title">{{ t('tmpGate.title') }}</h1>
      <p class="tmp-gate-desc">{{ t('tmpGate.hint') }}</p>
      <form class="tmp-gate-form" @submit.prevent="submit">
        <input
          ref="inputEl"
          v-model="password"
          class="tmp-gate-input"
          type="password"
          inputmode="numeric"
          autocomplete="off"
          :placeholder="t('tmpGate.placeholder')"
        />
        <p v-if="error" class="tmp-gate-error">{{ error }}</p>
        <button type="submit" class="tmp-gate-btn sm-pressable">{{ t('tmpGate.submit') }}</button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.tmp-gate {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: grid;
  place-items: center;
  padding: 1.25rem;
  box-sizing: border-box;
  background: rgba(12, 12, 20, 0.72);
}

.tmp-gate-card {
  width: min(100%, 20rem);
  padding: 1.35rem 1.25rem 1.25rem;
  border-radius: 0.75rem;
  background: #fff;
  box-shadow: 0 0.75rem 2rem rgba(0, 0, 0, 0.28);
}

.tmp-gate-title {
  margin: 0;
  font-size: 1.0625rem;
  font-weight: 700;
  color: #16162b;
  text-align: center;
}

.tmp-gate-desc {
  margin: 0.45rem 0 0;
  font-size: 0.8125rem;
  line-height: 1.45;
  color: #6d675f;
  text-align: center;
}

.tmp-gate-form {
  margin-top: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
}

.tmp-gate-input {
  width: 100%;
  box-sizing: border-box;
  border: 0.0625rem solid #e3ddd4;
  border-radius: 0.5rem;
  padding: 0.7rem 0.85rem;
  font-size: 1rem;
  letter-spacing: 0.12em;
  text-align: center;
  outline: none;
  -webkit-user-select: text;
  user-select: text;
}

.tmp-gate-input:focus {
  border-color: #16162b;
}

.tmp-gate-error {
  margin: 0;
  font-size: 0.8125rem;
  color: #c45c4a;
  text-align: center;
}

.tmp-gate-btn {
  border: 0;
  border-radius: 999px;
  background: #16162b;
  color: #fff;
  font-size: 0.9375rem;
  font-weight: 700;
  padding: 0.7rem 1rem;
  min-height: 2.75rem;
}
</style>
