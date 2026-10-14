<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { toggleLocale } from '../i18n'

defineProps<{
  showLogo?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <header class="app-header">
    <div v-if="showLogo" class="logo" @click="toggleLocale" title="toggle locale">
      <span class="logo-sister">{{ t('brandSister') }}</span><span class="logo-mart">{{ t('brandMart') }}</span>
    </div>
    <van-search
      class="search"
      shape="round"
      background="transparent"
      :placeholder="t('searchPlaceholder')"
      readonly
    />
    <div class="actions">
      <van-icon name="like-o" size="20" />
      <van-badge dot>
        <van-icon name="envelop-o" size="20" />
      </van-badge>
    </div>
  </header>
</template>

<style scoped>
.app-header {
  position: sticky;
  top: 0;
  z-index: 20;
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: var(--sm-header-h);
  padding: 6px 10px;
  background: #fff;
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
  color: #111 !important;
}

.logo-mart {
  color: var(--sm-primary) !important;
}

.search {
  flex: 1;
  padding: 0;
}

.search :deep(.van-search__content) {
  background: #f2f3f5;
}

.actions {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-right: 4px;
  color: #333;
}
</style>
