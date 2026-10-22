<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  title: string
  hint?: string
}>()

const { t } = useI18n()
</script>

<template>
  <div class="auth">
    <div class="logo">
      <span class="sister">{{ t('brandSister') }}</span><span class="mart">{{ t('brandMart') }}</span>
    </div>
    <h1>{{ title }}</h1>
    <p v-if="hint" class="hint">{{ hint }}</p>
    <slot />
  </div>
</template>

<style scoped>
.auth {
  min-height: 100vh;
  padding: 2.5rem 1.375rem 2rem;
  background: #fff;
}

.logo {
  text-align: center;
  font-size: 1.375rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1;
}

.sister {
  color: var(--sm-brand-sister);
}

.mart {
  color: var(--sm-primary);
}

h1 {
  margin: 1.25rem 0 0.25rem;
  font-size: 1.25rem;
  font-weight: 700;
}

.hint {
  margin: 0 0 0.25rem;
  color: var(--sm-text-secondary);
  font-size: 0.75rem;
  line-height: 1.5;
}

.auth :deep(.seg) {
  display: grid;
  grid-template-columns: 1fr 1fr;
  margin-top: 0.75rem;
  padding: 0.1875rem;
  border-radius: 0.5rem;
  background: #efeae3;
}

.auth :deep(.seg button) {
  border: 0;
  border-radius: 0.375rem;
  background: transparent;
  padding: 0.4375rem 0;
  font-size: 0.8125rem;
  color: #3f3a34;
}

.auth :deep(.seg button.on) {
  background: #fff;
  font-weight: 700;
  color: #1c1c1c;
}

.auth :deep(.lab) {
  display: block;
  margin-top: 0.75rem;
  font-size: 0.8125rem;
}

.auth :deep(.field) {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  height: 2.625rem;
  margin-top: 0.5rem;
  padding: 0 0.75rem;
  border: 0;
  border-radius: 0.5rem;
  background: #f4f7ff;
  color: #1c1c1c;
  font-size: 0.8125rem;
}

.auth :deep(.field input) {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  font-size: 0.8125rem;
  color: #1c1c1c;
}

.auth :deep(.field::placeholder),
.auth :deep(.field input::placeholder) {
  color: #7b8494;
}

.auth :deep(.eye),
.auth :deep(.send) {
  flex: none;
  border: 0;
  background: transparent;
  padding: 0;
  color: var(--sm-primary);
  font-size: 0.8125rem;
  font-weight: 600;
}

.auth :deep(.eye) {
  color: #7b8494;
  display: grid;
  place-items: center;
}

.auth :deep(.send:disabled) {
  color: #999;
}

.auth :deep(.forgot) {
  display: block;
  width: 100%;
  margin: 0.5rem 0 0;
  border: 0;
  background: transparent;
  padding: 0;
  text-align: right;
  font-size: 0.8125rem;
  color: #3f3a34;
}

.auth :deep(.check) {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  margin: 0.875rem 0 0;
  font-size: 0.75rem;
  line-height: 1.45;
  color: #3f3a34;
}

.auth :deep(.check input) {
  width: 0.875rem;
  height: 0.875rem;
  margin-top: 0.125rem;
  accent-color: var(--sm-primary);
  flex: none;
}

.auth :deep(.check button) {
  border: 0;
  background: transparent;
  padding: 0;
  color: var(--sm-primary);
  font-size: inherit;
}

.auth :deep(.submit) {
  width: 100%;
  height: 2.75rem;
  margin-top: 0.75rem;
  border: 0;
  border-radius: 0.5rem;
  background: var(--sm-primary);
  color: #fff;
  font-size: 0.9375rem;
  font-weight: 700;
}

.auth :deep(.center) {
  margin: 0.875rem 0 0;
  text-align: center;
  font-size: 0.8125rem;
  color: #3f3a34;
}

.auth :deep(.center button) {
  border: 0;
  background: transparent;
  padding: 0;
  color: #1c1c1c;
  font-size: inherit;
  font-weight: 700;
}

.auth :deep(.note),
.auth :deep(.slogan) {
  margin: 0.875rem 0 0;
  color: var(--sm-text-muted);
  font-size: 0.75rem;
  line-height: 1.5;
}

.auth :deep(.slogan) {
  text-align: center;
}
</style>
