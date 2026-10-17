<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchPoster } from '../api/m7'
import { useLocaleText } from '../composables/useLocaleText'

const { t } = useI18n()
const { text } = useLocaleText()
const poster = ref<Awaited<ReturnType<typeof fetchPoster>> | null>(null)

onMounted(async () => {
  poster.value = await fetchPoster('p1')
})

function copy() {
  if (!poster.value) return
  const url = new URL(poster.value.qr_payload, location.origin).toString()
  navigator.clipboard?.writeText(url).catch(() => undefined)
  showToast(t('pdp.copied'))
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('m7.poster')" />
    <div class="poster" v-if="poster">
      <img :src="poster.image" alt="" />
      <h2>{{ text(poster.name) }}</h2>
      <p>HK$ {{ poster.price }}</p>
      <div class="qr">{{ poster.qr_payload }}</div>
      <p class="ref">ref={{ poster.promo_code }}</p>
      <van-button round color="var(--sm-primary)" @click="copy">{{ t('m7.copyLink') }}</van-button>
    </div>
  </div>
</template>

<style scoped>
.poster {
  margin: 1rem;
  padding: 1rem;
  border-radius: 1rem;
  background: #fff8f0;
  text-align: center;
}
.poster img {
  width: 70%;
  border-radius: 0.5rem;
}
.qr {
  margin: 1rem auto;
  padding: 0.75rem;
  background: #fff;
  border: 1px dashed #ccc;
  font-size: 0.7rem;
  word-break: break-all;
  max-width: 16rem;
}
.ref {
  font-size: 0.8125rem;
  color: #666;
}
</style>
