export type Product = {
  id: string
  name: { 'zh-HK': string; en: string }
  price: number
  sold: string
  image: string
  gallery?: string[]
  category?: string
  badge?: 'flash' | 'hot' | 'new' | 'editor'
  liked?: boolean
}

function mockImage(file: string) {
  return `/mock/${encodeURIComponent(file)}`
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
  { id: 'b1', image: mockImage('首頁頂部廣告圖-1.png') },
  { id: 'b2', image: mockImage('首頁頂部廣告圖-2.png') },
]

const cushionNecklace: Product = {
  id: 'p1',
  name: { 'zh-HK': '方鑽項鏈', en: 'Cushion Necklace' },
  price: 128,
  sold: '56',
  image: mockImage('產品圖-1.jpg'),
  gallery: [mockImage('產品圖-1.jpg'), mockImage('產品圖-1-詳情.jpg'), mockImage('產品圖-1-詳情2.jpg')],
  category: 'necklaces',
  badge: 'flash',
  liked: true,
}

const gourdNecklace: Product = {
  id: 'p2',
  name: { 'zh-HK': '葫蘆項鏈', en: 'Gourd Necklace' },
  price: 128,
  sold: '88',
  image: mockImage('產品圖-2.jpg'),
  gallery: [
    mockImage('產品圖-2.jpg'),
    mockImage('產品圖-2-詳情1.jpg'),
    mockImage('產品圖-2-詳情2.jpg'),
    mockImage('產品圖-2-詳情3.jpg'),
  ],
  category: 'necklaces',
  badge: 'new',
  liked: true,
}

const cloverBracelet: Product = {
  id: 'p3',
  name: { 'zh-HK': '四葉草手鏈', en: 'Clover Bracelet' },
  price: 49,
  sold: '80+',
  image: mockImage('產品圖-3.jpg'),
  gallery: [
    mockImage('產品圖-3.jpg'),
    mockImage('產品圖-3-詳情.jpg'),
    mockImage('產品圖-3-詳情2.jpg'),
    mockImage('產品圖-3-詳情3.jpg'),
  ],
  category: 'bracelets',
  badge: 'hot',
  liked: true,
}

const heartBracelet: Product = {
  id: 'p4',
  name: { 'zh-HK': '紅心手鏈', en: 'Heart Bracelet' },
  price: 69,
  sold: '40+',
  image: mockImage('產品圖-4.jpg'),
  gallery: [
    mockImage('產品圖-4.jpg'),
    mockImage('產品圖-4-詳情1.jpg'),
    mockImage('產品圖-4-詳情2.jpg'),
    mockImage('產品圖-4-詳情3.jpg'),
  ],
  category: 'bracelets',
  badge: 'flash',
  liked: true,
}

export const flashProducts: Product[] = [heartBracelet, cloverBracelet, cushionNecklace, gourdNecklace]

export const homeCategories = [
  { id: 'necklaces', name: { 'zh-HK': '項鏈', en: 'Necklaces' }, image: cushionNecklace.image },
  { id: 'bracelets', name: { 'zh-HK': '手鏈', en: 'Bracelets' }, image: heartBracelet.image },
  { id: 'earrings', name: { 'zh-HK': '耳環', en: 'Earrings' }, image: '/products/cat-earrings.jpg' },
  { id: 'rings', name: { 'zh-HK': '戒指', en: 'Rings' }, image: '/products/cat-rings.jpg' },
  { id: 'scarves', name: { 'zh-HK': '頭巾', en: 'Scarves' }, image: '/products/cat-scarves.jpg' },
  { id: 'bags', name: { 'zh-HK': '包袋', en: 'Bags' }, image: '/products/cat-bags.jpg' },
]

export const collectionProducts: Product[] = [cushionNecklace, gourdNecklace]

export const feedProducts: Product[] = [cloverBracelet, heartBracelet]

export const categories: CategoryNode[] = [
  {
    id: 'bracelets',
    name: { 'zh-HK': '手鏈', en: 'Bracelets' },
    image: heartBracelet.image,
    children: [
      { id: 'b-all', name: { 'zh-HK': '全部商品', en: 'All' }, image: cloverBracelet.image },
      { id: 'b-heart', name: { 'zh-HK': '愛心手鏈', en: 'Heart' }, image: heartBracelet.image },
      { id: 'b-gold', name: { 'zh-HK': '金珠手鏈', en: 'Gold Link' }, image: '/products/sub-gold-bead.jpg' },
    ],
  },
  {
    id: 'necklaces',
    name: { 'zh-HK': '項鏈', en: 'Necklaces' },
    image: cushionNecklace.image,
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
    image: '/products/cat-earrings.jpg',
    children: [
      { id: 'e-all', name: { 'zh-HK': '全部商品', en: 'All' }, image: '/products/flash-earrings.jpg' },
      { id: 'e-drops', name: { 'zh-HK': '水滴耳環', en: 'Drops' }, image: '/products/hot-drops.jpg' },
    ],
  },
  {
    id: 'rings',
    name: { 'zh-HK': '戒指', en: 'Rings' },
    image: '/products/cat-rings.jpg',
    children: [
      { id: 'r-all', name: { 'zh-HK': '全部商品', en: 'All' }, image: '/products/hot-ring.jpg' },
    ],
  },
  {
    id: 'scarves',
    name: { 'zh-HK': '頭巾', en: 'Scarves' },
    image: '/products/cat-scarves.jpg',
    children: [
      { id: 's-all', name: { 'zh-HK': '全部商品', en: 'All' }, image: '/products/cat-scarves.jpg' },
    ],
  },
  {
    id: 'bags',
    name: { 'zh-HK': '包袋', en: 'Bags' },
    image: '/products/cat-bags.jpg',
    children: [
      { id: 'g-all', name: { 'zh-HK': '全部商品', en: 'All' }, image: '/products/cat-bags.jpg' },
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
