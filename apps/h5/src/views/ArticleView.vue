<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchArticle, type Article } from '../api/catalog'
import { useLocaleText } from '../composables/useLocaleText'

const { t } = useI18n()
const { text } = useLocaleText()
const route = useRoute()
const article = ref<Article | null>(null)
const loading = ref(true)
/** 進行中的請求序號，避免舊協議響應覆蓋新頁 */
let loadSeq = 0

async function load(code: string) {
  const seq = ++loadSeq
  // 先清空，避免協議 A→返回→協議 B 時短暫顯示 A 的正文
  article.value = null
  loading.value = true
  try {
    const next = await fetchArticle(code)
    if (seq !== loadSeq) return
    article.value = next
  } catch {
    if (seq !== loadSeq) return
    article.value = null
  } finally {
    if (seq === loadSeq) loading.value = false
  }
}

watch(
  () => String(route.params.id || 'terms'),
  (code) => {
    void load(code)
  },
  { immediate: true },
)

const title = computed(() => (article.value ? text(article.value.title) : ''))
const body = computed(() => (article.value ? text(article.value.body) : ''))
</script>

<template>
  <!-- 疊層勿用全局 .page（會預留 Tabbar 底距） -->
  <div class="article">
    <SubPageNav :title="title" />
    <article v-if="article">
      <h1>{{ title }}</h1>
      <div class="body" v-html="body" />
    </article>
    <p v-else-if="loading" class="empty">{{ t('m7.loading') }}</p>
    <p v-else class="empty">{{ t('m7.loadFail') }}</p>
  </div>
</template>

<style scoped>
.article {
  background: #fff;
  contain: layout style;
}
article {
  padding: 1rem 1rem 2rem;
}
h1 {
  margin: 0 0 0.75rem;
  font-size: 1.25rem;
}
.body {
  margin: 0;
  line-height: 1.7;
  color: #333;
  word-break: break-word;
}
.body :deep(img) {
  max-width: 100%;
  height: auto;
}
.body :deep(p) {
  margin: 0 0 0.75rem;
}
.body :deep(h2) {
  margin: 1.25rem 0 0.5rem;
  font-size: 1.05rem;
}
.body :deep(ul),
.body :deep(ol) {
  margin: 0 0 0.75rem;
  padding-left: 1.25rem;
}
.body :deep(li) {
  margin: 0.25rem 0;
}
.body :deep(a) {
  color: var(--sm-primary, #16162b);
}
.body :deep(.sm-contact-btn) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.5rem;
  padding: 0.55rem 1rem;
  border-radius: 999px;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.9375rem;
}
.body :deep(.sm-contact-wa) {
  background: #e8f8ef;
  color: #128c7e;
}
.body :deep(.sm-contact-fb) {
  background: #e8f1ff;
  color: #1877f2;
}
.empty {
  padding: 2rem 1rem;
  text-align: center;
  color: #999;
  font-size: 0.875rem;
}
</style>
