<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { showToast } from 'vant'
import 'vant/es/toast/style'

const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'
const health = ref<string>('checking…')
const healthOk = ref(false)

onMounted(async () => {
  try {
    const res = await fetch(`${apiBase}/health`)
    const data = await res.json()
    healthOk.value = Boolean(data.ok)
    health.value = data.ok
      ? `API ok · ThinkPHP ${data.thinkphp} · DB ${data.db?.ok ? 'connected' : 'down'}`
      : `API unhealthy · ${data.db?.error ?? res.status}`
  } catch (e) {
    healthOk.value = false
    health.value = `API unreachable (${apiBase})`
  }
})

function onCta() {
  showToast({ message: 'SisterMart H5 · M1 shell', position: 'bottom' })
}
</script>

<template>
  <div class="page">
    <header class="hero">
      <p class="brand">SisterMart</p>
      <h1>H5 scaffold</h1>
      <p class="sub">Vue 3 · Vant 4 · zh-HK / en（i18n 自 M2）</p>
    </header>

    <main class="body">
      <van-cell-group inset>
        <van-cell title="Milestone" value="M1" />
        <van-cell title="UI" value="Vant 4" />
        <van-cell title="Health">
          <template #value>
            <span :class="healthOk ? 'ok' : 'bad'">{{ health }}</span>
          </template>
        </van-cell>
      </van-cell-group>

      <div class="actions">
        <van-button type="primary" block round @click="onCta">Try Vant toast</van-button>
      </div>
    </main>
  </div>
</template>

<style scoped>
.page {
  min-height: 100vh;
  background:
    radial-gradient(120% 80% at 20% 0%, #f3e7d3 0%, transparent 55%),
    linear-gradient(180deg, #faf6f0 0%, #efe6da 100%);
  color: #2a2118;
  font-family: "Source Han Sans HK", "Noto Sans TC", "PingFang HK", sans-serif;
}

.hero {
  padding: 2.5rem 1.25rem 1.25rem;
}

.brand {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}

h1 {
  margin: 0.35rem 0 0;
  font-size: 1.15rem;
  font-weight: 600;
}

.sub {
  margin: 0.5rem 0 0;
  font-size: 0.9rem;
  opacity: 0.75;
}

.body {
  padding: 0.5rem 0 2rem;
}

.actions {
  margin: 1.25rem;
}

.ok {
  color: #1b7a3d;
}

.bad {
  color: #b42318;
}
</style>
