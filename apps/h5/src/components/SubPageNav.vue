<script setup lang="ts">
import { useNavBack } from '../composables/useNavBack'

const props = withDefaults(
  defineProps<{ title: string; fallback?: string }>(),
  { fallback: '/' },
)

const { goBack } = useNavBack(() => props.fallback || '/')
</script>

<template>
  <van-nav-bar
    class="sub-nav"
    :title="title"
    left-arrow
    fixed
    placeholder
    :border="false"
    @click-left="goBack"
  >
    <template v-if="$slots.right" #right>
      <slot name="right" />
    </template>
  </van-nav-bar>
</template>

<style scoped>
/* 固定頂欄蓋過列表卡片／心願鈕，避免上滑遮擋 */
.sub-nav {
  z-index: 300;
  background: #fff;
}
</style>
