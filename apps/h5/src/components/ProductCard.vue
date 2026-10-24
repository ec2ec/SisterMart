<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import type { ProductCard } from '../api/catalog'
import { useLocaleText } from '../composables/useLocaleText'
import { isFavorite, requestFavorite } from '../composables/useFavorites'
import { assetUrl } from '../utils/assetUrl'

const props = defineProps<{
  product: ProductCard
}>()

const { t } = useI18n()
const { text } = useLocaleText()
const router = useRouter()
const route = useRoute()

const name = computed(() => text(props.product.name))
const badgeItems = computed(() => {
  if (props.product.badge_items?.length) return props.product.badge_items
  return (props.product.badges ?? []).map((code) => ({
    code,
    color: code === 'editor' ? 'var(--sm-badge-alt)' : 'var(--sm-primary)',
    name: { 'zh-HK': code, en: code },
  }))
})
const liked = computed(() => isFavorite(props.product.id))
const showOrigin = computed(() => {
  const origin = Number(props.product.compare_at ?? 0)
  return origin > 0 && origin > Number(props.product.price)
})
const ratingText = computed(() => {
  const count = Number(props.product.rating_count ?? 0)
  const rating = Number(props.product.rating ?? 0)
  if (count <= 0 || rating <= 0) return ''
  return rating.toFixed(1)
})

function badgeLabel(item: { code: string; name: { 'zh-HK': string; en: string } }) {
  const fromApi = text(item.name)
  if (fromApi && fromApi !== item.code) return fromApi
  const key = `badge.${item.code}`
  const fromI18n = t(key)
  return fromI18n !== key ? fromI18n : fromApi || item.code
}

function openDetail() {
  router.push({ name: 'product-detail', params: { id: props.product.id } })
}

function onWish() {
  requestFavorite(props.product.id, route.fullPath, (to) => router.push(to))
}
</script>

<template>
  <article class="card" role="link" tabindex="0" @click="openDetail" @keydown.enter="openDetail">
    <div class="media">
      <img :src="assetUrl(product.image)" :alt="name" />
      <span v-if="badgeItems.length" class="badges">
        <span
          v-for="badge in badgeItems"
          :key="badge.code"
          class="badge"
          :style="{ background: badge.color }"
        >{{ badgeLabel(badge) }}</span>
      </span>
      <button class="wish" type="button" :aria-label="t('pdp.fav')" @click.stop="onWish">
        <van-icon :name="liked ? 'like' : 'like-o'" color="var(--sm-primary)" />
      </button>
    </div>
    <div class="meta">
      <h3>{{ name }}</h3>
      <div class="row">
        <span class="price-wrap">
          <s v-if="showOrigin" class="origin">HK$ {{ product.compare_at }}</s>
          <span class="price">HK$ {{ product.price }}</span>
        </span>
        <span class="meta-right">
          <span v-if="ratingText" class="rating">⭐{{ ratingText }}</span>
          <span class="sold">{{ t('home.sold', { n: product.sold }) }}</span>
        </span>
      </div>
    </div>
  </article>
</template>

<style scoped>
.card {
  min-width: 0;
  background: #fff;
  cursor: pointer;
}

.media {
  position: relative;
  border-radius: 0.625rem;
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

.badges {
  position: absolute;
  top: 0.375rem;
  left: 0.375rem;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.25rem;
}

.badge {
  padding: 0.0625rem 0.375rem;
  border-radius: 0.5rem;
  background: var(--sm-primary);
  color: #fff;
  font-size: 0.625rem;
  line-height: 1.4;
}

.wish {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  width: 1.75rem;
  height: 1.75rem;
  border: 0;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.95);
  display: grid;
  place-items: center;
  box-shadow: 0 0.0625rem 0.25rem rgba(0, 0, 0, 0.12);
}

.meta {
  min-width: 0;
  padding: 0.5rem 0.125rem 0.25rem;
}

.meta h3 {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #222;
  line-height: 1.35;
  /* 預留兩行高度，避免短標題與長標題同行價格錯位 */
  min-height: calc(1.35em * 2);
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
}

.row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.375rem;
  margin-top: 0.25rem;
}

.price-wrap {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.25rem 0.375rem;
  min-width: 0;
}

.price {
  font-size: 0.875rem;
  color: var(--sm-text);
  font-weight: 700;
}

.origin {
  font-size: 0.6875rem;
  color: var(--sm-text-muted);
  font-weight: 400;
  text-decoration: line-through;
}

.meta-right {
  display: flex;
  align-items: baseline;
  gap: 0.35rem;
  flex-shrink: 0;
  max-width: 55%;
  justify-content: flex-end;
  flex-wrap: wrap;
}
.rating {
  font-size: 0.6875rem;
  color: #e6a23c;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
.sold {
  font-size: 0.6875rem;
  color: var(--sm-text-muted);
}
</style>
