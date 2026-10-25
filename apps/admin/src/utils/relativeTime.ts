/** 後台相對時間：1分鐘前 / 2小時前 / 3天前；空值由調用方顯示 - */
export function formatRelativeZh(isoOrSql: string | null | undefined, nowMs = Date.now()): string {
  if (!isoOrSql) return ''
  const raw = isoOrSql.includes('T') ? isoOrSql : isoOrSql.replace(' ', 'T')
  const t = Date.parse(raw)
  if (Number.isNaN(t)) return isoOrSql
  const sec = Math.max(0, Math.floor((nowMs - t) / 1000))
  if (sec < 60) return '剛剛'
  const min = Math.floor(sec / 60)
  if (min < 60) return `${min}分鐘前`
  const hour = Math.floor(min / 60)
  if (hour < 24) return `${hour}小時前`
  const day = Math.floor(hour / 24)
  if (day < 30) return `${day}天前`
  const month = Math.floor(day / 30)
  if (month < 12) return `${month}個月前`
  return `${Math.floor(month / 12)}年前`
}
