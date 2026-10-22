<script setup lang="ts">
import { NConfigProvider, NDialogProvider, NMessageProvider, zhTW, dateZhTW } from 'naive-ui'
</script>

<template>
  <n-config-provider :locale="zhTW" :date-locale="dateZhTW">
    <n-dialog-provider>
      <n-message-provider>
        <router-view />
      </n-message-provider>
    </n-dialog-provider>
  </n-config-provider>
</template>
