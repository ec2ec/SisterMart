export type AdminMenu = {
  label: string
  key: string
  path?: string
  milestone?: string
  children?: AdminMenu[]
}

export const adminMenus: AdminMenu[] = [
  { label: '概覽', key: '/', path: '/' },
  {
    label: '商品目錄',
    key: 'catalog',
    children: [
      { label: '商品', key: '/catalog/products', path: '/catalog/products' },
      { label: '分類', key: '/catalog/categories', path: '/catalog/categories' },
    ],
  },
  {
    label: '銷售',
    key: 'sales',
    children: [
      { label: '訂單', key: '/sales/orders', path: '/sales/orders' },
      { label: '售後', key: '/sales/aftersales', path: '/sales/aftersales' },
    ],
  },
  {
    label: '會員',
    key: 'customers',
    children: [{ label: '會員', key: '/customers/members', path: '/customers/members', milestone: '後續' }],
  },
  {
    label: '內容',
    key: 'cms',
    children: [
      { label: '文章', key: '/cms/articles', path: '/cms/articles' },
      { label: '首頁', key: '/cms/home', path: '/cms/home', milestone: '後續' },
      { label: '消息', key: '/cms/messages', path: '/cms/messages', milestone: '後續' },
    ],
  },
  {
    label: '營銷',
    key: 'marketing',
    children: [
      { label: '優惠券', key: '/marketing/coupons', path: '/marketing/coupons', milestone: '後續' },
      { label: '積分', key: '/marketing/points', path: '/marketing/points', milestone: '後續' },
      { label: '秒殺', key: '/marketing/seckill', path: '/marketing/seckill', milestone: '二期' },
      { label: '分銷', key: '/marketing/distribution', path: '/marketing/distribution', milestone: '二期' },
    ],
  },
  {
    label: '設置',
    key: 'settings',
    children: [
      { label: '底部導航', key: '/settings/tabbar', path: '/settings/tabbar' },
      { label: '店鋪參數', key: '/settings/shop', path: '/settings/shop' },
      { label: '管理員與角色', key: '/settings/admins', path: '/settings/admins', milestone: '後續' },
    ],
  },
]

export function menuOptions() {
  return adminMenus.map(toOption)
}

function toOption(item: AdminMenu): { label: string; key: string; children?: ReturnType<typeof toOption>[] } {
  return {
    label: item.label,
    key: item.key,
    children: item.children?.map(toOption),
  }
}

export function findMenu(path: string) {
  const walk = (items: AdminMenu[]): AdminMenu | undefined => {
    for (const item of items) {
      if (item.path === path) return item
      const child = item.children ? walk(item.children) : undefined
      if (child) return child
    }
    return undefined
  }
  return walk(adminMenus)
}
