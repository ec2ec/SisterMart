<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchPointsMall } from '../api/m7'
import { useLocaleText } from '../composables/useLocaleText'
import { isLoggedIn, memberState, setSession, token, type Member } from '../composables/useSession'
import { assetUrl } from '../utils/assetUrl'

const { t } = useI18n()
const { text } = useLocaleText()
const router = useRouter()
const items = ref<Awaited<ReturnType<typeof fetchPointsMall>>['items']>([])
const loading = ref(true)
const loadError = ref('')

const myPoints = computed(() => {
  if (!isLoggedIn()) return 0
  return Math.max(0, Math.floor(Number(memberState.value?.points ?? 0)))
})

async function refreshPoints() {
  if (!isLoggedIn()) return
  try {
    const data = await api<Member>('/api/v1/members/me', {}, 'member')
    setSession(token(), data)
  } catch {
    /* keep cached points */
  }
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const [, data] = await Promise.all([refreshPoints(), fetchPointsMall()])
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
    <SubPageNav :title="t('account.pointsMall')">
      <template #right>
        <span class="my-pts">{{ t('m7.myPoints', { n: myPoints }) }}</span>
      </template>
    </SubPageNav>
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
.my-pts {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--sm-primary, #e85d8e);
  white-space: nowrap;
  max-width: 7.5rem;
  overflow: hidden;
  text-overflow: ellipsis;
}
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
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
  padding: 0.75rem 1rem;
}
.grid button {
  min-width: 0;
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
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  margin-top: 0.35rem;
  font-size: 0.8125rem;
  line-height: 1.35;
  min-height: calc(1.35em * 2);
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
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
