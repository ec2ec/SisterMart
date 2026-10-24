<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showDialog } from 'vant'
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

function plainPreview(html: string) {
  return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
}

function unreadCount(list: Msg[]) {
  return list.reduce((n, m) => n + (m.read ? 0 : 1), 0)
}

/** van-tab badge：0 不顯示 */
const orderBadge = computed(() => {
  const n = unreadCount(order.value)
  return n > 0 ? n : undefined
})
const systemBadge = computed(() => {
  const n = unreadCount(system.value)
  return n > 0 ? n : undefined
})

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
  const body = text(msg.body)
  const looksHtml = /<[a-z][\s\S]*>/i.test(body)
  // 訂單消息（含賣家留言）先看全文，再可進訂單
  if (msg.order_id) {
    try {
      await showDialog({
        title: text(msg.title),
        message: looksHtml ? body : body || msg.order_no || '',
        allowHtml: looksHtml,
        showCancelButton: true,
        confirmButtonText: t('m7.viewOrder'),
        cancelButtonText: t('pages.back'),
      })
      router.push({ name: 'order-detail', params: { id: msg.order_id } })
    } catch {
      /* 取消 */
    }
    return
  }
  await showDialog({
    title: text(msg.title),
    message: looksHtml ? body : body,
    allowHtml: looksHtml,
    confirmButtonText: t('pages.back'),
  })
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('pages.messages')" />
    <van-tabs v-model:active="tab">
      <van-tab :title="t('m7.orderMsg')" name="order" :badge="orderBadge" />
      <van-tab :title="t('m7.systemMsg')" name="system" :badge="systemBadge" />
    </van-tabs>
    <van-cell-group inset>
      <van-cell
        v-for="m in tab === 'order' ? order : system"
        :key="m.id"
        :title="text(m.title)"
        :label="plainPreview(text(m.body))"
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
