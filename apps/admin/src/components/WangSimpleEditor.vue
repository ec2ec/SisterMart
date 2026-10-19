<script setup lang="ts">
import { shallowRef, onBeforeUnmount, watch, computed } from 'vue'
import { Editor, Toolbar } from '@wangeditor/editor-for-vue'
import type { IDomEditor, IEditorConfig, IToolbarConfig } from '@wangeditor/editor'
import '@wangeditor/editor/dist/css/style.css'
import { uploadImage } from '../api'
import { assetUrl, toPreviewHtml, toStoredHtml } from '../utils/assetUrl'

const props = withDefaults(
  defineProps<{
    modelValue: string
    placeholder?: string
    preset?: 'product' | 'admin' | 'banner'
    height?: number
  }>(),
  {
    placeholder: '輸入內容…',
    preset: 'product',
    height: 220,
  },
)

const emit = defineEmits<{ 'update:modelValue': [string] }>()

const editorRef = shallowRef<IDomEditor>()
const html = shallowRef('')
let syncing = false

const toolbarConfig: Partial<IToolbarConfig> = {
  // 簡潔模式再收斂常用項
  toolbarKeys: [
    'bold',
    'italic',
    'underline',
    'through',
    '|',
    'bulletedList',
    'numberedList',
    '|',
    'color',
    'bgColor',
    '|',
    'insertLink',
    'uploadImage',
    '|',
    'undo',
    'redo',
  ],
}

const editorConfig = computed<Partial<IEditorConfig>>(() => ({
  placeholder: props.placeholder,
  MENU_CONF: {
    uploadImage: {
      allowedFileTypes: ['image/*'],
      maxFileSize: 5 * 1024 * 1024,
      async customUpload(file: File, insertFn: (url: string, alt: string, href: string) => void) {
        const data = await uploadImage(file, { preset: props.preset })
        const preview = assetUrl(data.url)
        // 編輯器內用可訪問 URL；同步時再壓成相對路徑
        insertFn(preview, file.name || '', preview)
      },
    },
  },
}))

const editorStyle = computed(() => `height: ${props.height}px; overflow-y: hidden;`)

watch(
  () => props.modelValue,
  (v) => {
    const next = toPreviewHtml(v || '')
    if (syncing) return
    if (html.value !== next) html.value = next
  },
  { immediate: true },
)

function handleCreated(editor: IDomEditor) {
  editorRef.value = editor
}

function onChange(editor: IDomEditor) {
  const raw = editor.getHtml()
  // wangEditor 空內容常為 <p><br></p>
  const empty = raw === '<p><br></p>' || raw === '<p></p>'
  const stored = empty ? '' : toStoredHtml(raw)
  syncing = true
  html.value = raw
  emit('update:modelValue', stored)
  queueMicrotask(() => {
    syncing = false
  })
}

onBeforeUnmount(() => {
  const editor = editorRef.value
  if (editor) editor.destroy()
})
</script>

<template>
  <div class="we">
    <Toolbar :editor="editorRef" :default-config="toolbarConfig" mode="simple" class="we-toolbar" />
    <Editor
      :model-value="html"
      :default-config="editorConfig"
      :style="editorStyle"
      mode="simple"
      class="we-body"
      @on-created="handleCreated"
      @on-change="onChange"
    />
  </div>
</template>

<style scoped>
.we {
  width: 100%;
  border: 1px solid #e0e0e6;
  border-radius: 0.35rem;
  overflow: hidden;
  background: #fff;
}
.we-toolbar {
  border-bottom: 1px solid #eee;
}
.we-body {
  background: #fff;
}
.we :deep(.w-e-text-container) {
  background: #fff !important;
}
.we :deep(img) {
  max-width: 100%;
  height: auto;
}
</style>
