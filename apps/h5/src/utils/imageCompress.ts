/** 客戶端壓縮：縮放到 maxEdge 內並輸出 JPEG；可選壓到 maxBytes 以內。 */
export async function compressImageFile(
  file: File | Blob,
  opts: {
    maxEdge?: number
    quality?: number
    minQuality?: number
    maxBytes?: number
    mime?: string
  } = {},
): Promise<Blob> {
  const maxEdge = opts.maxEdge ?? 1000
  const maxBytes = opts.maxBytes ?? 0
  const minQuality = opts.minQuality ?? 0.4
  let quality = opts.quality ?? 0.85
  const mime = opts.mime ?? 'image/jpeg'
  const bitmap = await createImageBitmap(file)
  let edge = maxEdge
  const long0 = Math.max(bitmap.width, bitmap.height)
  let scale = Math.min(1, edge / long0)
  let w = Math.max(1, Math.round(bitmap.width * scale))
  let h = Math.max(1, Math.round(bitmap.height * scale))

  const canvas = document.createElement('canvas')
  const ctx = canvas.getContext('2d')
  if (!ctx) {
    bitmap.close()
    throw new Error('canvas')
  }

  const draw = () => {
    canvas.width = w
    canvas.height = h
    ctx.fillStyle = '#fff'
    ctx.fillRect(0, 0, w, h)
    ctx.drawImage(bitmap, 0, 0, w, h)
  }

  const toBlob = (q: number) =>
    new Promise<Blob>((resolve, reject) => {
      canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('toBlob'))), mime, q)
    })

  draw()
  let blob = await toBlob(quality)

  if (maxBytes > 0) {
    while (blob.size > maxBytes && quality > minQuality + 0.01) {
      quality = Math.max(minQuality, quality - 0.08)
      blob = await toBlob(quality)
    }
    // 仍超體積：縮小最長邊再壓
    while (blob.size > maxBytes && edge > 600) {
      edge = Math.max(600, Math.floor(edge * 0.75))
      scale = Math.min(1, edge / long0)
      w = Math.max(1, Math.round(bitmap.width * scale))
      h = Math.max(1, Math.round(bitmap.height * scale))
      quality = opts.quality ?? 0.85
      draw()
      blob = await toBlob(quality)
      while (blob.size > maxBytes && quality > minQuality + 0.01) {
        quality = Math.max(minQuality, quality - 0.08)
        blob = await toBlob(quality)
      }
    }
  }

  bitmap.close()
  return blob
}

/** 從原圖按覆蓋方式裁成正方形（中心），再縮放到 size。 */
export async function cropSquareCover(
  file: File | Blob,
  opts: { size?: number; quality?: number; scale?: number; offsetX?: number; offsetY?: number } = {},
): Promise<Blob> {
  const size = opts.size ?? 512
  const quality = opts.quality ?? 0.85
  const scale = Math.max(1, opts.scale ?? 1)
  const bitmap = await createImageBitmap(file)
  const short = Math.min(bitmap.width, bitmap.height)
  const crop = short / scale
  const maxOx = (bitmap.width - crop) / 2
  const maxOy = (bitmap.height - crop) / 2
  const ox = Math.max(-maxOx, Math.min(maxOx, (opts.offsetX ?? 0) * maxOx))
  const oy = Math.max(-maxOy, Math.min(maxOy, (opts.offsetY ?? 0) * maxOy))
  const sx = (bitmap.width - crop) / 2 + ox
  const sy = (bitmap.height - crop) / 2 + oy
  const canvas = document.createElement('canvas')
  canvas.width = size
  canvas.height = size
  const ctx = canvas.getContext('2d')
  if (!ctx) {
    bitmap.close()
    throw new Error('canvas')
  }
  ctx.drawImage(bitmap, sx, sy, crop, crop, 0, 0, size, size)
  bitmap.close()
  return new Promise<Blob>((resolve, reject) => {
    canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('toBlob'))), 'image/jpeg', quality)
  })
}
