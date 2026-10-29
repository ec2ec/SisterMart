export type AdminMenu = {
  label: string
  key: string
  path?: string
  milestone?: string
  /** 僅列角色可見；省略則全員 */
  roles?: string[]
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
      { label: '角標', key: '/catalog/badges', path: '/catalog/badges' },
      { label: '服務標籤', key: '/catalog/service-tags', path: '/catalog/service-tags' },
      { label: '商品參數', key: '/catalog/attrs', path: '/catalog/attrs' },
      { label: '規格模板', key: '/catalog/spec-templates', path: '/catalog/spec-templates' },
      { label: '規格管理', key: '/catalog/specs', path: '/catalog/specs' },
    ],
  },
  {
    label: '銷售',
    key: 'sales',
    children: [
      { label: '訂單', key: '/sales/orders', path: '/sales/orders' },
      { label: '售後', key: '/sales/aftersales', path: '/sales/aftersales' },
      { label: '評價', key: '/sales/reviews', path: '/sales/reviews' },
    ],
  },
  {
    label: '會員',
    key: 'customers',
    children: [
      { label: '會員', key: '/customers/members', path: '/customers/members' },
      { label: '會員等級', key: '/customers/levels', path: '/customers/levels' },
    ],
  },
  {
    label: '首頁運營',
    key: 'home-ops',
    children: [
      { label: '首頁佈局', key: '/home-ops/layout', path: '/home-ops/layout' },
      { label: '輪播廣告', key: '/home-ops/banners', path: '/home-ops/banners' },
      { label: '廣告字幕', key: '/home-ops/marquee', path: '/home-ops/marquee' },
      { label: '頁腳標語', key: '/home-ops/slogan', path: '/home-ops/slogan' },
      { label: '活動專區', key: '/home-ops/campaigns', path: '/home-ops/campaigns' },
      { label: '商品櫥窗', key: '/home-ops/showcases', path: '/home-ops/showcases' },
      { label: '首頁遮罩', key: '/home-ops/popups', path: '/home-ops/popups' },
    ],
  },
  {
    label: '內容',
    key: 'cms',
    children: [
      { label: '文章', key: '/cms/articles', path: '/cms/articles' },
      { label: '系統消息', key: '/cms/messages', path: '/cms/messages' },
      { label: '發送流水', key: '/cms/notify-outbox', path: '/cms/notify-outbox' },
      { label: '訂閱名單', key: '/cms/newsletter', path: '/cms/newsletter' },
    ],
  },
  {
    label: '營銷',
    key: 'marketing',
    children: [
      { label: '優惠券', key: '/marketing/coupons', path: '/marketing/coupons' },
      { label: '積分', key: '/marketing/points', path: '/marketing/points' },
      { label: '秒殺活動', key: '/marketing/seckill', path: '/marketing/seckill' },
      { label: '分銷', key: '/marketing/distribution', path: '/marketing/distribution' },
    ],
  },
  {
    label: '設置',
    key: 'settings',
    children: [
      { label: '底部導航', key: '/settings/tabbar', path: '/settings/tabbar' },
      { label: '店鋪維護', key: '/settings/maintenance', path: '/settings/maintenance' },
      { label: '新用戶配置', key: '/settings/registration', path: '/settings/registration' },
      { label: '店鋪參數', key: '/settings/shop', path: '/settings/shop' },
      { label: '發佈通道', key: '/settings/releases', path: '/settings/releases', roles: ['super'] },
      { label: '管理員與角色', key: '/settings/admins', path: '/settings/admins', roles: ['super'] },
    ],
  },
]

export function menusForRole(role?: string | null) {
  const allow = (item: AdminMenu) => !item.roles || (role ? item.roles.includes(role) : false)
  const walk = (items: AdminMenu[]): AdminMenu[] =>
    items
      .filter(allow)
      .map((item) => ({
        ...item,
        children: item.children ? walk(item.children) : undefined,
      }))
      .filter((item) => !item.children || item.children.length > 0 || item.path)

  return walk(adminMenus)
}

export function menuOptions(role?: string | null) {
  return menusForRole(role).map(toOption)
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
