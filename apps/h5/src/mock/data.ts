export type Product = {
  id: string
  name: { 'zh-HK': string; en: string }
  price: number
  sold: string
  image: string
  badge?: 'flash' | 'hot' | 'new' | 'editor'
  liked?: boolean
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
  {
    id: 'b1',
    image: '/products/hero-bracelet.jpg',
    eyebrow: true,
  },
  {
    id: 'b2',
    image: '/products/flash-bracelet.jpg',
    eyebrow: true,
  },
  {
    id: 'b3',
    image: '/products/coll-pearl.jpg',
    eyebrow: true,
  },
]

export const flashProducts: Product[] = [
  {
    id: 'p-flash-1',
    name: { 'zh-HK': '手鏈', en: 'Bracelet' },
    price: 49,
    sold: '80+',
    image: '/products/flash-bracelet.jpg',
    badge: 'flash',
    liked: true,
  },
  {
    id: 'p-flash-2',
    name: { 'zh-HK': '耳環', en: 'Earrings' },
    price: 39,
    sold: '20+',
    image: '/products/flash-earrings.jpg',
    badge: 'hot',
    liked: true,
  },
]

export const homeCategories = [
  { id: 'necklaces', name: { 'zh-HK': '項鏈', en: 'Necklaces' }, image: '/products/cat-necklace.jpg' },
  { id: 'bracelets', name: { 'zh-HK': '手鏈', en: 'Bracelets' }, image: '/products/cat-bracelet.jpg' },
  { id: 'earrings', name: { 'zh-HK': '耳環', en: 'Earrings' }, image: '/products/cat-earrings.jpg' },
  { id: 'rings', name: { 'zh-HK': '戒指', en: 'Rings' }, image: '/products/cat-rings.jpg' },
  { id: 'scarves', name: { 'zh-HK': '頭巾', en: 'Scarves' }, image: '/products/cat-scarves.jpg' },
  { id: 'bags', name: { 'zh-HK': '包袋', en: 'Bags' }, image: '/products/cat-bags.jpg' },
]

export const collectionProducts: Product[] = [
  {
    id: 'p-col-1',
    name: { 'zh-HK': '葫蘆項鍊', en: 'Pearl Necklace' },
    price: 128,
    sold: '88',
    image: '/products/coll-gourd.jpg',
    badge: 'new',
    liked: true,
  },
  {
    id: 'p-col-2',
    name: { 'zh-HK': '珍珠項鍊', en: 'Pearl Strand' },
    price: 158,
    sold: '56',
    image: '/products/coll-pearl.jpg',
    badge: 'editor',
    liked: true,
  },
]

export const feedProducts: Product[] = [
  {
    id: 'p-hot-1',
    name: { 'zh-HK': '鑽戒', en: 'Halo Ring' },
    price: 299,
    sold: '100+',
    image: '/products/hot-ring.jpg',
    badge: 'hot',
    liked: true,
  },
  {
    id: 'p-hot-2',
    name: { 'zh-HK': '愛心耳環', en: 'Heart Drops' },
    price: 89,
    sold: '64',
    image: '/products/hot-drops.jpg',
    badge: 'hot',
    liked: true,
  },
]

export const categories: CategoryNode[] = [
  {
    id: 'bracelets',
    name: { 'zh-HK': '手鏈', en: 'Bracelets' },
    image: '/products/cat-bracelet.jpg',
    children: [
      { id: 'b-all', name: { 'zh-HK': '全部商品', en: 'All' }, image: '/products/sub-all-brace.jpg' },
      { id: 'b-heart', name: { 'zh-HK': '愛心手鏈', en: 'Heart' }, image: '/products/sub-heart-brace.jpg' },
      { id: 'b-gold', name: { 'zh-HK': '金珠手鏈', en: 'Gold Link' }, image: '/products/sub-gold-bead.jpg' },
    ],
  },
  {
    id: 'necklaces',
    name: { 'zh-HK': '項鏈', en: 'Necklaces' },
    image: '/products/cat-necklace.jpg',
    children: [
      { id: 'n-all', name: { 'zh-HK': '全部商品', en: 'All' }, image: '/products/sub-all-neck.jpg' },
      { id: 'n-gourd', name: { 'zh-HK': '葫蘆項鏈', en: 'Pearls' }, image: '/products/sub-gourd.jpg' },
      { id: 'n-square', name: { 'zh-HK': '方鑽項鏈', en: 'Gemstone' }, image: '/products/sub-square.jpg' },
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
