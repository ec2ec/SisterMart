declare module '@wangeditor/editor-for-vue' {
  import type { Component } from 'vue'
  import type { IDomEditor, IEditorConfig, IToolbarConfig } from '@wangeditor/editor'

  export const Editor: Component
  export const Toolbar: Component

  export type { IDomEditor, IEditorConfig, IToolbarConfig }
}
