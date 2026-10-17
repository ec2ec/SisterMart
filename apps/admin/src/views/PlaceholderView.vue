<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { NCard, NTag } from 'naive-ui'

const route = useRoute()
const title = computed(() => String(route.meta.title || '模塊'))
const milestone = computed(() => String(route.meta.milestone || '後續'))
</script>

<template>
  <n-card :title="title">
    <n-tag size="small">{{ milestone }}</n-tag>
    <p>此模塊在後續里程碑開放。側欄已按一期信息架構放好，目前可維護的是設置裡的底部導航。</p>
  </n-card>
</template>

<style scoped>
p {
  margin: 0.75rem 0 0;
  color: #6d675f;
}
</style>
