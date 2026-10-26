<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { fetchArticle, type Article } from '../api/catalog'
import { useLocaleText } from '../composables/useLocaleText'

const show = defineModel<boolean>('show', { default: false })
const props = defineProps<{
  /** 協議 code：terms / privacy 等 */
  code: string
}>()

const { t, locale } = useI18n()
const { text } = useLocaleText()
const article = ref<Article | null>(null)
const loading = ref(false)
let loadSeq = 0

async function load(code: string) {
  if (!code) return
  const seq = ++loadSeq
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
  () => [show.value, props.code, locale.value] as const,
  ([open, code]) => {
    if (open && code) void load(code)
  },
  { immediate: true },
)

const title = computed(() => (article.value ? text(article.value.title) : ''))
const body = computed(() => (article.value ? text(article.value.body) : ''))

function close() {
  show.value = false
}
</script>

<template>
  <van-popup
    v-model:show="show"
    position="bottom"
    round
    teleport="body"
    :close-on-click-overlay="true"
    class="article-pop"
    :style="{ height: '90%' }"
  >
    <div class="article-pop-inner">
      <header class="article-pop-head">
        <h2 class="article-pop-title">{{ title || t('m7.loading') }}</h2>
        <button
          type="button"
          class="article-pop-close"
          :aria-label="t('checkout.closePicker')"
          @click="close"
        >
          <van-icon name="cross" size="1.125rem" />
        </button>
      </header>
      <div class="article-pop-body">
        <div v-if="article" class="rich" v-html="body" />
        <p v-else-if="loading" class="empty">{{ t('m7.loading') }}</p>
        <p v-else class="empty">{{ t('m7.loadFail') }}</p>
      </div>
    </div>
  </van-popup>
</template>

<style scoped>
.article-pop-inner {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  background: #fff;
}

.article-pop-head {
  position: relative;
  flex: none;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 3rem;
  padding: 0.75rem 3rem;
  border-bottom: 0.0625rem solid #f0f0f0;
}

.article-pop-title {
  margin: 0;
  max-width: 100%;
  font-size: 1.0625rem;
  font-weight: 700;
  color: #14141f;
  text-align: center;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.article-pop-close {
  position: absolute;
  top: 50%;
  right: 0.65rem;
  transform: translateY(-50%);
  width: 2.25rem;
  height: 2.25rem;
  border: 0;
  border-radius: 50%;
  padding: 0;
  background: #f5f5f7;
  color: #333;
  display: grid;
  place-items: center;
}

.article-pop-body {
  flex: 1;
  min-height: 0;
  overflow: auto;
  -webkit-overflow-scrolling: touch;
  padding: 1rem 1rem calc(1.25rem + env(safe-area-inset-bottom, 0rem));
}

.rich {
  margin: 0;
  line-height: 1.7;
  color: #333;
  word-break: break-word;
  font-size: 0.9375rem;
}

.rich :deep(img) {
  max-width: 100%;
  height: auto;
}

.rich :deep(p) {
  margin: 0 0 0.75rem;
}

.rich :deep(h1),
.rich :deep(h2),
.rich :deep(h3) {
  margin: 1.1rem 0 0.5rem;
  font-size: 1.05rem;
  color: #14141f;
}

.rich :deep(ul),
.rich :deep(ol) {
  margin: 0 0 0.75rem;
  padding-left: 1.25rem;
}

.rich :deep(li) {
  margin: 0.25rem 0;
}

.rich :deep(a) {
  color: var(--sm-primary, #16162b);
}

.empty {
  margin: 2rem 0 0;
  text-align: center;
  color: #999;
  font-size: 0.875rem;
}
</style>

<style>
/* van-popup 根節點在 teleport 外，需非 scoped 調高度內布局 */
.article-pop.van-popup {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
</style>
