<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppTabbar from './components/AppTabbar.vue'

const route = useRoute()
const showTabbar = computed(() => ['home', 'category', 'cart', 'account'].includes(String(route.name)))
</script>

<template>
  <router-view />
  <AppTabbar v-if="showTabbar" />
</template>
