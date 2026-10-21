<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchPointsMall } from '../api/m7'
import { useLocaleText } from '../composables/useLocaleText'
import { assetUrl } from '../utils/assetUrl'

const { t } = useI18n()
const { text } = useLocaleText()
const router = useRouter()
const items = ref<Awaited<ReturnType<typeof fetchPointsMall>>['items']>([])
const loading = ref(true)
const loadError = ref('')

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const data = await fetchPointsMall()
    items.value = data.items || []
  } catch (e) {
    items.value = []
    loadError.value = e instanceof Error && e.message ? e.message : t('m7.loadFail')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('account.pointsMall')" />
    <p v-if="loading" class="hint">{{ t('m7.loading') }}</p>
    <p v-else-if="loadError" class="hint err">{{ loadError }}</p>
    <p v-else-if="!items.length" class="hint">{{ t('m7.pointsEmpty') }}</p>
    <div v-else class="grid">
      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        @click="router.push({ name: 'points-mall-detail', params: { id: item.id } })"
      >
        <img :src="assetUrl(item.image)" alt="" />
        <strong>{{ text(item.name) }}</strong>
        <span>{{ item.points_cost }} {{ t('account.points') }}</span>
        <em>{{ t('pdp.stock', { n: item.stock }) }}</em>
      </button>
    </div>
  </div>
</template>

<style scoped>
.hint {
  margin: 1.5rem 1rem;
  text-align: center;
  color: #999;
  font-size: 0.875rem;
}
.hint.err {
  color: #c45c26;
}
.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
}
.grid button {
  border: 0;
  background: #fff;
  text-align: left;
  padding: 0;
}
.grid img {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 0.5rem;
}
.grid strong {
  display: block;
  margin-top: 0.35rem;
  font-size: 0.8125rem;
}
.grid span {
  color: var(--sm-primary);
  font-size: 0.75rem;
}
.grid em {
  display: block;
  font-style: normal;
  font-size: 0.6875rem;
  color: #999;
}
</style>
