<script setup lang="ts">
import { computed } from 'vue'
import { NInput } from 'naive-ui'
import ProductMediaGallery, { type MediaItem } from './ProductMediaGallery.vue'

const props = withDefaults(
  defineProps<{
    modelValue: string
    scene: string
    placeholder?: string
    deleteTitle?: string
    deleteContent?: string
  }>(),
  {
    placeholder: '上傳後自動填入，或貼上已有圖片地址',
    deleteTitle: '移除圖片',
    deleteContent: '確定移除此圖片？保存後才會生效。',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const media = computed<MediaItem[]>(() => {
  const url = props.modelValue.trim()
  return url ? [{ media_type: 'image', url }] : []
})

function onMedia(next: MediaItem[]) {
  emit('update:modelValue', next[0]?.url || '')
}
</script>

<template>
  <div class="field">
    <ProductMediaGallery
      :model-value="media"
      :scene="scene"
      :max="1"
      library
      :delete-title="deleteTitle"
      :delete-content="deleteContent"
      hint=""
      @update:model-value="onMedia"
    />
    <n-input
      :value="modelValue"
      :placeholder="placeholder"
      @update:value="(v: string) => emit('update:modelValue', v)"
    />
  </div>
</template>

<style scoped>
.field {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
</style>
