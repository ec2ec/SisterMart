export type Product = {
  id: string
  name: { 'zh-HK': string; en: string }
  price: number
  sold: string
  image: string
  gallery?: string[]
  category?: string
  badges?: ('flash' | 'hot' | 'new' | 'editor')[]
  liked?: boolean
}

function mockImage(file: string) {
  return `/mock/${file}`
}

export type CategoryNode = {
  id: string
  name: { 'zh-HK': string; en: string }
  image: string
  children?: {
    id: string
    name: { 'zh-HK': string; en: string }
    image: string
  }[]
}

export const banners = [
  { id: 'b1', image: mockImage('home-banner-1.png') },
  { id: 'b2', image: mockImage('home-banner-2.png') },
]

const cushionNecklace: Product = {
  id: 'p1',
  name: { 'zh-HK': '方鑽項鏈', en: 'Cushion Necklace' },
  price: 128,
  sold: '56',
  image: mockImage('product-1.jpg'),
  gallery: [mockImage('product-1.jpg'), mockImage('product-1-detail.jpg'), mockImage('product-1-detail-2.jpg')],
  category: 'necklaces',
  badges: ['flash'],
  liked: true,
}

const gourdNecklace: Product = {
  id: 'p2',
  name: { 'zh-HK': '葫蘆項鏈', en: 'Gourd Necklace' },
  price: 128,
  sold: '88',
  image: mockImage('product-2.jpg'),
  gallery: [
    mockImage('product-2.jpg'),
    mockImage('product-2-detail-1.jpg'),
    mockImage('product-2-detail-2.jpg'),
    mockImage('product-2-detail-3.jpg'),
  ],
  category: 'necklaces',
  badges: ['new', 'editor'],
  liked: true,
}

const cloverBracelet: Product = {
  id: 'p3',
  name: { 'zh-HK': '四葉草手鏈', en: 'Clover Bracelet' },
  price: 49,
  sold: '80+',
  image: mockImage('product-3.jpg'),
  gallery: [
    mockImage('product-3.jpg'),
    mockImage('product-3-detail.jpg'),
    mockImage('product-3-detail-2.jpg'),
    mockImage('product-3-detail-3.jpg'),
  ],
  category: 'bracelets',
  badges: ['hot'],
  liked: true,
}

const heartBracelet: Product = {
  id: 'p4',
  name: { 'zh-HK': '紅心手鏈', en: 'Heart Bracelet' },
  price: 69,
  sold: '40+',
  image: mockImage('product-4.jpg'),
  gallery: [
    mockImage('product-4.jpg'),
    mockImage('product-4-detail-1.jpg'),
    mockImage('product-4-detail-2.jpg'),
    mockImage('product-4-detail-3.jpg'),
  ],
  category: 'bracelets',
  badges: ['flash'],
  liked: true,
}

export const flashProducts: Product[] = [heartBracelet, cloverBracelet, cushionNecklace, gourdNecklace]

export const homeCategories = [
  { id: 'necklaces', name: { 'zh-HK': '項鏈', en: 'Necklaces' }, image: mockImage('cat-necklace.jpg') },
  { id: 'bracelets', name: { 'zh-HK': '手鏈', en: 'Bracelets' }, image: mockImage('cat-bracelet.jpg') },
  { id: 'earrings', name: { 'zh-HK': '耳環', en: 'Earrings' }, image: mockImage('cat-earrings.jpg') },
  { id: 'rings', name: { 'zh-HK': '戒指', en: 'Rings' }, image: mockImage('cat-ring.jpg') },
  { id: 'scarves', name: { 'zh-HK': '頭巾', en: 'Scarves' }, image: mockImage('cat-headscarf.jpg') },
  { id: 'bags', name: { 'zh-HK': '包袋', en: 'Bags' }, image: mockImage('cat-bag.jpg') },
]

export const collectionProducts: Product[] = [cushionNecklace, gourdNecklace]

export const feedProducts: Product[] = [cloverBracelet, heartBracelet]

export const categories: CategoryNode[] = [
  {
    id: 'bracelets',
    name: { 'zh-HK': '手鏈', en: 'Bracelets' },
    image: mockImage('cat-bracelet.jpg'),
    children: [
      { id: 'b-all', name: { 'zh-HK': '全部商品', en: 'All' }, image: cloverBracelet.image },
      { id: 'b-heart', name: { 'zh-HK': '愛心手鏈', en: 'Heart' }, image: heartBracelet.image },
      { id: 'b-gold', name: { 'zh-HK': '金珠手鏈', en: 'Gold Link' }, image: '/products/sub-gold-bead.jpg' },
    ],
  },
  {
    id: 'necklaces',
    name: { 'zh-HK': '項鏈', en: 'Necklaces' },
    image: mockImage('cat-necklace.jpg'),
    children: [
      { id: 'n-all', name: { 'zh-HK': '全部商品', en: 'All' }, image: cushionNecklace.image },
      { id: 'n-gourd', name: { 'zh-HK': '葫蘆項鏈', en: 'Gourd' }, image: gourdNecklace.image },
      { id: 'n-square', name: { 'zh-HK': '方鑽項鏈', en: 'Cushion' }, image: cushionNecklace.image },
      { id: 'n-pearl', name: { 'zh-HK': '珍珠項鏈', en: 'Heart' }, image: '/products/sub-pearl.jpg' },
      { id: 'n-clavicle', name: { 'zh-HK': '鎖骨鏈', en: 'Layered' }, image: '/products/sub-clavicle.jpg' },
      { id: 'n-heart', name: { 'zh-HK': '愛心項鏈', en: 'Moon' }, image: '/products/sub-heart-neck.jpg' },
    ],
  },
  {
    id: 'earrings',
    name: { 'zh-HK': '耳環', en: 'Earrings' },
    image: mockImage('cat-earrings.jpg'),
    children: [
      { id: 'e-all', name: { 'zh-HK': '全部商品', en: 'All' }, image: '/products/flash-earrings.jpg' },
      { id: 'e-drops', name: { 'zh-HK': '水滴耳環', en: 'Drops' }, image: '/products/hot-drops.jpg' },
    ],
  },
  {
    id: 'rings',
    name: { 'zh-HK': '戒指', en: 'Rings' },
    image: mockImage('cat-ring.jpg'),
    children: [
      { id: 'r-all', name: { 'zh-HK': '全部商品', en: 'All' }, image: '/products/hot-ring.jpg' },
    ],
  },
  {
    id: 'scarves',
    name: { 'zh-HK': '頭巾', en: 'Scarves' },
    image: mockImage('cat-headscarf.jpg'),
    children: [
      { id: 's-all', name: { 'zh-HK': '全部商品', en: 'All' }, image: mockImage('cat-headscarf.jpg') },
    ],
  },
  {
    id: 'bags',
    name: { 'zh-HK': '包袋', en: 'Bags' },
    image: mockImage('cat-bag.jpg'),
    children: [
      { id: 'g-all', name: { 'zh-HK': '全部商品', en: 'All' }, image: mockImage('cat-bag.jpg') },
    ],
  },
  {
    id: 'sets',
    name: { 'zh-HK': '套裝', en: 'Sets' },
    image: '/products/coll-pearl.jpg',
    children: [
      { id: 'set-all', name: { 'zh-HK': '全部商品', en: 'All' }, image: '/products/coll-pearl.jpg' },
    ],
  },
]

export type CartItem = {
  id: string
  productId: string
  name: { 'zh-HK': string; en: string }
  spec: { 'zh-HK': string; en: string }
  price: number
  qty: number
  image: string
  checked: boolean
}

export const initialCart: CartItem[] = [
  {
    id: 'c1',
    productId: 'p1',
    name: { 'zh-HK': '紅色愛心手鏈', en: 'Heart Bracelet' },
    spec: { 'zh-HK': '紅色 / 16 cm', en: 'Red / 16 cm' },
    price: 69,
    qty: 1,
    image: '/products/cart-heart-brace.jpg',
    checked: true,
  },
  {
    id: 'c2',
    productId: 'p2',
    name: { 'zh-HK': '葫蘆項鍊', en: 'Gourd Necklace' },
    spec: { 'zh-HK': '金色 / 均碼', en: 'Gold / One Size' },
    price: 128,
    qty: 1,
    image: '/products/cart-gourd-neck.jpg',
    checked: true,
  },
]

export const accountMock = {
  name: 'Coco',
  phone: '+852 9000 0000',
  avatarLetter: 'C',
  stars: 3,
  spent: 860,
  balance: '15.00',
  coupons: 2,
  points: 300,
  streak: 1,
  toShipBadge: 1,
}

/** 全站 Mock 商品池（详情 / 搜索 / 列表共用，與首頁各區塊共用同一批商品） */
export const allProducts: Product[] = [cushionNecklace, gourdNecklace, cloverBracelet, heartBracelet]

export function findProduct(id: string): Product | undefined {
  return allProducts.find((p) => p.id === id) ?? allProducts[0]
}

export function searchProducts(keyword: string): Product[] {
  const q = keyword.trim().toLowerCase()
  if (!q) return allProducts
  return allProducts.filter((p) =>
    `${p.name['zh-HK']} ${p.name.en}`.toLowerCase().includes(q),
  )
}

export type Localized = { 'zh-HK': string; en: string }

export type SpecOption = {
  id: string
  name: Localized
  image?: string
}

export type SpecGroup = {
  id: string
  name: Localized
  options: SpecOption[]
}

export type SkuQuote = {
  key: string
  price: number
  compareAt: number
  stock: number
}

export type Review = {
  id: string
  stars: number
  author: string
  body: Localized
  images: string[]
  follow?: Localized
}

export type ProductSpecs = {
  compareAt: number
  stock: number
  tags: Localized[]
  detail: Localized
  groups: SpecGroup[]
  skus: SkuQuote[]
  reviews: Review[]
}

const serviceTags: Localized[] = [
  { 'zh-HK': '香港包郵門檻', en: 'Free-shipping threshold' },
  { 'zh-HK': '售後保障', en: 'After-sales cover' },
]

function quotes(price: number, pairs: [string, number, number][]): SkuQuote[] {
  return pairs.map(([key, delta, stock]) => ({
    key,
    price: price + delta,
    compareAt: price + delta + 30,
    stock,
  }))
}

const heartSpecs: ProductSpecs = {
  compareAt: 99,
  stock: 12,
  tags: serviceTags,
  detail: {
    'zh-HK': '紅色愛心手鏈，鍍金鏈身配琺瑯愛心。可選顏色與鏈長，細節見下方實拍。',
    en: 'Heart bracelet with an enamel motif on a gold-tone chain. Choose a color and length. Photos below show the details.',
  },
  groups: [
    {
      id: 'color',
      name: { 'zh-HK': '顏色', en: 'Color' },
      options: [
        { id: 'red', name: { 'zh-HK': '紅色愛心', en: 'Red heart' }, image: heartBracelet.image },
        { id: 'white', name: { 'zh-HK': '白色', en: 'White' }, image: heartBracelet.gallery?.[3] },
        { id: 'gold', name: { 'zh-HK': '金色', en: 'Gold' }, image: heartBracelet.gallery?.[2] },
      ],
    },
    {
      id: 'size',
      name: { 'zh-HK': '尺寸', en: 'Size' },
      options: [
        { id: '16', name: { 'zh-HK': '16 cm', en: '16 cm' } },
        { id: '18', name: { 'zh-HK': '18 cm', en: '18 cm' } },
      ],
    },
  ],
  skus: quotes(69, [
    ['red|16', 0, 12],
    ['red|18', 10, 8],
    ['white|16', 0, 5],
    ['white|18', 10, 3],
    ['gold|16', 20, 6],
    ['gold|18', 30, 2],
  ]),
  reviews: [
    {
      id: 'r1',
      stars: 5,
      author: 'Coco',
      body: { 'zh-HK': '鏈長剛好，愛心顏色和圖片一致。', en: 'The length fits, and the hearts match the photos.' },
      images: [heartBracelet.image],
      follow: { 'zh-HK': '戴了一週，扣環仍然穩。', en: 'After a week, the clasp still feels secure.' },
    },
    {
      id: 'r2',
      stars: 4,
      author: 'Amy',
      body: { 'zh-HK': '日常佩戴很輕，包裝完整。', en: 'Light for everyday wear, and the packaging was complete.' },
      images: [],
    },
  ],
}

function basicSpecs(product: Product): ProductSpecs {
  const photo = product.gallery?.[1] || product.image
  return {
    compareAt: product.price + 30,
    stock: 20,
    tags: serviceTags,
    detail: {
      'zh-HK': `${product.name['zh-HK']}，香港現貨。下方為細節實拍。`,
      en: `${product.name.en}, ready to ship in Hong Kong. Detail photos are below.`,
    },
    groups: [
      {
        id: 'style',
        name: { 'zh-HK': '款式', en: 'Style' },
        options: [
          { id: 'classic', name: { 'zh-HK': '經典', en: 'Classic' }, image: product.image },
          { id: 'detail', name: { 'zh-HK': '細節款', en: 'Detail' }, image: photo },
        ],
      },
    ],
    skus: quotes(product.price, [
      ['classic', 0, 20],
      ['detail', 10, 9],
    ]),
    reviews: [
      {
        id: `${product.id}-r1`,
        stars: 5,
        author: 'Mia',
        body: { 'zh-HK': '成色和描述一致，會再回購。', en: 'It matches the description. I would buy it again.' },
        images: [product.image],
      },
    ],
  }
}

export function specsFor(product: Product): ProductSpecs {
  if (product.id === heartBracelet.id) return heartSpecs
  return basicSpecs(product)
}

export function skuKey(selected: Record<string, string>, groups: SpecGroup[]) {
  return groups.map((group) => selected[group.id] || '').join('|')
}
