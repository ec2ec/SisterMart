<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast, type UploaderFileListItem } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import {
  applyAftersale,
  fetchOrder,
  uploadAftersaleEvidence,
  type Order,
} from '../api/trade'
import { useLocaleText } from '../composables/useLocaleText'
import { ApiError } from '../api/client'

const { t } = useI18n()
const { text } = useLocaleText()
const route = useRoute()
const router = useRouter()
const order = ref<Order | null>(null)
const itemId = ref<number | null>(null)
const type = ref<'refund_only' | 'return_refund'>('refund_only')
const refundTo = ref<'balance' | 'original'>('balance')
const description = ref('')
const fileList = ref<UploaderFileListItem[]>([])
const submitting = ref(false)
const apiBase = import.meta.env.VITE_API_BASE || 'http://127.0.0.1:8000'

const expired = computed(() => Boolean(order.value?.aftersale_expired))
const whatsappDigits = computed(() => (order.value?.cs_whatsapp || '').replace(/\D/g, ''))

async function load() {
  order.value = null
  description.value = ''
  fileList.value = []
  refundTo.value = 'balance'
  order.value = await fetchOrder(String(route.params.id))
  const first = (order.value.items || []).find((i) => (i.refunded_qty || 0) < i.qty)
  itemId.value = first?.id ?? null
  if (order.value.status === 'pending_shipment') type.value = 'refund_only'
  if (order.value.status === 'completed') type.value = 'return_refund'
}

watch(() => route.params.id, load, { immediate: true })

function assetUrl(u: string) {
  return u.startsWith('http') ? u : `${apiBase}${u}`
}

function openWhatsApp() {
  if (!whatsappDigits.value) {
    showToast(t('fulfill.whatsappMissing'))
    return
  }
  const textMsg = encodeURIComponent(
    t('fulfill.whatsappPrefill', { no: order.value?.order_no || '' }),
  )
  window.open(`https://wa.me/${whatsappDigits.value}?text=${textMsg}`, '_blank', 'noopener')
}

async function afterRead(item: UploaderFileListItem | UploaderFileListItem[]) {
  const list = Array.isArray(item) ? item : [item]
  for (const entry of list) {
    const file = entry.file
    if (!file) continue
    if (file.size > 1024 * 1024) {
      showToast(t('fulfill.evidenceSize'))
      fileList.value = fileList.value.filter((f) => f !== entry)
      continue
    }
    entry.status = 'uploading'
    try {
      const data = await uploadAftersaleEvidence(file)
      entry.url = assetUrl(data.url)
      entry.status = 'done'
      ;(entry as UploaderFileListItem & { remoteUrl?: string }).remoteUrl = data.url
    } catch (error) {
      entry.status = 'failed'
      showToast(error instanceof Error ? error.message : t('fulfill.evidenceFail'))
      fileList.value = fileList.value.filter((f) => f !== entry)
    }
  }
}

async function submit() {
  if (!order.value || !itemId.value) return
  if (!description.value.trim()) {
    showToast(t('fulfill.descRequired'))
    return
  }
  const evidence = fileList.value
    .map((f) => (f as UploaderFileListItem & { remoteUrl?: string }).remoteUrl || '')
    .filter(Boolean)
  submitting.value = true
  try {
    const as = await applyAftersale({
      order_id: order.value.id,
      order_item_id: itemId.value,
      type: type.value,
      refund_to: refundTo.value,
      description: description.value.trim(),
      evidence,
    })
    showToast(t('fulfill.applied'))
    router.replace({ name: 'aftersale-detail', params: { id: as.id } })
  } catch (error) {
    const msg =
      error instanceof ApiError && error.code === 'AFTERSALE_EXPIRED'
        ? t('fulfill.expired')
        : error instanceof Error
          ? error.message
          : t('checkout.fail')
    showToast(msg)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="page" v-if="order">
    <SubPageNav :title="t('fulfill.apply')" />

    <section v-if="expired" class="card expired">
      <p class="msg">{{ t('fulfill.expired') }}</p>
      <van-button
        block
        type="primary"
        color="var(--sm-primary)"
        round
        :disabled="!whatsappDigits"
        @click="openWhatsApp"
      >
        {{ t('fulfill.contactWhatsapp') }}
      </van-button>
    </section>

    <template v-else>
      <section class="card">
        <p>{{ order.order_no }}</p>
        <div v-for="item in order.items" :key="item.id" class="line">
          <label>
            <input
              v-model="itemId"
              type="radio"
              :value="item.id"
              :disabled="(item.refunded_qty || 0) >= item.qty"
            />
            <img :src="item.image" alt="" />
            <span>{{ text(item.name) }} × {{ item.qty }}</span>
          </label>
        </div>
      </section>

      <section class="card">
        <van-radio-group v-if="order.status === 'pending_receipt'" v-model="type" direction="vertical">
          <van-cell clickable @click="type = 'refund_only'">
            <template #icon><van-radio name="refund_only" /></template>
            <template #title>{{ t('fulfill.refundOnly') }}</template>
          </van-cell>
          <van-cell clickable @click="type = 'return_refund'">
            <template #icon><van-radio name="return_refund" /></template>
            <template #title>{{ t('fulfill.returnRefund') }}</template>
          </van-cell>
        </van-radio-group>

        <p class="label">{{ t('fulfill.refundTo') }}</p>
        <van-radio-group v-model="refundTo" direction="vertical" class="refund">
          <van-cell clickable @click="refundTo = 'balance'">
            <template #icon><van-radio name="balance" checked-color="var(--sm-primary)" /></template>
            <template #title>{{ t('fulfill.toBalance') }}</template>
          </van-cell>
          <van-cell clickable @click="refundTo = 'original'">
            <template #icon><van-radio name="original" checked-color="var(--sm-primary)" /></template>
            <template #title>{{ t('fulfill.toOriginal') }}</template>
          </van-cell>
        </van-radio-group>

        <van-field
          v-model="description"
          rows="4"
          autosize
          type="textarea"
          maxlength="500"
          show-word-limit
          :placeholder="t('fulfill.descPlaceholder')"
        />

        <p class="label">{{ t('fulfill.evidence') }}</p>
        <van-uploader
          v-model="fileList"
          :max-count="3"
          :max-size="1024 * 1024"
          accept="image/jpeg,image/png,image/webp"
          :after-read="afterRead"
          @oversize="showToast(t('fulfill.evidenceSize'))"
        />
        <p class="hint">{{ t('fulfill.evidenceHint') }}</p>

        <van-button
          block
          type="primary"
          color="var(--sm-primary)"
          round
          :loading="submitting"
          style="margin-top: 1rem"
          @click="submit"
        >
          {{ t('fulfill.submit') }}
        </van-button>
      </section>
    </template>
  </div>
</template>

<style scoped>
.card {
  margin: 0.75rem;
  padding: 0.875rem;
  background: #fff;
  border-radius: 0.75rem;
}
.expired .msg {
  margin: 0 0 1.25rem;
  font-size: 0.95rem;
  line-height: 1.5;
  color: #333;
  text-align: center;
}
.line label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0.5rem 0;
  font-size: 0.8125rem;
}
.line img {
  width: 2.5rem;
  height: 2.5rem;
  object-fit: cover;
  border-radius: 0.375rem;
}
.label {
  margin: 0.75rem 0 0.35rem;
  font-size: 0.875rem;
  font-weight: 600;
}
.hint {
  margin: 0.35rem 0 0;
  font-size: 0.75rem;
  color: #999;
}
.refund :deep(.van-cell) {
  padding-left: 0;
}
</style>
