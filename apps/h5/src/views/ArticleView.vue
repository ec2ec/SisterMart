<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import SubPageNav from '../components/SubPageNav.vue'

const { t } = useI18n()
const route = useRoute()

const article = computed(() => {
  const id = String(route.params.id || 'terms')
  if (id === 'privacy') {
    return { title: t('auth.privacyTitle'), body: t('auth.privacyBody') }
  }
  return { title: t('auth.termsTitle'), body: t('auth.termsBody') }
})
</script>

<template>
  <div class="page article">
    <SubPageNav :title="article.title" />
    <article>
      <h1>{{ article.title }}</h1>
      <p>{{ article.body }}</p>
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
  color: #3f3a34;
  font-size: 0.875rem;
  line-height: 1.7;
}
</style>
