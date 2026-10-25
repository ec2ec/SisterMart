import { showImagePreview } from 'vant'
import { assetUrl } from './assetUrl'

/** 用 Vant ImagePreview 全屏預覽；多圖可左右滑動。 */
export function previewImages(urls: string[], startIndex = 0) {
  const images = urls.map((u) => assetUrl(u)).filter(Boolean)
  if (!images.length) return
  const startPosition = Math.min(Math.max(0, startIndex), images.length - 1)
  showImagePreview({
    images,
    startPosition,
    closeable: true,
  })
}
