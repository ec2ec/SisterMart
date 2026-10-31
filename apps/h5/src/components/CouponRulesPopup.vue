<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { assetUrl } from '../utils/assetUrl'

const props = defineProps<{
  show: boolean
  title?: string
  html: string
}>()

const emit = defineEmits<{ 'update:show': [boolean]; close: [] }>()
const { t } = useI18n()

function rewriteAssets(raw: string) {
  return raw.replace(/(src=["'])(\/[^"']+)(["'])/gi, (_, a, path, b) => `${a}${assetUrl(path)}${b}`)
}

const bodyHtml = computed(() => rewriteAssets(props.html || ''))

function onUpdate(v: boolean) {
  emit('update:show', v)
  if (!v) emit('close')
}
</script>

<template>
  <van-popup
    :show="show"
    round
    closeable
    position="bottom"
    teleport="body"
    class="coupon-rules-pop"
    :style="{ height: '70%' }"
    @update:show="onUpdate"
  >
    <div class="inner">
      <h2 class="title">{{ title || t('m6.rules') }}</h2>
      <div class="body">
        <div v-if="bodyHtml.trim()" class="rich" v-html="bodyHtml" />
        <p v-else class="empty">{{ t('m6.rulesEmpty') }}</p>
      </div>
    </div>
  </van-popup>
</template>

<style scoped>
.inner {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  background: #fff;
}
.title {
  flex: none;
  margin: 0;
  padding: 1rem 3rem 0.75rem 1.25rem;
  font-size: 1.0625rem;
  font-weight: 700;
  color: var(--sm-text);
  text-align: center;
}
.body {
  flex: 1;
  min-height: 0;
  overflow: auto;
  -webkit-overflow-scrolling: touch;
  padding: 0 1.25rem 1.5rem;
  font-size: 0.8rem;
  line-height: 1.6;
  color: #3b3e42;
}
.empty {
  margin: 2rem 0;
  text-align: center;
  color: #999;
  font-size: 0.875rem;
}
.rich :deep(img) {
  max-width: 100%;
  height: auto;
}
.rich :deep(p) {
  margin: 0 0 0.75rem;
  line-height: inherit;
  font-size: inherit;
  color: inherit;
}
</style>
