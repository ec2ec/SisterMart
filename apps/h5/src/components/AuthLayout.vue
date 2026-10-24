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
    <div class="auth-bg" aria-hidden="true">
      <span class="orb orb-a" />
      <span class="orb orb-b" />
      <span class="orb orb-c" />
      <span class="grid" />
      <span class="wave" />
    </div>
    <div class="auth-inner">
      <div class="logo">
        <span class="sister">{{ t('brandSister') }}</span><span class="mart">{{ t('brandMart') }}</span>
      </div>
      <h1>{{ title }}</h1>
      <p v-if="hint" class="hint">{{ hint }}</p>
      <slot />
    </div>
  </div>
</template>

<style scoped>
.auth {
  position: relative;
  isolation: isolate;
  min-height: 100vh;
  min-height: 100dvh;
  padding: 2.25rem 1.375rem 2.5rem;
  overflow: hidden;
  /* 參考圖：粉 → lavender → 淺藍 */
  background: linear-gradient(
    165deg,
    #f8d4e4 0%,
    #f0d8ee 28%,
    #e4daf6 55%,
    #d8e8f8 82%,
    #cfe6f6 100%
  );
  color: #1c1c1c;
}

.auth-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  overflow: hidden;
}

.grid {
  position: absolute;
  inset: -10%;
  opacity: 0.22;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.55) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.55) 1px, transparent 1px);
  background-size: 1.75rem 1.75rem;
  mask-image: radial-gradient(ellipse 80% 60% at 50% 20%, #000 20%, transparent 75%);
}

.orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(2rem);
  opacity: 0.7;
}

.orb-a {
  top: -4rem;
  right: -3rem;
  width: 14rem;
  height: 14rem;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.95) 0%, rgba(255, 182, 212, 0.35) 55%, transparent 70%);
}

.orb-b {
  top: 28%;
  left: -5rem;
  width: 12rem;
  height: 12rem;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.85) 0%, rgba(210, 190, 255, 0.4) 50%, transparent 70%);
}

.orb-c {
  bottom: 8%;
  right: -4rem;
  width: 13rem;
  height: 13rem;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.8) 0%, rgba(180, 220, 255, 0.45) 50%, transparent 70%);
}

.wave {
  position: absolute;
  left: -10%;
  right: -10%;
  bottom: 6%;
  height: 6rem;
  opacity: 0.45;
  background:
    radial-gradient(120% 80% at 10% 80%, transparent 40%, rgba(255, 255, 255, 0.55) 41%, transparent 42%),
    radial-gradient(100% 70% at 55% 70%, transparent 42%, rgba(255, 255, 255, 0.4) 43%, transparent 44%),
    radial-gradient(110% 75% at 90% 85%, transparent 40%, rgba(255, 255, 255, 0.5) 41%, transparent 42%);
}

.auth-inner {
  position: relative;
  z-index: 1;
  max-width: 24rem;
  margin: 0 auto;
}

.logo {
  text-align: center;
  font-size: 1.5rem;
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
  margin: 1.5rem 0 0.35rem;
  font-size: 1.625rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #14141f;
}

.hint {
  margin: 0 0 0.35rem;
  color: #3a3540;
  font-size: 0.8125rem;
  line-height: 1.5;
}

/* —— 分段：Email / Phone —— */
.auth :deep(.seg) {
  display: grid;
  grid-template-columns: 1fr 1fr;
  margin-top: 1rem;
  padding: 0.25rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.38);
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.35);
  backdrop-filter: blur(8px);
}

.auth :deep(.seg button) {
  border: 0;
  border-radius: 999px;
  background: transparent;
  padding: 0.55rem 0;
  font-size: 0.875rem;
  color: #5a5560;
  transition: background 0.15s ease, box-shadow 0.15s ease, color 0.15s ease;
}

.auth :deep(.seg button.on) {
  background: #fff;
  font-weight: 700;
  color: #14141f;
  box-shadow: 0 0.2rem 0.65rem rgba(80, 50, 100, 0.12);
}

.auth :deep(.lab) {
  display: block;
  margin-top: 0.95rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #1c1c1c;
}

.auth :deep(.field) {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
  height: 3rem;
  margin-top: 0.45rem;
  padding: 0 1rem;
  border: 0.0625rem solid rgba(120, 90, 160, 0.18);
  border-radius: 999px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.72) 0%, rgba(236, 228, 255, 0.55) 100%);
  box-shadow:
    0 0.25rem 0.9rem rgba(160, 130, 210, 0.22),
    inset 0 0.0625rem 0 rgba(255, 255, 255, 0.65);
  color: #1c1c1c;
  font-size: 0.875rem;
  backdrop-filter: blur(10px);
}

.auth :deep(.field input) {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  font-size: 0.875rem;
  color: #1c1c1c;
}

.auth :deep(.field::placeholder),
.auth :deep(.field input::placeholder) {
  color: #9a94a3;
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
  color: #6b6574;
  display: grid;
  place-items: center;
}

.auth :deep(.send:disabled) {
  color: #999;
}

.auth :deep(.forgot) {
  display: block;
  width: 100%;
  margin: 0.55rem 0 0;
  border: 0;
  background: transparent;
  padding: 0;
  text-align: right;
  font-size: 0.8125rem;
  color: #3a3540;
}

.auth :deep(.check) {
  display: flex;
  align-items: flex-start;
  gap: 0.55rem;
  margin: 1rem 0 0;
  font-size: 0.75rem;
  line-height: 1.45;
  color: #3a3540;
}

.auth :deep(.check input) {
  width: 1rem;
  height: 1rem;
  margin-top: 0.1rem;
  accent-color: var(--sm-primary);
  flex: none;
  border-radius: 0.2rem;
}

.auth :deep(.check button) {
  border: 0;
  background: transparent;
  padding: 0;
  color: var(--sm-primary);
  font-size: inherit;
  font-weight: 600;
}

.auth :deep(.submit) {
  width: 100%;
  height: 3rem;
  margin-top: 1rem;
  border: 0;
  border-radius: 999px;
  background: linear-gradient(100deg, #1a1a3a 0%, #16162b 45%, #0c0c14 100%);
  color: #fff;
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: 0.01em;
  box-shadow: 0 0.45rem 1.1rem rgba(22, 22, 43, 0.35);
}

.auth :deep(.submit:active) {
  opacity: 0.92;
  transform: translateY(0.0625rem);
}

.auth :deep(.center) {
  margin: 1.1rem 0 0;
  text-align: center;
  font-size: 0.875rem;
  color: #3a3540;
}

.auth :deep(.center button) {
  border: 0;
  background: transparent;
  padding: 0;
  color: #14141f;
  font-size: inherit;
  font-weight: 700;
}

.auth :deep(.note),
.auth :deep(.slogan) {
  margin: 1rem 0 0;
  color: #6b6574;
  font-size: 0.75rem;
  line-height: 1.5;
}

.auth :deep(.slogan) {
  text-align: center;
  margin-top: 1.5rem;
}
</style>
