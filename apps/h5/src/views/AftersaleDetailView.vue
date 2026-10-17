<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { cancelAftersale, fetchAftersale, type Aftersale } from '../api/trade'
import { useLocaleText } from '../composables/useLocaleText'

const { t } = useI18n()
const { text } = useLocaleText()
const route = useRoute()
const row = ref<Aftersale | null>(null)
const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'

function assetUrl(u: string) {
  return u.startsWith('http') ? u : `${apiBase}${u}`
}

async function load() {
  row.value = await fetchAftersale(String(route.params.id))
}

onMounted(load)

async function onCancel() {
  if (!row.value) return
  await cancelAftersale(row.value.id)
  showToast(t('orders.cancelled'))
  await load()
}

const statusKey: Record<string, string> = {
  pending_review: 'fulfill.stReview',
  awaiting_return: 'fulfill.stReturn',
  pending_refund: 'fulfill.stRefund',
  completed: 'fulfill.stDone',
  rejected: 'fulfill.stRejected',
  cancelled: 'orders.cancelled',
}
</script>

<template>
  <div class="page" v-if="row">
    <SubPageNav :title="t('fulfill.detail')" />
    <section class="card">
      <p>{{ row.aftersale_no }}</p>
      <h2>{{ t(statusKey[row.status] || row.status) }}</h2>
      <p v-if="row.item">{{ text(row.item.name) }} · HK$ {{ row.amount.toFixed(2) }}</p>
      <p>{{ t('fulfill.reason') }}：{{ row.description || row.reason }}</p>
      <p v-if="row.admin_note">{{ row.admin_note }}</p>
      <div v-if="row.evidence?.length" class="evidence">
        <p class="label">{{ t('fulfill.evidence') }}</p>
        <div class="grid">
          <a v-for="(u, i) in row.evidence" :key="i" :href="assetUrl(u)" target="_blank" rel="noreferrer">
            <img :src="assetUrl(u)" alt="" />
          </a>
        </div>
      </div>
      <van-button v-if="row.status === 'pending_review'" block plain round style="margin-top: 1rem" @click="onCancel">
        {{ t('fulfill.withdraw') }}
      </van-button>
    </section>
  </div>
</template>

<style scoped>
.card {
  margin: 0.75rem;
  padding: 0.875rem;
  background: #fff;
  border-radius: 0.75rem;
}
.label {
  margin: 0.75rem 0 0.35rem;
  font-size: 0.875rem;
  font-weight: 600;
}
.grid {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.grid img {
  width: 4.5rem;
  height: 4.5rem;
  object-fit: cover;
  border-radius: 0.375rem;
}
</style>
