<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { showToast } from 'vant'
import { cropSquareCover } from '../utils/imageCompress'

/** 選圖上限 5MB；落庫由服務端再壓縮 */
const MAX_AVATAR_BYTES = 5 * 1024 * 1024

const props = defineProps<{ show: boolean }>()
const emit = defineEmits<{
  'update:show': [boolean]
  confirm: [Blob]
}>()

const { t } = useI18n()
const file = ref<File | null>(null)
const previewUrl = ref('')
const scale = ref(1)
const busy = ref(false)
const inputRef = ref<HTMLInputElement | null>(null)

watch(
  () => props.show,
  (v) => {
    if (!v) reset()
  },
)

function reset() {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = ''
  file.value = null
  scale.value = 1
  busy.value = false
}

function close() {
  emit('update:show', false)
}

function pick() {
  inputRef.value?.click()
}

function onFile(ev: Event) {
  const input = ev.target as HTMLInputElement
  const f = input.files?.[0]
  input.value = ''
  if (!f) return
  if (!f.type.startsWith('image/')) {
    showToast(t('account.avatarType'))
    return
  }
  if (f.size > MAX_AVATAR_BYTES) {
    showToast(t('account.avatarTooLarge'))
    return
  }
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  file.value = f
  previewUrl.value = URL.createObjectURL(f)
  scale.value = 1
}

async function confirm() {
  if (!file.value) {
    showToast(t('account.avatarPick'))
    return
  }
  busy.value = true
  try {
    const blob = await cropSquareCover(file.value, { size: 512, quality: 0.85, scale: scale.value })
    emit('confirm', blob)
    close()
  } catch {
    showToast(t('account.avatarFail'))
  } finally {
    busy.value = false
  }
}

onBeforeUnmount(reset)
</script>

<template>
  <van-popup :show="show" position="bottom" round @update:show="emit('update:show', $event)">
    <div class="sheet">
      <h3>{{ t('account.avatarTitle') }}</h3>
      <input ref="inputRef" type="file" accept="image/*" hidden @change="onFile" />
      <div class="preview" @click="pick">
        <img v-if="previewUrl" :src="previewUrl" alt="" :style="{ transform: `scale(${scale})` }" />
        <span v-else>{{ t('account.avatarPick') }}</span>
      </div>
      <div v-if="previewUrl" class="zoom">
        <span>{{ t('account.avatarZoom') }}</span>
        <input v-model.number="scale" type="range" min="1" max="2.5" step="0.05" />
      </div>
      <div class="actions">
        <van-button block @click="pick">{{ t('account.avatarPick') }}</van-button>
        <van-button block type="primary" color="var(--sm-primary)" :loading="busy" @click="confirm">
          {{ t('account.avatarSave') }}
        </van-button>
        <van-button block plain @click="close">{{ t('pages.back') }}</van-button>
      </div>
    </div>
  </van-popup>
</template>

<style scoped>
.sheet {
  padding: 1rem 1rem 1.5rem;
}
.sheet h3 {
  margin: 0 0 0.75rem;
  font-size: 1rem;
  text-align: center;
}
.preview {
  width: 12rem;
  height: 12rem;
  margin: 0 auto 0.75rem;
  border-radius: 50%;
  overflow: hidden;
  background: #f0ebe3;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #999;
  font-size: 0.8125rem;
}
.preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.zoom {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  font-size: 0.75rem;
  color: #666;
}
.zoom input {
  flex: 1;
}
.actions {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
</style>
