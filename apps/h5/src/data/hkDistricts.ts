/** 香港十八區（單選彈框用；落庫仍用中文 region + district） */
export type HkLocale = 'zh-HK' | 'en'

export type HkDistrictOption = {
  no: number
  region: '香港島' | '九龍' | '新界'
  district: string
  districtEn: string
}

export type HkRegionGroup = {
  region: '香港島' | '九龍' | '新界'
  regionEn: string
  items: HkDistrictOption[]
}

export const HK_DISTRICT_GROUPS: HkRegionGroup[] = [
  {
    region: '香港島',
    regionEn: 'Hong Kong Island',
    items: [
      { no: 1, region: '香港島', district: '中西區', districtEn: 'Central and Western District' },
      { no: 2, region: '香港島', district: '灣仔區', districtEn: 'Wan Chai District' },
      { no: 3, region: '香港島', district: '東區', districtEn: 'Eastern District' },
      { no: 4, region: '香港島', district: '南區', districtEn: 'Southern District' },
    ],
  },
  {
    region: '九龍',
    regionEn: 'Kowloon',
    items: [
      { no: 5, region: '九龍', district: '油尖旺區', districtEn: 'Yau Tsim Mong District' },
      { no: 6, region: '九龍', district: '深水埗區', districtEn: 'Sham Shui Po District' },
      { no: 7, region: '九龍', district: '九龍城區', districtEn: 'Kowloon City District' },
      { no: 8, region: '九龍', district: '黃大仙區', districtEn: 'Wong Tai Sin District' },
      { no: 9, region: '九龍', district: '觀塘區', districtEn: 'Kwun Tong District' },
    ],
  },
  {
    region: '新界',
    regionEn: 'New Territories',
    items: [
      { no: 10, region: '新界', district: '荃灣區', districtEn: 'Tsuen Wan District' },
      { no: 11, region: '新界', district: '屯門區', districtEn: 'Tuen Mun District' },
      { no: 12, region: '新界', district: '元朗區', districtEn: 'Yuen Long District' },
      { no: 13, region: '新界', district: '北區', districtEn: 'North District' },
      { no: 14, region: '新界', district: '大埔區', districtEn: 'Tai Po District' },
      { no: 15, region: '新界', district: '沙田區', districtEn: 'Sha Tin District' },
      { no: 16, region: '新界', district: '西貢區', districtEn: 'Sai Kung District' },
      { no: 17, region: '新界', district: '葵青區', districtEn: 'Kwai Tsing District' },
      { no: 18, region: '新界', district: '離島區', districtEn: 'Islands District' },
    ],
  },
]

export function findHkDistrict(district: string): HkDistrictOption | undefined {
  for (const g of HK_DISTRICT_GROUPS) {
    const hit = g.items.find((i) => i.district === district)
    if (hit) return hit
  }
  return undefined
}

export function formatHkRegionLabel(region: string, locale: HkLocale = 'zh-HK'): string {
  const g = HK_DISTRICT_GROUPS.find((x) => x.region === region)
  if (!g) return region
  return locale === 'en' ? g.regionEn : g.region
}

/** 依介面語言只顯示中文或英文，不混排 */
export function formatHkDistrictLabel(
  district: string,
  region?: string,
  locale: HkLocale = 'zh-HK',
): string {
  const hit = findHkDistrict(district)
  if (hit) return locale === 'en' ? hit.districtEn : hit.district
  if (region && district) return `${formatHkRegionLabel(region, locale)} ${district}`
  return district || ''
}

export function formatHkAddressLine(
  region: string,
  district: string,
  detail: string,
  locale: HkLocale = 'zh-HK',
): string {
  const area = formatHkDistrictLabel(district, region, locale)
  return [area, detail].filter(Boolean).join(' ')
}
