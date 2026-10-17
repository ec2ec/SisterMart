<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchMessages, markMessageRead, type Msg } from '../api/m7'
import { useLocaleText } from '../composables/useLocaleText'
import { isLoggedIn } from '../composables/useSession'
import { refreshUnread } from '../composables/useUnread'

const { t } = useI18n()
const { text } = useLocaleText()
const router = useRouter()
const tab = ref<'order' | 'system'>('order')
const order = ref<Msg[]>([])
const system = ref<Msg[]>([])

onMounted(async () => {
  if (!isLoggedIn()) {
    router.replace({ name: 'login', query: { redirect: '/messages' } })
    return
  }
  const data = await fetchMessages()
  order.value = data.order
  system.value = data.system
  await refreshUnread()
})

async function open(msg: Msg) {
  await markMessageRead(msg.id)
  msg.read = true
  await refreshUnread()
  if (msg.order_id) {
    router.push({ name: 'order-detail', params: { id: msg.order_id } })
  }
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('pages.messages')" />
    <van-tabs v-model:active="tab">
      <van-tab :title="t('m7.orderMsg')" name="order" />
      <van-tab :title="t('m7.systemMsg')" name="system" />
    </van-tabs>
    <van-cell-group inset>
      <van-cell
        v-for="m in tab === 'order' ? order : system"
        :key="m.id"
        :title="text(m.title)"
        :label="text(m.body)"
        :value="m.created_at.slice(5, 16)"
        :class="{ unread: !m.read }"
        is-link
        @click="open(m)"
      />
    </van-cell-group>
  </div>
</template>

<style scoped>
.unread :deep(.van-cell__title) {
  font-weight: 700;
}
</style>
