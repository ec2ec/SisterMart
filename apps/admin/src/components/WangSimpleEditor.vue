<script setup lang="ts">
/**
 * 專案富文本標準組件（簡潔模式）。
 * 用戶說「改成富文本」時複用本組件 + uploadImage(preset)，見 .cursor/rules/rich-text-editor.mdc
 */
import { shallowRef, onBeforeUnmount, ref, watch, computed } from 'vue'
import { NButton } from 'naive-ui'
import { Editor, Toolbar } from '@wangeditor/editor-for-vue'
import type { IDomEditor, IEditorConfig, IToolbarConfig } from '@wangeditor/editor'
import '@wangeditor/editor/dist/css/style.css'
import { uploadImage } from '../api'
import { assetUrl, toPreviewHtml, toStoredHtml } from '../utils/assetUrl'
import MediaPickerDialog from './MediaPickerDialog.vue'

const props = withDefaults(
  defineProps<{
    modelValue: string
    placeholder?: string
    /** 圖片場景，對應設置裡的上傳配置 */
    scene?: string
    height?: number
  }>(),
  {
    placeholder: '輸入內容…',
    scene: 'content.richtext',
    height: 220,
  },
)

const emit = defineEmits<{ 'update:modelValue': [string] }>()

const editorRef = shallowRef<IDomEditor>()
const html = shallowRef('')
const libraryOpen = ref(false)
let syncing = false

const toolbarConfig: Partial<IToolbarConfig> = {
  // 簡潔模式常用項 + 字號／對齊
  toolbarKeys: [
    'bold',
    'italic',
    'underline',
    'through',
    '|',
    'fontSize',
    '|',
    'bulletedList',
    'numberedList',
    '|',
    'justifyLeft',
    'justifyCenter',
    'justifyRight',
    'justifyJustify',
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
    fontSize: {
      fontSizeList: [
        '12px',
        '13px',
        '14px',
        '15px',
        '16px',
        '18px',
        '20px',
        '24px',
        '28px',
        '32px',
        '36px',
        '48px',
      ],
    },
    uploadImage: {
      allowedFileTypes: ['image/*'],
      maxFileSize: 5 * 1024 * 1024,
      async customUpload(file: File, insertFn: (url: string, alt: string, href: string) => void) {
        const data = await uploadImage(file, { scene: props.scene })
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

function insertFromLibrary(url: string) {
  const editor = editorRef.value
  if (!editor) return
  const src = assetUrl(url).replace(/"/g, '')
  editor.focus()
  editor.dangerouslyInsertHtml(`<p><img src="${src}" alt="" /></p>`)
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
    <div class="we-lib">
      <n-button text size="tiny" @click="libraryOpen = true">從素材庫插入</n-button>
    </div>
    <MediaPickerDialog v-model:show="libraryOpen" :scene="scene" @select="insertFromLibrary" />
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
.we-lib {
  border-top: 1px solid #eee;
  padding: 0.15rem 0.5rem 0.25rem;
  text-align: right;
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
