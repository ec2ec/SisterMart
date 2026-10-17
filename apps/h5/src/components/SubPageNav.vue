<script setup lang="ts">
import { useRouter } from 'vue-router'

defineProps<{ title: string }>()
const router = useRouter()
</script>

<template>
  <van-nav-bar
    :title="title"
    left-arrow
    fixed
    placeholder
    :border="false"
    @click-left="router.back()"
  />
</template>
