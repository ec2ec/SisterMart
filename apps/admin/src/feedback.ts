import { createDiscreteApi, dateZhTW, zhTW } from 'naive-ui'
import type { DialogOptions } from 'naive-ui'

const { dialog, message } = createDiscreteApi(['dialog', 'message'], {
  configProviderProps: {
    locale: zhTW,
    dateLocale: dateZhTW,
  },
})

let lastErrorKey = ''
let lastErrorAt = 0

function errorText(err: unknown, fallback: string) {
  if (typeof err === 'string' && err.trim()) return err
  if (err instanceof Error && err.message) return err.message
  return fallback
}

/** 錯誤用彈窗（不可點遮罩／Esc 關閉），避免 toast 一閃被忽略 */
export function showError(err: unknown, fallback = '操作失敗', title = '操作失敗') {
  const content = errorText(err, fallback)
  const code =
    err && typeof err === 'object' && 'businessCode' in err
      ? String((err as { businessCode?: number }).businessCode ?? '')
      : ''
  const key = `${code}:${content}`
  const now = Date.now()
  // 同一錯誤短時間內只彈一次（api throw + catch 雙重觸發）
  if (key === lastErrorKey && now - lastErrorAt < 2000) return
  lastErrorKey = key
  lastErrorAt = now
  dialog.error({
    title,
    content,
    positiveText: '關閉',
    maskClosable: false,
    closeOnEsc: false,
  })
}

export function showWarningDialog(options: DialogOptions) {
  return dialog.warning({
    maskClosable: false,
    closeOnEsc: false,
    positiveText: options.positiveText ?? '確定',
    negativeText: options.negativeText ?? '取消',
    ...options,
  })
}

export function showErrorDialog(options: DialogOptions) {
  return dialog.error({
    maskClosable: false,
    closeOnEsc: false,
    positiveText: options.positiveText ?? '關閉',
    ...options,
  })
}

/** 成功／提示仍可用輕量 message；錯誤請用 showError */
export { message, dialog }
