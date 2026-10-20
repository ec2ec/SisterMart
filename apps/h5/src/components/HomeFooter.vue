<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const props = defineProps<{
  slogan?: { 'zh-HK': string; en: string } | null
}>()

const { t, locale } = useI18n()
const router = useRouter()

/** 三欄佈局（與後台頁腳欄目一致） */
const columns = [
  ['about', 'contact', 'service'],
  ['terms', 'privacy', 'payment'],
  ['shipping', 'refund', 'return'],
] as const

const sloganText = computed(() => {
  if (props.slogan) {
    const loc = String(locale.value || 'zh-HK')
    return loc.startsWith('en')
      ? props.slogan.en || props.slogan['zh-HK']
      : props.slogan['zh-HK'] || props.slogan.en
  }
  return t('home.slogan')
})

function openArticle(code: string) {
  router.push({ name: 'article', params: { id: code } })
}
</script>

<template>
  <footer class="footer">
    <div class="cols">
      <div v-for="(col, i) in columns" :key="i" class="col">
        <button
          v-for="k in col"
          :key="k"
          type="button"
          class="link"
          @click="openArticle(k)"
        >
          {{ t(`footer.${k}`) }}
        </button>
      </div>
    </div>
    <p class="slogan">{{ sloganText }}</p>
  </footer>
</template>

<style scoped>
.footer {
  padding: 1.25rem 1.5rem 1.75rem;
  background: #fff;
}

.cols {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.5rem 1rem;
}

.col {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.link {
  display: block;
  width: 100%;
  margin: 0;
  padding: 0.375rem 0;
  border: 0;
  background: transparent;
  text-align: left;
  font: inherit;
  font-size: 0.8125rem;
  color: #666;
  cursor: pointer;
}

.slogan {
  margin: 1.125rem 0 0;
  text-align: center;
  font-size: 0.75rem;
  color: #aaa;
}
</style>
