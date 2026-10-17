/**
 * SisterMart M17: aftersale window + evidence upload + order list cover.
 */
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'

const BASE = process.env.API_BASE || 'http://127.0.0.1:8000'
const OUT = '/opt/cursor/artifacts/m17-accept'
fs.mkdirSync(OUT, { recursive: true })

const results = []
function check(id, title, pass, detail = '') {
  results.push({ id, title, pass: Boolean(pass), detail: String(detail || '') })
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${id}  ${title}${detail ? ' — ' + detail : ''}`)
}

async function api(method, urlPath, { body, token, expectHttp, formData } = {}) {
  const headers = { Accept: 'application/json' }
  if (token) headers.Authorization = `Bearer ${token}`
  if (body !== undefined && !formData) headers['Content-Type'] = 'application/json'
  const res = await fetch(`${BASE}${urlPath}`, {
    method,
    headers,
    body: formData || (body === undefined ? undefined : JSON.stringify(body)),
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

async function registerMember(prefix) {
  const email = `${prefix}${Date.now()}@test.local`
  const codeRes = await api('POST', '/api/v1/verification-codes', {
    body: { channel: 'email', purpose: 'register', target: email },
  })
  const code = codeRes.json.data.debug_code
  const reg = await api('POST', '/api/v1/members', {
    body: { channel: 'email', email, password: 'secret123', code, agreed: true },
  })
  if (!ok(reg.json)) throw new Error('register failed ' + JSON.stringify(reg.json))
  return { token: reg.json.data.token, member: reg.json.data.member }
}

async function loginAdmin() {
  const res = await api('POST', '/api/v1/admin/sessions', {
    body: { email: 'admin@sistermart.local', password: 'admin123456' },
  })
  return res.json.data.token
}

async function paidOrder(token) {
  const addr = await api('POST', '/api/v1/addresses', {
    token,
    body: {
      receiver: 'M17',
      phone: '90001717',
      region: '九龍',
      district: '旺角',
      detail: 'M17街1號',
      is_default: true,
    },
    expectHttp: 201,
  })
  const p1 = await api('GET', '/api/v1/products/p1')
  const sku = p1.json.data.skus.find((s) => s.stock > 0)
  const orderRes = await api('POST', '/api/v1/orders', {
    token,
    body: { address_id: addr.json.data.id, buy_now: true, sku_id: sku.id, qty: 1 },
    expectHttp: 201,
  })
  let order = orderRes.json.data
  if (order.status === 'pending_payment') {
    const pay = await api('POST', `/api/v1/orders/${order.id}/payments`, {
      token,
      body: { channel: 'fps' },
      expectHttp: 201,
    })
    await api('POST', `/api/v1/payments/${pay.json.data.payment_id}/mock-confirm`, {
      token,
      expectHttp: 200,
    })
  }
  const detail = await api('GET', `/api/v1/orders/${order.id}`, { token, expectHttp: 200 })
  return detail.json.data
}

function tinyPng() {
  // 1x1 PNG
  return Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
  )
}

async function main() {
  execSync('php database/m17_seed.php', { cwd: path.resolve('apps/api'), stdio: 'inherit' })
  const adminToken = await loginAdmin()
  const { token } = await registerMember('m17')

  const list = await api('GET', '/api/v1/orders', { token, expectHttp: 200 })
  check('A0', '訂單列表接口可用', ok(list.json))

  const order = await paidOrder(token)
  check('A1', '待發貨可申請（未過期）', order.aftersale_eligible === true && order.aftersale_expired === false, JSON.stringify({
    eligible: order.aftersale_eligible,
    expired: order.aftersale_expired,
  }))

  const list2 = await api('GET', '/api/v1/orders', { token, expectHttp: 200 })
  const row = (list2.json.data.items || []).find((o) => o.id === order.id)
  check('A2', '列表含 cover 縮略圖字段', Boolean(row?.cover), row?.cover)

  // evidence upload
  const fd = new FormData()
  fd.append('file', new Blob([tinyPng()], { type: 'image/png' }), 'e.png')
  const up = await api('POST', '/api/v1/uploads/aftersale-evidence', {
    token,
    formData: fd,
    expectHttp: 201,
  })
  check('A3', '證據圖上傳成功', ok(up.json) && String(up.json.data.url).includes('/storage/aftersale/'), up.json.data?.url)

  const as = await api('POST', '/api/v1/aftersales', {
    token,
    body: {
      order_id: order.id,
      order_item_id: order.items[0].id,
      type: 'refund_only',
      refund_to: 'balance',
      description: 'M17 驗收：商品有問題需要退款',
      evidence: [up.json.data.url],
    },
    expectHttp: 201,
  })
  check(
    'A4',
    '售後申請寫入 evidence',
    ok(as.json) && Array.isArray(as.json.data.evidence) && as.json.data.evidence.length === 1,
    JSON.stringify(as.json.data.evidence),
  )

  // complete another order then age it beyond window
  const order2 = await paidOrder(token)
  await api('POST', `/api/v1/admin/orders/${order2.id}/shipments`, {
    token: adminToken,
    body: { company: 'SF', tracking_no: 'SFM17' + Date.now() },
    expectHttp: 201,
  })
  await api('POST', `/api/v1/orders/${order2.id}/confirm-receipt`, { token, expectHttp: 200 })
  execSync(
    `mysql -usistermart -psistermart -h127.0.0.1 sistermart -e "UPDATE orders SET completed_at=DATE_SUB(NOW(), INTERVAL 31 DAY) WHERE id=${order2.id}"`,
    { stdio: 'inherit' },
  )
  const aged = await api('GET', `/api/v1/orders/${order2.id}`, { token, expectHttp: 200 })
  check(
    'A5',
    '完成超過窗口 → expired',
    aged.json.data.aftersale_expired === true && aged.json.data.aftersale_eligible === false,
    JSON.stringify({
      expired: aged.json.data.aftersale_expired,
      eligible: aged.json.data.aftersale_eligible,
      wa: aged.json.data.cs_whatsapp,
    }),
  )

  const denied = await api('POST', '/api/v1/aftersales', {
    token,
    body: {
      order_id: order2.id,
      order_item_id: aged.json.data.items[0].id,
      type: 'return_refund',
      refund_to: 'balance',
      description: '應該被拒絕',
    },
  })
  check(
    'A6',
    '超窗 apply 返回 AFTERSALE_EXPIRED',
    denied.json.error?.name === 'AFTERSALE_EXPIRED' || denied.status === 422,
    denied.json.error?.name || denied.json.message,
  )

  // within window completed
  const order3 = await paidOrder(token)
  await api('POST', `/api/v1/admin/orders/${order3.id}/shipments`, {
    token: adminToken,
    body: { company: 'SF', tracking_no: 'SFM17B' + Date.now() },
    expectHttp: 201,
  })
  await api('POST', `/api/v1/orders/${order3.id}/confirm-receipt`, { token, expectHttp: 200 })
  const fresh = await api('GET', `/api/v1/orders/${order3.id}`, { token, expectHttp: 200 })
  check(
    'A7',
    '剛完成仍可申請',
    fresh.json.data.aftersale_eligible === true && fresh.json.data.aftersale_expired === false,
  )

  const shop = await api('GET', '/api/v1/admin/settings/shop', { token: adminToken, expectHttp: 200 })
  check(
    'A8',
    '後台可讀售後窗口與 WhatsApp',
    ok(shop.json) && shop.json.data.aftersale_window_days >= 1 && shop.json.data.cs_whatsapp,
    JSON.stringify(shop.json.data),
  )

  const saved = await api('PUT', '/api/v1/admin/settings/shop', {
    token: adminToken,
    body: { aftersale_window_days: 30, cs_whatsapp: '85290001234' },
    expectHttp: 200,
  })
  check('A9', '後台可保存售後參數', ok(saved.json) && saved.json.data.aftersale_window_days === 30)

  // oversize upload
  const big = Buffer.alloc(1024 * 1024 + 10, 1)
  const fdBig = new FormData()
  fdBig.append('file', new Blob([big], { type: 'image/jpeg' }), 'big.jpg')
  const upBig = await api('POST', '/api/v1/uploads/aftersale-evidence', { token, formData: fdBig })
  check(
    'A10',
    '超過 1MB 上傳被拒',
    !ok(upBig.json) && (upBig.status === 422 || upBig.json.code !== 0),
    upBig.json.message || upBig.json.error?.name,
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
