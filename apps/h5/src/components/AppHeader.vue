<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { toggleLocale } from '../i18n'

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

function goSearch() {
  router.push({ name: 'search' })
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
      <van-icon name="like-o" size="20" @click="router.push({ name: 'wishlist' })" />
      <van-badge dot>
        <van-icon name="envelop-o" size="20" @click="router.push({ name: 'messages' })" />
      </van-badge>
    </div>
  </header>
</template>

<style scoped>
.app-header {
  z-index: 20;
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: var(--sm-header-h);
  padding: 6px 10px;
  padding-top: calc(6px + env(safe-area-inset-top, 0px));
}

.app-header.solid {
  position: sticky;
  top: 0;
  background: #fff;
}

.app-header.overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  background: linear-gradient(180deg, rgba(0, 0, 0, 0.35) 0%, rgba(0, 0, 0, 0) 100%);
  color: #fff;
}

.logo {
  flex: 0 0 auto;
  font-size: 15px;
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
  color: #fff;
}

.logo-mart {
  color: var(--sm-primary);
}

.overlay .logo-mart {
  color: #ffb4a2;
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
  gap: 12px;
  padding-right: 4px;
  color: #333;
}

.overlay .actions {
  color: #fff;
}
</style>
