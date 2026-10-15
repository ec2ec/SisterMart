<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchSeckillSessions, type SeckillSession } from '../api/m7'
import { useLocaleText } from '../composables/useLocaleText'

const { t } = useI18n()
const { text } = useLocaleText()
const router = useRouter()
const sessions = ref<SeckillSession[]>([])

onMounted(async () => {
  sessions.value = (await fetchSeckillSessions()).items
})
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('m7.seckill')" />
    <p class="mock">{{ t('m7.mockBanner') }}</p>
    <section v-for="s in sessions" :key="s.id" class="session">
      <div class="head">
        <h2>{{ text(s.name) }}</h2>
        <span :class="s.status">{{ s.status === 'live' ? t('m7.live') : t('m7.upcoming') }}</span>
      </div>
      <p class="time">{{ s.starts_at }} → {{ s.ends_at }}</p>
      <div class="row">
        <button
          v-for="p in s.products"
          :key="p.id"
          type="button"
          class="card"
          @click="router.push({ name: 'seckill-detail', params: { id: s.id }, query: { product: p.product_id } })"
        >
          <img :src="p.image" alt="" />
          <strong>{{ text(p.name) }}</strong>
          <span class="price">HK$ {{ p.seckill_price }} <s>HK$ {{ p.origin_price }}</s></span>
        </button>
      </div>
    </section>
  </div>
</template>

<style scoped>
.mock {
  margin: 0.5rem 1rem;
  font-size: 0.75rem;
  color: #a67c52;
}
.session {
  margin: 0.75rem 1rem 1.25rem;
}
.head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.head h2 {
  margin: 0;
  font-size: 1rem;
}
.head span {
  font-size: 0.75rem;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
}
.head .live {
  background: #ffe8e0;
  color: #c45c26;
}
.head .upcoming {
  background: #eee;
  color: #666;
}
.time {
  margin: 0.25rem 0 0.75rem;
  font-size: 0.75rem;
  color: #999;
}
.row {
  display: flex;
  gap: 0.75rem;
  overflow-x: auto;
}
.card {
  flex: 0 0 8.5rem;
  border: 0;
  background: #fff;
  text-align: left;
  padding: 0;
}
.card img {
  width: 8.5rem;
  height: 8.5rem;
  object-fit: cover;
  border-radius: 0.5rem;
  display: block;
}
.card strong {
  display: block;
  font-size: 0.8125rem;
  margin-top: 0.35rem;
}
.price {
  font-size: 0.75rem;
  color: var(--sm-primary);
}
.price s {
  color: #aaa;
  margin-left: 0.25rem;
}
</style>
