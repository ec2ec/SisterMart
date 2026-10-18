<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast, type UploaderFileListItem } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import {
  createReview,
  fetchOrder,
  followReview,
  uploadReviewImage,
  type Order,
} from '../api/trade'
import { useLocaleText } from '../composables/useLocaleText'
import { ApiError } from '../api/client'

const { t } = useI18n()
const { text } = useLocaleText()
const route = useRoute()
const router = useRouter()

const order = ref<Order | null>(null)
const stars = ref(5)
const body = ref('')
const followBody = ref('')
const fileList = ref<UploaderFileListItem[]>([])
const submitting = ref(false)
const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'

const itemId = computed(() => Number(route.query.item || 0) || null)
const item = computed(() => (order.value?.items || []).find((i) => i.id === itemId.value) || null)
const isFollow = computed(() => Boolean(item.value?.reviewed && item.value?.can_follow))

async function load() {
  order.value = await fetchOrder(String(route.params.id))
  stars.value = 5
  body.value = ''
  followBody.value = ''
  fileList.value = []
  if (!item.value) {
    showToast(t('review.itemMissing'))
    return
  }
  if (!item.value.can_review && !item.value.can_follow) {
    showToast(t('review.notEligible'))
  }
}

watch(() => [route.params.id, route.query.item], load, { immediate: true })

function assetUrl(u: string) {
  return u.startsWith('http') ? u : `${apiBase}${u}`
}

async function afterRead(entryOrList: UploaderFileListItem | UploaderFileListItem[]) {
  const list = Array.isArray(entryOrList) ? entryOrList : [entryOrList]
  for (const entry of list) {
    const file = entry.file
    if (!file) continue
    if (file.size > 5 * 1024 * 1024) {
      showToast(t('review.imageSize'))
      fileList.value = fileList.value.filter((f) => f !== entry)
      continue
    }
    entry.status = 'uploading'
    try {
      const data = await uploadReviewImage(file)
      entry.url = assetUrl(data.url)
      entry.status = 'done'
      ;(entry as UploaderFileListItem & { remoteUrl?: string }).remoteUrl = data.url
    } catch (error) {
      entry.status = 'failed'
      showToast(error instanceof Error ? error.message : t('review.imageFail'))
      fileList.value = fileList.value.filter((f) => f !== entry)
    }
  }
}

async function submit() {
  if (!order.value || !item.value) return
  submitting.value = true
  try {
    if (isFollow.value && item.value.review_id) {
      if (!followBody.value.trim()) {
        showToast(t('review.followRequired'))
        return
      }
      await followReview(item.value.review_id, followBody.value.trim())
      showToast(t('review.followDone'))
    } else {
      if (!body.value.trim()) {
        showToast(t('review.bodyRequired'))
        return
      }
      const images = fileList.value
        .map((f) => (f as UploaderFileListItem & { remoteUrl?: string }).remoteUrl || '')
        .filter(Boolean)
      if (!item.value.id) {
        showToast(t('review.itemMissing'))
        return
      }
      await createReview({
        order_item_id: item.value.id,
        stars: stars.value,
        body: body.value.trim(),
        images,
      })
      showToast(t('review.done'))
    }
    router.replace({ name: 'order-detail', params: { id: order.value.id } })
  } catch (error) {
    const msg =
      error instanceof ApiError ? error.message : error instanceof Error ? error.message : t('review.fail')
    showToast(msg)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="isFollow ? t('review.followTitle') : t('review.title')" />
    <section v-if="item" class="card">
      <div class="line">
        <img v-if="item.image" :src="item.image" alt="" />
        <div>
          <h3>{{ text(item.name) }}</h3>
          <p>{{ text(item.spec) }}</p>
        </div>
      </div>

      <template v-if="!isFollow">
        <p class="lab">{{ t('review.stars') }}</p>
        <div class="stars">
          <button
            v-for="n in 5"
            :key="n"
            type="button"
            class="star"
            :class="{ on: n <= stars }"
            @click="stars = n"
          >
            ★
          </button>
        </div>
        <van-field
          v-model="body"
          type="textarea"
          rows="4"
          maxlength="1000"
          show-word-limit
          :placeholder="t('review.bodyPlaceholder')"
        />
        <p class="lab">{{ t('review.images') }}</p>
        <p class="hint">{{ t('review.imageHint') }}</p>
        <van-uploader v-model="fileList" :max-count="3" :after-read="afterRead" />
      </template>
      <template v-else>
        <van-field
          v-model="followBody"
          type="textarea"
          rows="4"
          maxlength="1000"
          show-word-limit
          :placeholder="t('review.followPlaceholder')"
        />
      </template>

      <van-button
        type="primary"
        color="var(--sm-primary)"
        block
        round
        :loading="submitting"
        :disabled="!item.can_review && !item.can_follow"
        style="margin-top: 1rem"
        @click="submit"
      >
        {{ t('review.submit') }}
      </van-button>
    </section>
  </div>
</template>

<style scoped>
.card {
  margin: 0.75rem;
  padding: 0.875rem;
  background: #fff;
  border-radius: 0.75rem;
}
.line {
  display: grid;
  grid-template-columns: 4rem 1fr;
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.line img {
  width: 4rem;
  height: 4rem;
  object-fit: cover;
  border-radius: 0.5rem;
}
.line h3 {
  margin: 0 0 0.25rem;
  font-size: 0.95rem;
}
.line p {
  margin: 0;
  color: #888;
  font-size: 0.8125rem;
}
.lab {
  margin: 0.75rem 0 0.35rem;
  font-size: 0.875rem;
  font-weight: 600;
}
.hint {
  margin: 0 0 0.5rem;
  color: #999;
  font-size: 0.75rem;
}
.stars {
  display: flex;
  gap: 0.35rem;
  margin-bottom: 0.75rem;
}
.star {
  border: 0;
  background: transparent;
  font-size: 1.5rem;
  color: #ddd;
  padding: 0;
  line-height: 1;
}
.star.on {
  color: #e6a23c;
}
</style>
