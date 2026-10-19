<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { toggleLocale } from '../i18n'
import { loggedIn, syncSessionFromStorage } from '../composables/useSession'
import { refreshUnread, unreadCount } from '../composables/useUnread'

withDefaults(
  defineProps<{
    showLogo?: boolean
    /** solid: 白底；overlay: 浮在轮播上（透明） */
    variant?: 'solid' | 'overlay'
  }>(),
  { variant: 'solid' },
)

const { t } = useI18n()
const router = useRouter()

onMounted(() => {
  refreshUnread()
})
watch(loggedIn, () => refreshUnread())

function goSearch() {
  router.push({ name: 'search' })
}

function goMessages() {
  syncSessionFromStorage()
  if (!loggedIn.value) {
    router.push({ name: 'login', query: { redirect: '/messages' } })
    return
  }
  router.push({ name: 'messages' })
}
</script>

<template>
  <header class="app-header" :class="variant">
    <div
      v-if="showLogo"
      class="logo"
      role="button"
      tabindex="0"
      @click="toggleLocale"
      @keydown.enter="toggleLocale"
    >
      <span class="logo-sister">{{ t('brandSister') }}</span><span class="logo-mart">{{ t('brandMart') }}</span>
    </div>
    <van-search
      class="search"
      shape="round"
      background="transparent"
      :placeholder="t('searchPlaceholder')"
      readonly
      @click-input="goSearch"
    />
    <div class="actions">
      <button
        class="action-btn"
        type="button"
        :aria-label="t('pages.wishlist')"
        @click="router.push({ name: 'wishlist' })"
      >
        <van-icon name="like-o" size="1.125rem" color="var(--sm-primary)" />
      </button>
      <van-badge :content="unreadCount > 0 ? (unreadCount > 99 ? '99+' : unreadCount) : ''" :show-zero="false">
        <button class="action-btn" type="button" :aria-label="t('pages.messages')" @click="goMessages">
          <van-icon name="envelop-o" size="1.125rem" color="var(--sm-primary)" />
        </button>
      </van-badge>
    </div>
  </header>
</template>

<style scoped>
.app-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: var(--sm-header-h);
  padding: 0.375rem 0.625rem;
  padding-top: calc(0.375rem + env(safe-area-inset-top, 0rem));
}

.app-header.solid {
  background: #fff;
}

.app-header.overlay {
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.72) 0%, rgba(255, 255, 255, 0) 100%);
  color: #111;
}

.logo {
  flex: 0 0 auto;
  font-size: 0.9375rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  white-space: nowrap;
  cursor: pointer;
  line-height: 1;
  user-select: none;
}

.logo-sister {
  color: #111;
}

.overlay .logo-sister {
  color: #111;
}

.logo-mart {
  color: var(--sm-primary);
}

.overlay .logo-mart {
  color: var(--sm-primary);
}

.search {
  flex: 1;
  padding: 0;
}

.search :deep(.van-search__content) {
  background: rgba(255, 255, 255, 0.92);
}

.solid .search :deep(.van-search__content) {
  background: #f2f3f5;
}

.actions {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  flex: 0 0 auto;
}

/* 與商品卡願望鈕同圓形樣式；邊長對齊搜索框高度；底色與搜索框一致 */
.action-btn {
  width: var(--van-search-input-height, 34px);
  height: var(--van-search-input-height, 34px);
  border: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.92);
  display: grid;
  place-items: center;
  box-shadow: 0 0.0625rem 0.25rem rgba(0, 0, 0, 0.12);
  padding: 0;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}

.solid .action-btn {
  background: #f2f3f5;
  box-shadow: none;
}
</style>
