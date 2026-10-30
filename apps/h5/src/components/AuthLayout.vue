<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { toggleLocale } from '../i18n'

withDefaults(
  defineProps<{
    title: string
    hint?: string
    /** 左上角返回 */
    showBack?: boolean
    /** 右上角語言切換 */
    showLocale?: boolean
  }>(),
  {
    showBack: false,
    showLocale: false,
  },
)

const emit = defineEmits<{
  back: []
}>()

const { t, locale } = useI18n()

/** 按鈕表示「即將切換到」的語言 */
const nextLocaleFlag = computed(() => (locale.value === 'zh-HK' ? '🇺🇸' : '🇭🇰'))
const nextLocaleLabel = computed(() => t('account.switchLang'))

function onBackClick() {
  emit('back')
}
</script>

<template>
  <div class="auth" :class="{ 'has-top': showBack || showLocale }">
    <header v-if="showBack || showLocale" class="auth-top">
      <button
        v-if="showBack"
        type="button"
        class="sm-icon-btn auth-icon-btn"
        :aria-label="t('pages.back')"
        @click="onBackClick"
      >
        <van-icon name="arrow-left" size="1.25rem" />
      </button>
      <span v-else class="auth-top-spacer" aria-hidden="true" />
      <button
        v-if="showLocale"
        type="button"
        class="auth-lang sm-pressable"
        :title="nextLocaleLabel"
        :aria-label="nextLocaleLabel"
        @click="toggleLocale"
      >
        <span class="flag" aria-hidden="true">{{ nextLocaleFlag }}</span>
        <span>{{ nextLocaleLabel }}</span>
      </button>
      <span v-else class="auth-top-spacer" aria-hidden="true" />
    </header>

    <div class="auth-bg" aria-hidden="true">
      <div class="mesh mesh-base" />
      <div class="mesh-move mesh-move-a">
        <div class="mesh-paint mesh-paint-a" />
      </div>
      <div class="mesh-move mesh-move-b">
        <div class="mesh-paint mesh-paint-b" />
      </div>
      <!-- 流水反射：外層 transform、內層 blur，避免 WebKit 合成卡死 -->
      <div class="caustic-move">
        <div class="caustic-paint" />
      </div>
      <div class="shimmer-move">
        <div class="shimmer-paint" />
      </div>
      <div class="grain" />
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
  /* 底色：粉紫 → 薰衣草 → 青藍（與全息參考圖一致） */
  background: #e8d4f0;
  color: #1c1c1c;
}

.auth.has-top {
  padding-top: calc(3.25rem + env(safe-area-inset-top, 0rem));
}

.auth-top {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: calc(0.5rem + env(safe-area-inset-top, 0rem)) 0.75rem 0.5rem;
  pointer-events: none;
}

.auth-top > * {
  pointer-events: auto;
}

.auth-top-spacer {
  width: var(--sm-icon-btn, 2.75rem);
  height: var(--sm-icon-btn, 2.75rem);
  flex: none;
}

.auth-icon-btn {
  background: rgba(255, 255, 255, 0.55);
  color: #14141f;
  box-shadow: 0 0.15rem 0.5rem rgba(80, 50, 100, 0.1);
}

.auth-lang {
  flex: none;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  border: 0;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.55);
  padding: 0.35rem 0.65rem;
  color: #14141f;
  font-size: 0.75rem;
  font-weight: 600;
  box-shadow: 0 0.15rem 0.5rem rgba(80, 50, 100, 0.1);
}

.auth-lang .flag {
  font-size: 0.95rem;
  line-height: 1;
}

.auth-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  overflow: hidden;
}

/*
 * 晃動一律「外層 transform / 內層 filter+paint」拆開，
 * 避免 WebKit 在同節點上 filter+transform 視覺卡死。
 * 色塊對比與行程刻意加大，否則柔色+blur 肉眼幾乎看不出動。
 */
.mesh,
.mesh-move,
.caustic-move,
.shimmer-move,
.grain {
  display: block;
  position: absolute;
  pointer-events: none;
}

.mesh-base {
  inset: -20%;
  width: 140%;
  height: 140%;
  background:
    radial-gradient(70% 55% at 18% 12%, #fff8fc 0%, transparent 58%),
    radial-gradient(65% 50% at 92% 16%, #9ecfff 0%, transparent 52%),
    radial-gradient(60% 50% at 72% 82%, #b89af5 0%, transparent 55%),
    radial-gradient(70% 55% at 12% 78%, #ff7ec0 0%, transparent 55%),
    radial-gradient(55% 45% at 48% 48%, #e8d0ff 0%, transparent 60%),
    linear-gradient(155deg, #f7d6e8 0%, #e4d4f6 42%, #cfe4fa 100%);
  background-size: 140% 140%;
  will-change: transform, background-position;
  animation: auth-base-flow 7s ease-in-out infinite alternate;
}

.mesh-move {
  inset: -55%;
  will-change: transform;
}

.mesh-move-a {
  animation: auth-drift-a 4.5s ease-in-out infinite alternate;
}

.mesh-move-b {
  animation: auth-drift-b 5.5s ease-in-out infinite alternate;
}

.mesh-paint {
  position: absolute;
  inset: 0;
  display: block;
}

.mesh-paint-a {
  filter: blur(1.1rem);
  opacity: 1;
  background:
    radial-gradient(34% 28% at 18% 22%, rgba(255, 255, 255, 1) 0%, transparent 70%),
    radial-gradient(40% 34% at 82% 14%, rgba(90, 180, 255, 1) 0%, transparent 68%),
    radial-gradient(46% 38% at 58% 56%, rgba(150, 80, 255, 0.95) 0%, transparent 70%),
    radial-gradient(42% 36% at 18% 80%, rgba(255, 70, 170, 0.95) 0%, transparent 68%),
    radial-gradient(32% 28% at 90% 74%, rgba(60, 200, 255, 0.9) 0%, transparent 66%);
}

.mesh-paint-b {
  filter: blur(0.95rem);
  opacity: 0.95;
  background:
    radial-gradient(28% 24% at 36% 30%, rgba(255, 255, 255, 1) 0%, transparent 70%),
    radial-gradient(26% 22% at 70% 44%, rgba(255, 130, 200, 0.95) 0%, transparent 70%),
    radial-gradient(28% 24% at 44% 76%, rgba(130, 180, 255, 0.9) 0%, transparent 70%);
}

.caustic-move {
  inset: -70%;
  will-change: transform;
  animation: auth-caustic 3.2s linear infinite;
}

.caustic-paint {
  position: absolute;
  inset: 0;
  display: block;
  opacity: 0.9;
  mix-blend-mode: soft-light;
  filter: blur(0.18rem);
  transform: rotate(-12deg) scale(1.35);
  background:
    repeating-linear-gradient(
      118deg,
      transparent 0 0.55rem,
      rgba(255, 255, 255, 0.55) 0.55rem 0.95rem,
      transparent 0.95rem 1.85rem,
      rgba(255, 255, 255, 0.78) 1.85rem 2.35rem,
      transparent 2.35rem 3.4rem
    ),
    repeating-linear-gradient(
      132deg,
      transparent 0 0.9rem,
      rgba(180, 230, 255, 0.55) 0.9rem 1.35rem,
      transparent 1.35rem 2.5rem,
      rgba(255, 180, 230, 0.45) 2.5rem 2.95rem,
      transparent 2.95rem 4.1rem
    );
}

.shimmer-move {
  inset: -40%;
  will-change: transform;
  animation: auth-shimmer 4s ease-in-out infinite alternate;
}

.shimmer-paint {
  position: absolute;
  inset: 0;
  display: block;
  opacity: 0.55;
  mix-blend-mode: overlay;
  filter: blur(0.55rem);
  background: linear-gradient(
    105deg,
    transparent 0%,
    transparent 38%,
    rgba(255, 255, 255, 0.75) 48%,
    rgba(210, 235, 255, 0.55) 52%,
    transparent 62%,
    transparent 100%
  );
}

.grain {
  inset: 0;
  opacity: 0.06;
  mix-blend-mode: overlay;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  background-size: 180px 180px;
}

@keyframes auth-base-flow {
  0% {
    transform: translate3d(-4%, -3%, 0) scale(1.02);
    background-position: 0% 20%, 100% 0%, 80% 100%, 0% 100%, 50% 50%, 0% 0%;
  }
  100% {
    transform: translate3d(4%, 3%, 0) scale(1.08);
    background-position: 30% 0%, 60% 30%, 40% 70%, 20% 60%, 40% 40%, 100% 100%;
  }
}

@keyframes auth-drift-a {
  0% {
    transform: translate3d(-22vw, -14vh, 0) scale(1.05) rotate(-9deg);
  }
  100% {
    transform: translate3d(20vw, 14vh, 0) scale(1.28) rotate(9deg);
  }
}

@keyframes auth-drift-b {
  0% {
    transform: translate3d(18vw, 10vh, 0) scale(1.04) rotate(7deg);
  }
  100% {
    transform: translate3d(-20vw, -14vh, 0) scale(1.26) rotate(-8deg);
  }
}

@keyframes auth-caustic {
  0% {
    transform: translate3d(-28vw, 4vh, 0) rotate(-2deg);
  }
  100% {
    transform: translate3d(28vw, -10vh, 0) rotate(2deg);
  }
}

@keyframes auth-shimmer {
  0% {
    transform: translate3d(-30%, 8%, 0) skewX(-8deg);
  }
  100% {
    transform: translate3d(30%, -8%, 0) skewX(8deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .mesh-base,
  .mesh-move-a,
  .mesh-move-b,
  .caustic-move,
  .shimmer-move {
    animation: none;
  }
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
  opacity: 0.65;
}

.auth :deep(.send.is-busy),
.auth :deep(.submit.is-busy) {
  pointer-events: none;
  opacity: 0.85;
}

.auth :deep(.btn-spin) {
  display: inline-block;
  width: 0.875rem;
  height: 0.875rem;
  margin-right: 0.35rem;
  border: 0.125rem solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  vertical-align: -0.1rem;
  animation: sm-btn-spin 0.65s linear infinite;
}

@keyframes sm-btn-spin {
  to {
    transform: rotate(360deg);
  }
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

.auth :deep(.check.shake) {
  animation: sm-agree-shake 0.42s ease-in-out 0s 2;
}

@keyframes sm-agree-shake {
  0%,
  100% {
    transform: translateX(0);
  }
  25% {
    transform: translateX(-0.4rem);
  }
  75% {
    transform: translateX(0.4rem);
  }
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

.auth :deep(.submit:disabled) {
  opacity: 0.7;
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
