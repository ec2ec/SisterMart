#!/usr/bin/env node
/**
 * 經後台 API 灌入 200 條測試商品（冪等：code=TSEED001…TSEED200）。
 *
 * 用法：
 *   SM_ADMIN_API_BASE=https://sistermart.vip/api/index.php \
 *   SM_ADMIN_EMAIL=... SM_ADMIN_PASSWORD=... \
 *   node scripts/seed-200-products.mjs
 */
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

function loadEnvFile(path) {
  try {
    const text = readFileSync(path, 'utf8')
    for (const line of text.split('\n')) {
      const m = line.match(/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/)
      if (!m) continue
      const key = m[1]
      let val = m[2].trim()
      if ((val.startsWith("'") && val.endsWith("'")) || (val.startsWith('"') && val.endsWith('"'))) {
        val = val.slice(1, -1)
      }
      if (!process.env[key]) process.env[key] = val
    }
  } catch {
    /* optional */
  }
}

loadEnvFile(resolve('/cursor/stores/self/sistermart-agent-admin.env'))

const API = (process.env.SM_ADMIN_API_BASE || '').replace(/\/$/, '')
const EMAIL = process.env.SM_ADMIN_EMAIL || ''
const PASSWORD = process.env.SM_ADMIN_PASSWORD || ''
const COUNT = Number(process.env.SEED_COUNT || 200)

if (!API || !EMAIL || !PASSWORD) {
  console.error('缺少 SM_ADMIN_API_BASE / SM_ADMIN_EMAIL / SM_ADMIN_PASSWORD')
  process.exit(1)
}

// 僅用線上已存在的圖（VPS 默認包不含全部 /mock）
const MOCK_IMAGES = [
  '/mock/product-1.jpg',
  '/mock/product-1-detail.jpg',
  '/mock/product-2.jpg',
  '/mock/product-2-detail-1.jpg',
  '/mock/product-3.jpg',
  '/mock/product-4.jpg',
  '/storage/product/1/20261022/d243e6b9be7eea6b.jpg',
]

const NAMES = [
  ['測試手鏈', 'Test Bracelet'],
  ['測試項鏈', 'Test Necklace'],
  ['測試耳環', 'Test Earrings'],
  ['測試戒指', 'Test Ring'],
  ['測試頭巾', 'Test Scarf'],
  ['測試包袋', 'Test Bag'],
  ['測試套裝', 'Test Set'],
]

async function req(path, { method = 'GET', token, body } = {}) {
  const headers = { Accept: 'application/json' }
  if (token) headers.Authorization = `Bearer ${token}`
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  const res = await fetch(`${API}${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })
  const json = await res.json().catch(() => ({}))
  if (!res.ok || json.code !== 0) {
    const msg = json.message || res.statusText || 'request failed'
    throw new Error(`${method} ${path}: ${msg}`)
  }
  return json.data
}

async function main() {
  console.log(`login ${EMAIL} @ ${API}`)
  const login = await req('/api/v1/admin/sessions', {
    method: 'POST',
    body: { email: EMAIL, password: PASSWORD },
  })
  const token = login.token
  const cats = await req('/api/v1/admin/categories', { token })
  const catList = Array.isArray(cats) ? cats : cats.items || []
  if (!catList.length) throw new Error('無分類，無法灌數')
  const catIds = []
  const walk = (nodes) => {
    for (const n of nodes || []) {
      if (n?.id) catIds.push(n.id)
      if (n?.children?.length) walk(n.children)
    }
  }
  walk(catList)

  let created = 0
  let updated = 0
  let failed = 0

  // 先搜一輪現有 TSEED*（list 無 offset；缺的靠 code 探查後 PUT）
  const existing = new Map()
  const listed = await req('/api/v1/admin/products?q=TSEED&limit=100', { token })
  for (const p of listed.items || []) {
    if (String(p.code || '').startsWith('TSEED')) existing.set(p.code, p.db_id || p.id)
  }
  console.log(`existing TSEED cache: ${existing.size}`)

  for (let i = 1; i <= COUNT; i++) {
    const code = `TSEED${String(i).padStart(3, '0')}`
    const catId = catIds[(i - 1) % catIds.length]
    const [zhBase, enBase] = NAMES[(i - 1) % NAMES.length]
    const img = MOCK_IMAGES[(i - 1) % MOCK_IMAGES.length]
    const img2 = MOCK_IMAGES[i % MOCK_IMAGES.length]
    const price = 49 + (i % 50) * 3
    const soldBoost = (i * 7) % 200
    const body = {
      code,
      category_id: catId,
      status: 1,
      sold_boost: soldBoost,
      name: { 'zh-HK': `${zhBase} #${i}`, en: `${enBase} #${i}` },
      detail: {
        'zh-HK': `<p>測試商品 ${code}，用於首頁無限滾動。</p>`,
        en: `<p>Test product ${code} for home infinite scroll.</p>`,
      },
      badges: i % 5 === 0 ? ['new'] : i % 7 === 0 ? ['hot'] : [],
      service_tag_ids: [],
      attr_value_ids: [],
      media: [
        { media_type: 'image', url: img },
        { media_type: 'image', url: img2 },
      ],
      spec_groups: [
        {
          code: 'style',
          name: { 'zh-HK': '款式', en: 'Style' },
          options: [
            { code: 'a', name: { 'zh-HK': '經典', en: 'Classic' }, image: img },
            { code: 'b', name: { 'zh-HK': '細節', en: 'Detail' }, image: img2 },
          ],
        },
      ],
      skus: [
        {
          code: 'a',
          price,
          compare_at: price + 40,
          agent_price: Math.floor(price * 0.55),
          cost_price: Math.floor(price * 0.4),
          stock: 20 + (i % 30),
          option_refs: ['style:a'],
        },
        {
          code: 'b',
          price: price + 10,
          compare_at: price + 50,
          agent_price: Math.floor((price + 10) * 0.55),
          cost_price: Math.floor((price + 10) * 0.4),
          stock: 15 + (i % 20),
          option_refs: ['style:b'],
        },
      ],
    }

    try {
      let dbId = existing.get(code)
      if (!dbId) {
        try {
          const one = await req(`/api/v1/admin/products/${encodeURIComponent(code)}`, { token })
          dbId = one.db_id
          if (dbId) existing.set(code, dbId)
        } catch {
          /* create path */
        }
      }
      if (dbId) {
        const saved = await req(`/api/v1/admin/products/${dbId}`, { method: 'PUT', token, body })
        updated++
        if (i <= 3 || i === COUNT) {
          console.log(`put ${code} id=${dbId} image=${saved.image}`)
        }
      } else {
        const saved = await req('/api/v1/admin/products', { method: 'POST', token, body })
        created++
        if (saved.db_id) existing.set(code, saved.db_id)
        if (i <= 3) console.log(`post ${code} image=${saved.image}`)
      }
      if (i % 20 === 0) console.log(`… ${i}/${COUNT} (created=${created} updated=${updated})`)
    } catch (e) {
      failed++
      console.error(`fail ${code}:`, e.message || e)
    }
  }

  console.log(JSON.stringify({ created, updated, failed, total: COUNT }, null, 2))
  if (failed) process.exit(2)
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
