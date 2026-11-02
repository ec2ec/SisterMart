/** 客戶端僅擋超大檔；實際上限與壓縮以服務端場景配置為準 */
export const H5_IMAGE_MAX_UPLOAD_BYTES = 20 * 1024 * 1024
export const H5_IMAGE_MAX_EDGE = 1000
export const H5_IMAGE_MAX_STORE_BYTES = 500 * 1024

export class H5ImageTooLargeError extends Error {
  constructor() {
    super('H5_IMAGE_TOO_LARGE')
    this.name = 'H5ImageTooLargeError'
  }
}

/** 只校驗選圖體積。壓縮、最長邊、是否 keep 原格式都走服務端場景配置。 */
export async function prepareH5UploadImage(file: File): Promise<File> {
  if (file.size > H5_IMAGE_MAX_UPLOAD_BYTES) {
    throw new H5ImageTooLargeError()
  }
  return file
}
