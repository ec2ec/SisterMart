/** 香港地址：大區 → 十八區 → 社區地點（彈框兩步選；落庫中文 region/district/area） */
export type HkLocale = 'zh-HK' | 'en'

export type HkAreaOption = {
  area: string
  areaEn: string
}

export type HkDistrictOption = {
  no: number
  region: '香港島' | '九龍' | '新界'
  district: string
  districtEn: string
  areas: HkAreaOption[]
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
      {
        no: 1,
        region: '香港島',
        district: '中西區',
        districtEn: 'Central and Western',
        areas: [
          { area: '中環', areaEn: 'Central' },
          { area: '上環', areaEn: 'Sheung Wan' },
          { area: '西環', areaEn: 'Sai Wan' },
          { area: '堅尼地城', areaEn: 'Kennedy Town' },
          { area: '半山', areaEn: 'Mid-Levels' },
        ],
      },
      {
        no: 2,
        region: '香港島',
        district: '灣仔區',
        districtEn: 'Wan Chai',
        areas: [
          { area: '灣仔', areaEn: 'Wan Chai' },
          { area: '銅鑼灣', areaEn: 'Causeway Bay' },
          { area: '跑馬地', areaEn: 'Happy Valley' },
          { area: '大坑', areaEn: 'Tai Hang' },
        ],
      },
      {
        no: 3,
        region: '香港島',
        district: '東區',
        districtEn: 'Eastern',
        areas: [
          { area: '北角', areaEn: 'North Point' },
          { area: '鰂魚涌', areaEn: 'Quarry Bay' },
          { area: '西灣河', areaEn: 'Sai Wan Ho' },
          { area: '柴灣', areaEn: 'Chai Wan' },
          { area: '小西灣', areaEn: 'Siu Sai Wan' },
          { area: '筲箕灣', areaEn: 'Shau Kei Wan' },
        ],
      },
      {
        no: 4,
        region: '香港島',
        district: '南區',
        districtEn: 'Southern',
        areas: [
          { area: '香港仔', areaEn: 'Aberdeen' },
          { area: '黃竹坑', areaEn: 'Wong Chuk Hang' },
          { area: '鴨脷洲', areaEn: 'Ap Lei Chau' },
          { area: '淺水灣', areaEn: 'Repulse Bay' },
          { area: '赤柱', areaEn: 'Stanley' },
        ],
      },
    ],
  },
  {
    region: '九龍',
    regionEn: 'Kowloon',
    items: [
      {
        no: 5,
        region: '九龍',
        district: '油尖旺區',
        districtEn: 'Yau Tsim Mong',
        areas: [
          { area: '尖沙咀', areaEn: 'Tsim Sha Tsui' },
          { area: '佐敦', areaEn: 'Jordan' },
          { area: '油麻地', areaEn: 'Yau Ma Tei' },
          { area: '旺角', areaEn: 'Mong Kok' },
          { area: '大角咀', areaEn: 'Tai Kok Tsui' },
        ],
      },
      {
        no: 6,
        region: '九龍',
        district: '深水埗區',
        districtEn: 'Sham Shui Po',
        areas: [
          { area: '深水埗', areaEn: 'Sham Shui Po' },
          { area: '長沙灣', areaEn: 'Cheung Sha Wan' },
          { area: '荔枝角', areaEn: 'Lai Chi Kok' },
          { area: '石硤尾', areaEn: 'Shek Kip Mei' },
        ],
      },
      {
        no: 7,
        region: '九龍',
        district: '九龍城區',
        districtEn: 'Kowloon City',
        areas: [
          { area: '九龍城', areaEn: 'Kowloon City' },
          { area: '土瓜灣', areaEn: 'To Kwa Wan' },
          { area: '紅磡', areaEn: 'Hung Hom' },
          { area: '何文田', areaEn: 'Ho Man Tin' },
          { area: '啟德', areaEn: 'Kai Tak' },
        ],
      },
      {
        no: 8,
        region: '九龍',
        district: '黃大仙區',
        districtEn: 'Wong Tai Sin',
        areas: [
          { area: '黃大仙', areaEn: 'Wong Tai Sin' },
          { area: '鑽石山', areaEn: 'Diamond Hill' },
          { area: '慈雲山', areaEn: 'Tsz Wan Shan' },
          { area: '彩虹', areaEn: 'Choi Hung' },
        ],
      },
      {
        no: 9,
        region: '九龍',
        district: '觀塘區',
        districtEn: 'Kwun Tong',
        areas: [
          { area: '觀塘', areaEn: 'Kwun Tong' },
          { area: '藍田', areaEn: 'Lam Tin' },
          { area: '牛頭角', areaEn: 'Ngau Tau Kok' },
          { area: '秀茂坪', areaEn: 'Sau Mau Ping' },
          { area: '油塘', areaEn: 'Yau Tong' },
        ],
      },
    ],
  },
  {
    region: '新界',
    regionEn: 'New Territories',
    items: [
      {
        no: 10,
        region: '新界',
        district: '荃灣區',
        districtEn: 'Tsuen Wan',
        areas: [
          { area: '荃灣', areaEn: 'Tsuen Wan' },
          { area: '汀九', areaEn: 'Ting Kau' },
          { area: '深井', areaEn: 'Sham Tseng' },
        ],
      },
      {
        no: 11,
        region: '新界',
        district: '屯門區',
        districtEn: 'Tuen Mun',
        areas: [
          { area: '屯門', areaEn: 'Tuen Mun' },
          { area: '藍地', areaEn: 'Lam Tei' },
          { area: '掃管笏', areaEn: 'So Kwun Wat' },
        ],
      },
      {
        no: 12,
        region: '新界',
        district: '元朗區',
        districtEn: 'Yuen Long',
        areas: [
          { area: '元朗', areaEn: 'Yuen Long' },
          { area: '天水圍', areaEn: 'Tin Shui Wai' },
          { area: '錦田', areaEn: 'Kam Tin' },
          { area: '屏山', areaEn: 'Ping Shan' },
        ],
      },
      {
        no: 13,
        region: '新界',
        district: '北區',
        districtEn: 'North',
        areas: [
          { area: '上水', areaEn: 'Sheung Shui' },
          { area: '粉嶺', areaEn: 'Fanling' },
          { area: '沙頭角', areaEn: 'Sha Tau Kok' },
          { area: '打鼓嶺', areaEn: 'Ta Ku Ling' },
        ],
      },
      {
        no: 14,
        region: '新界',
        district: '大埔區',
        districtEn: 'Tai Po',
        areas: [
          { area: '大埔', areaEn: 'Tai Po' },
          { area: '太和', areaEn: 'Tai Wo' },
          { area: '船灣', areaEn: 'Plover Cove' },
        ],
      },
      {
        no: 15,
        region: '新界',
        district: '沙田區',
        districtEn: 'Sha Tin',
        areas: [
          { area: '沙田', areaEn: 'Sha Tin' },
          { area: '馬鞍山', areaEn: 'Ma On Shan' },
          { area: '大圍', areaEn: 'Tai Wai' },
          { area: '火炭', areaEn: 'Fo Tan' },
        ],
      },
      {
        no: 16,
        region: '新界',
        district: '西貢區',
        districtEn: 'Sai Kung',
        areas: [
          { area: '西貢', areaEn: 'Sai Kung' },
          { area: '將軍澳', areaEn: 'Tseung Kwan O' },
          { area: '坑口', areaEn: 'Hang Hau' },
          { area: '清水灣', areaEn: 'Clear Water Bay' },
        ],
      },
      {
        no: 17,
        region: '新界',
        district: '葵青區',
        districtEn: 'Kwai Tsing',
        areas: [
          { area: '葵涌', areaEn: 'Kwai Chung' },
          { area: '青衣', areaEn: 'Tsing Yi' },
          { area: '荔景', areaEn: 'Lai King' },
        ],
      },
      {
        no: 18,
        region: '新界',
        district: '離島區',
        districtEn: 'Islands',
        areas: [
          { area: '大嶼山', areaEn: 'Lantau' },
          { area: '長洲', areaEn: 'Cheung Chau' },
          { area: '梅窩', areaEn: 'Mui Wo' },
          { area: '坪洲', areaEn: 'Peng Chau' },
          { area: '南丫島', areaEn: 'Lamma Island' },
          { area: '東涌', areaEn: 'Tung Chung' },
        ],
      },
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

export function findHkArea(district: string, area: string): HkAreaOption | undefined {
  const d = findHkDistrict(district)
  return d?.areas.find((a) => a.area === area)
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

export function formatHkAreaLabel(
  area: string,
  district?: string,
  locale: HkLocale = 'zh-HK',
): string {
  if (!area) return ''
  if (district) {
    const hit = findHkArea(district, area)
    if (hit) return locale === 'en' ? hit.areaEn : hit.area
  }
  return area
}

export function formatHkAddressLine(
  region: string,
  district: string,
  detail: string,
  locale: HkLocale = 'zh-HK',
  area = '',
): string {
  const districtLabel = formatHkDistrictLabel(district, region, locale)
  const areaLabel = formatHkAreaLabel(area, district, locale)
  return [districtLabel, areaLabel, detail].filter(Boolean).join(' ')
}
