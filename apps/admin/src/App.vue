<script setup lang="ts">
import { NConfigProvider, NMessageProvider, zhTW, dateZhTW } from 'naive-ui'
</script>

<template>
  <n-config-provider :locale="zhTW" :date-locale="dateZhTW">
    <n-message-provider>
      <router-view />
    </n-message-provider>
  </n-config-provider>
</template>
