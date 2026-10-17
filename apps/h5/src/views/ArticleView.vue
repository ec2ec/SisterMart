<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchArticle, type Article } from '../api/catalog'
import { useLocaleText } from '../composables/useLocaleText'

const { t } = useI18n()
const { text } = useLocaleText()
const route = useRoute()
const article = ref<Article | null>(null)

async function load() {
  const code = String(route.params.id || 'terms')
  try {
    article.value = await fetchArticle(code)
  } catch {
    article.value = null
  }
}

onMounted(load)
watch(() => route.params.id, load)

const title = computed(() => (article.value ? text(article.value.title) : t('auth.termsTitle')))
const body = computed(() => (article.value ? text(article.value.body) : ''))
</script>

<template>
  <div class="page article">
    <SubPageNav :title="title" />
    <article>
      <h1>{{ title }}</h1>
      <p>{{ body }}</p>
    </article>
  </div>
</template>

<style scoped>
.article {
  background: #fff;
}
article {
  padding: 1rem 1rem 2rem;
}
h1 {
  margin: 0 0 0.75rem;
  font-size: 1.25rem;
}
p {
  margin: 0;
  line-height: 1.7;
  color: #333;
  white-space: pre-wrap;
}
</style>
