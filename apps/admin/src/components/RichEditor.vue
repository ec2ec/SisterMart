<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { NButton, NSpace } from 'naive-ui'

const props = defineProps<{ modelValue: string; placeholder?: string }>()
const emit = defineEmits<{ 'update:modelValue': [string] }>()

const root = ref<HTMLDivElement | null>(null)

onMounted(() => {
  if (root.value && props.modelValue) {
    root.value.innerHTML = props.modelValue
  }
})

watch(
  () => props.modelValue,
  (v) => {
    if (!root.value) return
    if (root.value.innerHTML !== v) {
      root.value.innerHTML = v || ''
    }
  },
)

function sync() {
  emit('update:modelValue', root.value?.innerHTML || '')
}

function cmd(command: string, value?: string) {
  root.value?.focus()
  document.execCommand(command, false, value)
  sync()
}

function link() {
  const url = window.prompt('連結 URL', 'https://')
  if (!url) return
  cmd('createLink', url)
}
</script>

<template>
  <div class="re">
    <n-space size="small" style="margin-bottom: 0.35rem">
      <n-button size="tiny" @click="cmd('bold')"><b>B</b></n-button>
      <n-button size="tiny" @click="cmd('italic')"><i>I</i></n-button>
      <n-button size="tiny" @click="cmd('underline')"><u>U</u></n-button>
      <n-button size="tiny" @click="cmd('insertUnorderedList')">• 列表</n-button>
      <n-button size="tiny" @click="cmd('insertOrderedList')">1. 列表</n-button>
      <n-button size="tiny" @click="link">連結</n-button>
      <n-button size="tiny" @click="cmd('removeFormat')">清除格式</n-button>
    </n-space>
    <div
      ref="root"
      class="re-body"
      contenteditable="true"
      :data-placeholder="placeholder || '輸入內容…'"
      @input="sync"
      @blur="sync"
    />
  </div>
</template>

<style scoped>
.re {
  width: 100%;
  border: 1px solid #e0e0e6;
  border-radius: 0.35rem;
  padding: 0.5rem;
  background: #fff;
}
.re-body {
  min-height: 7rem;
  outline: none;
  font-size: 0.875rem;
  line-height: 1.6;
}
.re-body:empty:before {
  content: attr(data-placeholder);
  color: #c2c2c2;
}
.re-body :deep(a) {
  color: #2080f0;
}
</style>
