<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { MaintenanceStatus } from '../composables/useMaintenance'
import { useLocaleText } from '../composables/useLocaleText'

const props = defineProps<{
  status: MaintenanceStatus
}>()

const emit = defineEmits<{ retry: [] }>()

const { t } = useI18n()
const { text } = useLocaleText()

const title = computed(() => {
  const n = text(props.status.title)
  return n || t('maintenance.title')
})

const message = computed(() => {
  const n = text(props.status.message)
  return n || t('maintenance.message')
})

function fmt(dt: string | null) {
  if (!dt) return ''
  // 2026-12-12 12:35:00 → 2026-12-12 12:35
  return dt.replace(/:\d{2}$/, '').replace('T', ' ')
}

const windowText = computed(() => {
  const a = fmt(props.status.starts_at)
  const b = fmt(props.status.ends_at)
  if (a && b) return t('maintenance.window', { start: a, end: b })
  if (a) return t('maintenance.windowStart', { start: a })
  if (b) return t('maintenance.windowEnd', { end: b })
  return ''
})
</script>

<template>
  <div class="maint">
    <div class="bg" aria-hidden="true" />
    <div class="card">
      <p class="brand">
        <span class="sister">{{ t('brandSister') }}</span><span class="mart">{{ t('brandMart') }}</span>
      </p>
      <div class="icon" aria-hidden="true">
        <van-icon name="setting-o" size="2rem" />
      </div>
      <h1>{{ title }}</h1>
      <p v-if="windowText" class="window">{{ windowText }}</p>
      <p class="msg">{{ message }}</p>
      <p class="thanks">{{ t('maintenance.thanks') }}</p>
      <button type="button" class="retry" @click="emit('retry')">
        {{ t('maintenance.retry') }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.maint {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  box-sizing: border-box;
  overflow: auto;
}
.bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 80% 50% at 20% 10%, rgba(196, 92, 74, 0.12), transparent 55%),
    radial-gradient(ellipse 70% 45% at 90% 80%, rgba(22, 22, 43, 0.08), transparent 50%),
    linear-gradient(165deg, #f7f4ef 0%, #efeae3 45%, #e8e4dc 100%);
}
.card {
  position: relative;
  width: min(22rem, 100%);
  padding: 2rem 1.5rem 1.5rem;
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.92);
  border: 0.0625rem solid rgba(227, 221, 212, 0.9);
  box-shadow: 0 0.75rem 2rem rgba(28, 28, 28, 0.06);
  text-align: center;
}
.brand {
  margin: 0 0 1.25rem;
  font-size: 1.125rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1;
}
.sister {
  color: var(--sm-brand-sister);
}
.mart {
  color: var(--sm-accent);
}
.icon {
  width: 3.5rem;
  height: 3.5rem;
  margin: 0 auto 1rem;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: var(--sm-bg-soft);
  color: var(--sm-text-secondary);
}
h1 {
  margin: 0 0 0.75rem;
  font-size: 1.375rem;
  font-weight: 700;
  color: var(--sm-text);
}
.window {
  margin: 0 0 0.875rem;
  padding: 0.625rem 0.75rem;
  border-radius: 0.5rem;
  background: #f6f3ee;
  color: var(--sm-text);
  font-size: 0.8125rem;
  line-height: 1.45;
  font-variant-numeric: tabular-nums;
}
.msg {
  margin: 0 0 0.5rem;
  font-size: 0.875rem;
  line-height: 1.55;
  color: var(--sm-text-secondary);
}
.thanks {
  margin: 0 0 1.25rem;
  font-size: 0.8125rem;
  color: var(--sm-text-muted);
}
.retry {
  width: 100%;
  border: 0;
  border-radius: 999px;
  padding: 0.75rem 1rem;
  background: var(--sm-primary);
  color: #fff;
  font: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
}
.retry:active {
  opacity: 0.9;
}
</style>
