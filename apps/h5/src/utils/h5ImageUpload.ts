import { compressImageFile } from './imageCompress'

/** H5 會員上傳統一：選圖 ≤5MB；落庫目標 ≤500KB、最長邊 ≤1000（保持比例） */
export const H5_IMAGE_MAX_UPLOAD_BYTES = 5 * 1024 * 1024
export const H5_IMAGE_MAX_EDGE = 1000
export const H5_IMAGE_MAX_STORE_BYTES = 500 * 1024

export class H5ImageTooLargeError extends Error {
  constructor() {
    super('H5_IMAGE_TOO_LARGE')
    this.name = 'H5ImageTooLargeError'
  }
}

/** 校驗選圖體積後，客戶端預壓縮（服務端落庫會再壓一次）。 */
export async function prepareH5UploadImage(file: File): Promise<File> {
  if (!file.type.startsWith('image/') && file.type !== '') {
    // 部分 Android 可能空 type，仍交給後續壓縮／服務端校驗
  }
  if (file.size > H5_IMAGE_MAX_UPLOAD_BYTES) {
    throw new H5ImageTooLargeError()
  }
  const blob = await compressImageFile(file, {
    maxEdge: H5_IMAGE_MAX_EDGE,
    maxBytes: H5_IMAGE_MAX_STORE_BYTES,
    quality: 0.85,
  })
  const base = (file.name || 'image').replace(/\.[^.]+$/, '') || 'image'
  return new File([blob], `${base}.jpg`, { type: 'image/jpeg' })
}
