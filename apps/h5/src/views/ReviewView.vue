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
import { assetUrl } from '../utils/assetUrl'
import {
  H5_IMAGE_MAX_UPLOAD_BYTES,
  H5ImageTooLargeError,
  prepareH5UploadImage,
} from '../utils/h5ImageUpload'

const MAX_REVIEW_IMAGES = 5

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

const itemId = computed(() => Number(route.query.item || 0) || null)
const item = computed(() => (order.value?.items || []).find((i) => i.id === itemId.value) || null)
const isFollow = computed(() => Boolean(item.value?.reviewed && item.value?.can_follow))
/** 1–5 星對應評級描述（與電商常見綜合評分文案一致） */
const starLabel = computed(() => {
  const n = Math.min(5, Math.max(1, Math.round(stars.value) || 5))
  return t(`review.starLabel${n}`)
})

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

async function afterRead(entryOrList: UploaderFileListItem | UploaderFileListItem[]) {
  const list = Array.isArray(entryOrList) ? entryOrList : [entryOrList]
  for (const entry of list) {
    const file = entry.file
    if (!file) continue
    entry.status = 'uploading'
    try {
      const prepared = await prepareH5UploadImage(file)
      const data = await uploadReviewImage(prepared)
      entry.url = assetUrl(data.url)
      entry.status = 'done'
      ;(entry as UploaderFileListItem & { remoteUrl?: string }).remoteUrl = data.url
    } catch (error) {
      entry.status = 'failed'
      showToast(
        error instanceof H5ImageTooLargeError
          ? t('review.imageSize')
          : error instanceof Error
            ? error.message
            : t('review.imageFail'),
      )
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
        <div class="rate-row">
          <span class="rate-lab">{{ t('review.overall') }}</span>
          <div class="rate-stars" role="radiogroup" :aria-label="t('review.overall')">
            <button
              v-for="n in 5"
              :key="n"
              type="button"
              class="rate-star"
              :class="{ on: n <= stars }"
              role="radio"
              :aria-checked="n === stars"
              @click="stars = n"
            >
              ★
            </button>
          </div>
          <span class="rate-desc">{{ starLabel }}</span>
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
        <van-uploader
          v-model="fileList"
          :max-count="MAX_REVIEW_IMAGES"
          :max-size="H5_IMAGE_MAX_UPLOAD_BYTES"
          accept="image/jpeg,image/png,image/webp"
          :after-read="afterRead"
          @oversize="showToast(t('review.imageSize'))"
        />
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
  grid-template-columns: 4rem minmax(0, 1fr);
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.line > div {
  min-width: 0;
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
  line-height: 1.35;
  white-space: normal;
  overflow-wrap: anywhere;
  word-break: break-word;
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
.rate-row {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  margin: 0.25rem 0 0.875rem;
  padding: 0.65rem 0;
  border-bottom: 0.0625rem solid #f0f0f0;
}
.rate-lab {
  flex-shrink: 0;
  font-size: 0.875rem;
  color: #666;
}
.rate-stars {
  display: flex;
  align-items: center;
  gap: 0.2rem;
  flex: 1;
  min-width: 0;
}
.rate-star {
  border: 0;
  background: transparent;
  padding: 0;
  margin: 0;
  font-size: 1.35rem;
  line-height: 1;
  color: #c8c9cc;
  cursor: pointer;
}
.rate-star.on {
  color: #f5a623;
}
.rate-desc {
  flex-shrink: 0;
  min-width: 3.5rem;
  font-size: 0.875rem;
  color: #666;
}
</style>
