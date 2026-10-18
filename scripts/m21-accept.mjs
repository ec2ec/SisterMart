/**
 * SisterMart M21: admin seckill session CRUD → member list visibility.
 */
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'

const BASE = process.env.API_BASE || 'http://127.0.0.1:8000'
const OUT = '/opt/cursor/artifacts/m21-accept'
fs.mkdirSync(OUT, { recursive: true })

const results = []
function check(id, title, pass, detail = '') {
  results.push({ id, title, pass: Boolean(pass), detail: String(detail || '') })
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${id}  ${title}${detail ? ' — ' + detail : ''}`)
}

async function api(method, urlPath, { body, token, expectHttp } = {}) {
  const headers = { Accept: 'application/json' }
  if (token) headers.Authorization = `Bearer ${token}`
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  const res = await fetch(`${BASE}${urlPath}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  const text = await res.text()
  let json
  try {
    json = JSON.parse(text)
  } catch {
    json = { parse_error: true, text: text.slice(0, 300) }
  }
  if (expectHttp !== undefined && res.status !== expectHttp) {
    throw new Error(`HTTP ${res.status} expected ${expectHttp} ${urlPath}: ${text.slice(0, 400)}`)
  }
  return { status: res.status, json }
}

function ok(json) {
  return json && json.code === 0
}

/** Format wall-clock in API TZ (Asia/Shanghai); Node host is often UTC. */
function sqlDt(offsetMs = 0) {
  const d = new Date(Date.now() + offsetMs)
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Shanghai',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  }).formatToParts(d)
  const g = (t) => parts.find((p) => p.type === t)?.value || '00'
  return `${g('year')}-${g('month')}-${g('day')} ${g('hour')}:${g('minute')}:${g('second')}`
}

async function loginAdmin() {
  const res = await api('POST', '/api/v1/admin/sessions', {
    body: { email: 'admin@sistermart.local', password: 'admin123456' },
  })
  return res.json.data.token
}

async function main() {
  execSync('php apps/api/database/m11_seed.php', { stdio: 'inherit', cwd: '/workspace' })

  const adminToken = await loginAdmin()

  const list0 = await api('GET', '/api/v1/admin/seckill/sessions', {
    token: adminToken,
    expectHttp: 200,
  })
  check('A1', '後台場次列表可用', ok(list0.json) && Array.isArray(list0.json.data.items), `n=${list0.json.data?.items?.length}`)

  const p1 = await api('GET', '/api/v1/admin/products/p1', { token: adminToken, expectHttp: 200 })
  const sku = (p1.json.data.skus || [])[0]
  check('A2', '商品 p1 有 SKU 可掛秒殺', ok(p1.json) && sku?.id, `sku=${sku?.id}`)

  const code = `sk-m21-${Date.now().toString(36).slice(-5)}`
  const created = await api('POST', '/api/v1/admin/seckill/sessions', {
    token: adminToken,
    body: {
      code,
      name_zh: 'M21 驗收場',
      name_en: 'M21 Accept',
      starts_at: sqlDt(-60_000),
      ends_at: sqlDt(2 * 3600_000),
      items: [
        {
          product_code: 'p1',
          sku_id: sku.id,
          seckill_price: 88,
          origin_price: Number(sku.compare_at || sku.price || 199),
          stock: 7,
        },
      ],
    },
    expectHttp: 201,
  })
  check(
    'A3',
    '後台新建 live 場次成功',
    ok(created.json) &&
      created.json.data.code === code &&
      created.json.data.status === 'live' &&
      created.json.data.items?.length === 1,
    JSON.stringify({
      id: created.json.data?.id,
      status: created.json.data?.status,
      items: created.json.data?.items?.length,
    }),
  )

  const sessionId = created.json.data.id
  const itemId = created.json.data.items[0].id

  const read = await api('GET', `/api/v1/admin/seckill/sessions/${sessionId}`, {
    token: adminToken,
    expectHttp: 200,
  })
  check('A4', '後台讀取場次', ok(read.json) && read.json.data.id === sessionId)

  const updated = await api('PUT', `/api/v1/admin/seckill/sessions/${sessionId}`, {
    token: adminToken,
    body: {
      name_zh: 'M21 驗收場（改）',
      items: [
        {
          id: itemId,
          product_code: 'p1',
          sku_id: sku.id,
          seckill_price: 77,
          origin_price: 199,
          stock: 5,
        },
      ],
    },
    expectHttp: 200,
  })
  check(
    'A5',
    '後台更新場次與庫存/價格',
    ok(updated.json) &&
      updated.json.data.name['zh-HK'] === 'M21 驗收場（改）' &&
      Number(updated.json.data.items[0].seckill_price) === 77 &&
      Number(updated.json.data.items[0].stock) === 5,
    JSON.stringify(updated.json.data?.items?.[0]),
  )

  const memberList = await api('GET', '/api/v1/seckill/sessions', { expectHttp: 200 })
  const found = (memberList.json.data.items || []).find((s) => s.id === code)
  check(
    'A6',
    '會員列表可見新建 live 場次',
    ok(memberList.json) && found && found.products?.some((p) => Number(p.seckill_price) === 77),
    JSON.stringify({
      found: !!found,
      price: found?.products?.[0]?.seckill_price,
      stock: found?.products?.[0]?.stock,
    }),
  )

  const detail = await api('GET', `/api/v1/seckill/sessions/${code}`, { expectHttp: 200 })
  check(
    'A7',
    '會員場次詳情可用',
    ok(detail.json) && detail.json.data.id === code && detail.json.data.status === 'live',
    detail.json.data?.status,
  )

  const bad = await api('POST', '/api/v1/admin/seckill/sessions', {
    token: adminToken,
    body: {
      code,
      name_zh: 'dup',
      starts_at: sqlDt(-60_000),
      ends_at: sqlDt(2 * 3600_000),
      items: [{ product_code: 'p1', sku_id: sku.id, seckill_price: 1, origin_price: 2, stock: 1 }],
    },
  })
  check(
    'A8',
    '重複場次代碼被拒',
    !ok(bad.json) && (bad.status === 422 || bad.json.code !== 0),
    bad.json.message || bad.json.error?.name,
  )

  const failed = results.filter((r) => !r.pass)
  fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify({ results, failed: failed.length }, null, 2))
  console.log(`\n${results.length - failed.length}/${results.length} passed`)
  if (failed.length) process.exit(1)
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
