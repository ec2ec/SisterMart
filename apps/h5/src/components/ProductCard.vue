<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import type { Product } from '../mock/data'
import { useLocaleText } from '../composables/useLocaleText'

const props = defineProps<{
  product: Product
}>()

const { t } = useI18n()
const { text } = useLocaleText()
const router = useRouter()

const name = computed(() => text(props.product.name))
const badgeText = computed(() => (props.product.badge ? t(`badge.${props.product.badge}`) : ''))

function openDetail() {
  router.push({ name: 'product-detail', params: { id: props.product.id } })
}
</script>

<template>
  <article class="card" role="link" tabindex="0" @click="openDetail" @keydown.enter="openDetail">
    <div class="media">
      <img :src="product.image" :alt="name" />
      <span v-if="product.badge" class="badge" :class="product.badge">{{ badgeText }}</span>
      <button class="wish" type="button" aria-label="wishlist" @click.stop>
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
  cursor: pointer;
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
  color: var(--sm-primary);
  font-weight: 700;
}

.sold {
  font-size: 11px;
  color: var(--sm-text-muted);
}
</style>
