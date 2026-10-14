<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Product } from '../mock/data'

const props = defineProps<{
  product: Product
  soldPrefix?: boolean
}>()

const { t, locale } = useI18n()

const name = computed(() => props.product.name[locale.value as 'zh-HK' | 'en'])
const badgeText = computed(() => {
  if (!props.product.badge) return ''
  return t(`badge.${props.product.badge}`)
})
</script>

<template>
  <article class="card">
    <div class="media">
      <img :src="product.image" :alt="name" />
      <span v-if="product.badge" class="badge" :class="product.badge">{{ badgeText }}</span>
      <button class="wish" type="button" aria-label="wishlist">
        <van-icon name="like-o" color="var(--sm-primary)" />
      </button>
    </div>
    <div class="meta">
      <h3>{{ name }}</h3>
      <div class="row">
        <span class="price">HK$ {{ product.price }}</span>
        <span class="sold">{{ t('home.sold', { n: product.sold }) }}</span>
      </div>
    </div>
  </article>
</template>

<style scoped>
.card {
  background: #fff;
}

.media {
  position: relative;
  border-radius: 10px;
  overflow: hidden;
  background: #f5f5f5;
  aspect-ratio: 1 / 1;
}

.media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.badge {
  position: absolute;
  top: 0;
  left: 0;
  padding: 3px 8px;
  font-size: 11px;
  color: #fff;
  background: var(--sm-primary);
  border-bottom-right-radius: 8px;
}

.badge.editor {
  background: #6b4f3a;
}

.wish {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 28px;
  height: 28px;
  border: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.95);
  display: grid;
  place-items: center;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
}

.meta {
  padding: 8px 2px 4px;
}

.meta h3 {
  margin: 0;
  font-size: 13px;
  font-weight: 600;
  color: #222;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 6px;
  margin-top: 4px;
}

.price {
  font-size: 14px;
}

.sold {
  font-size: 11px;
  color: var(--sm-text-muted);
}
</style>
