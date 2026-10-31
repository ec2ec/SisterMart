<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { toggleLocale } from '../i18n'
import { goLogin } from '../composables/useAuth'
import { clearFavorites, favoriteCount, hydrateFavorites } from '../composables/useFavorites'
import { loggedIn, syncSessionFromStorage } from '../composables/useSession'
import { refreshUnread, unreadCount } from '../composables/useUnread'

withDefaults(
  defineProps<{
    showLogo?: boolean
    /** 商詳疊層等：左側返回，關閉疊層並還原底層 Tab */
    showBack?: boolean
    /** solid: 白底；overlay: 浮在轮播上（透明） */
    variant?: 'solid' | 'overlay'
  }>(),
  { variant: 'solid' },
)

const emit = defineEmits<{ back: [] }>()

const { t } = useI18n()
const router = useRouter()

onMounted(() => {
  refreshUnread()
  if (loggedIn.value) void hydrateFavorites()
})
watch(loggedIn, (v) => {
  refreshUnread()
  if (v) void hydrateFavorites()
  else clearFavorites()
})

function goSearch() {
  router.push({ name: 'search' })
}

function goWishlist() {
  syncSessionFromStorage()
  if (!loggedIn.value) {
    void goLogin(router, '/wishlist')
    return
  }
  router.push({ name: 'wishlist' })
}

function goMessages() {
  syncSessionFromStorage()
  if (!loggedIn.value) {
    void goLogin(router, '/messages')
    return
  }
  router.push({ name: 'messages' })
}

function onBack() {
  emit('back')
}
</script>

<template>
  <header class="app-header" :class="variant">
    <button
      v-if="showBack"
      type="button"
      class="sm-icon-btn back-btn"
      :aria-label="t('pages.back')"
      @click="onBack"
    >
      <van-icon name="arrow-left" size="1.125rem" />
    </button>
    <button
      v-else-if="showLogo"
      type="button"
      class="logo sm-pressable"
      @click="toggleLocale"
    >
      <span class="logo-sister">{{ t('brandSister') }}</span><span class="logo-mart">{{ t('brandMart') }}</span>
    </button>
    <van-search
      class="search"
      shape="round"
      background="transparent"
      :placeholder="t('searchPlaceholder')"
      readonly
      @click-input="goSearch"
    />
    <div class="actions">
      <van-badge
        :content="loggedIn && favoriteCount > 0 ? (favoriteCount > 99 ? '99+' : favoriteCount) : ''"
        :show-zero="false"
      >
        <button
          class="sm-icon-btn action-btn"
          type="button"
          :aria-label="t('pages.wishlist')"
          @click="goWishlist"
        >
          <van-icon name="like-o" size="1.125rem" color="var(--sm-primary)" />
        </button>
      </van-badge>
      <van-badge
        :content="loggedIn && unreadCount > 0 ? (unreadCount > 99 ? '99+' : unreadCount) : ''"
        :show-zero="false"
      >
        <button
          class="sm-icon-btn action-btn"
          type="button"
          :aria-label="t('pages.messages')"
          @click="goMessages"
        >
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

/* 圓鈕外框與搜索框同高，視覺協調（覆蓋全局 .sm-icon-btn 2.75rem） */
.back-btn,
.action-btn {
  width: var(--van-search-input-height);
  height: var(--van-search-input-height);
}

.back-btn {
  background: #f2f3f5;
  color: var(--sm-text);
}

.logo {
  flex: 0 0 auto;
  border: 0;
  background: transparent;
  padding: 0.25rem 0.35rem;
  border-radius: 0.5rem;
  font-size: 1.2rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  white-space: nowrap;
  line-height: 1;
  user-select: none;
  color: inherit;
}

.logo-sister {
  color: var(--sm-brand-sister);
}

.overlay .logo-sister {
  color: var(--sm-brand-sister);
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

/* 熱區用 .sm-icon-btn；底色與搜索框一致 */
.action-btn {
  background: rgba(255, 255, 255, 0.92);
  box-shadow: 0 0.0625rem 0.25rem rgba(0, 0, 0, 0.12);
}

.solid .action-btn {
  background: #f2f3f5;
  box-shadow: none;
}

/* 頂欄心願／消息角標位置微調（其餘 Vant 屬性不變） */
.actions :deep(.van-badge--top-right) {
  top: 0.2rem;
  right: 0.2rem;
}
</style>
