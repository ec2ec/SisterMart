/**
 * SisterMart M18: product reviews + order list tabs (to_review / aftersale).
 */
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'

const BASE = process.env.API_BASE || 'http://127.0.0.1:8000'
const OUT = '/opt/cursor/artifacts/m18-accept'
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
      receiver: 'M18',
      phone: '90001818',
      region: '九龍',
      district: '旺角',
      detail: 'M18街1號',
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

async function completeOrder(token, adminToken, orderId) {
  await api('POST', `/api/v1/admin/orders/${orderId}/shipments`, {
    token: adminToken,
    body: { company: 'SF', tracking_no: 'SFM18' + Date.now() + Math.floor(Math.random() * 1000) },
    expectHttp: 201,
  })
  await api('POST', `/api/v1/orders/${orderId}/confirm-receipt`, { token, expectHttp: 200 })
  const detail = await api('GET', `/api/v1/orders/${orderId}`, { token, expectHttp: 200 })
  return detail.json.data
}

function tinyPng() {
  return Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
  )
}

async function main() {
  execSync('php database/m18_seed.php', { cwd: path.resolve('apps/api'), stdio: 'inherit' })
  const adminToken = await loginAdmin()
  const { token } = await registerMember('m18')

  const pending = await paidOrder(token)
  const deny = await api('POST', '/api/v1/reviews', {
    token,
    body: {
      order_item_id: pending.items[0].id,
      stars: 5,
      body: '尚未完成不應可評',
    },
  })
  check(
    'A1',
    '未完成訂單不可主評',
    deny.status === 422 || deny.json.error?.name === 'INVALID_STATUS',
    deny.json.error?.name || deny.json.message,
  )

  const completed = await completeOrder(token, adminToken, pending.id)
  check(
    'A2',
    '完成後 needs_review / can_review',
    completed.needs_review === true && completed.items[0].can_review === true,
    JSON.stringify({ needs: completed.needs_review, can: completed.items[0].can_review }),
  )

  const toReview = await api('GET', '/api/v1/orders?status=to_review', { token, expectHttp: 200 })
  check(
    'A3',
    'to_review Tab 含未評完成單',
    ok(toReview.json) && (toReview.json.data.items || []).some((o) => o.id === completed.id),
    `count=${(toReview.json.data.items || []).length}`,
  )

  const fd = new FormData()
  fd.append('file', new Blob([tinyPng()], { type: 'image/png' }), 'r.png')
  const up = await api('POST', '/api/v1/uploads/review-image', {
    token,
    formData: fd,
    expectHttp: 201,
  })
  check(
    'A4',
    '評價圖上傳成功',
    ok(up.json) && String(up.json.data.url).includes('/storage/review/'),
    up.json.data?.url,
  )

  const created = await api('POST', '/api/v1/reviews', {
    token,
    body: {
      order_item_id: completed.items[0].id,
      stars: 5,
      body: 'M18 驗收主評：成色滿意',
      images: [up.json.data.url],
    },
    expectHttp: 201,
  })
  check(
    'A5',
    '主評成功',
    ok(created.json) && created.json.data.stars === 5 && created.json.data.images?.length === 1,
    JSON.stringify({ id: created.json.data?.id, stars: created.json.data?.stars }),
  )

  const dup = await api('POST', '/api/v1/reviews', {
    token,
    body: {
      order_item_id: completed.items[0].id,
      stars: 4,
      body: '重複主評',
    },
  })
  check(
    'A6',
    '不可重複主評',
    dup.status === 422 || dup.json.error?.name === 'ALREADY_REVIEWED',
    dup.json.error?.name || dup.json.message,
  )

  const productCode = completed.items[0].product_code
  const product = await api('GET', `/api/v1/products/${productCode}`, { expectHttp: 200 })
  const reviews = product.json.data.reviews || []
  check(
    'A7',
    '商品詳情含剛寫入評價',
    reviews.some((r) => r.id === created.json.data.id && r.stars === 5),
    `reviews=${reviews.length}`,
  )

  const follow = await api('POST', `/api/v1/reviews/${created.json.data.id}/follow`, {
    token,
    body: { body: '戴了一週仍然穩' },
    expectHttp: 200,
  })
  check(
    'A8',
    '追評成功',
    ok(follow.json) && follow.json.data.follow === '戴了一週仍然穩',
    follow.json.data?.follow,
  )

  const follow2 = await api('POST', `/api/v1/reviews/${created.json.data.id}/follow`, {
    token,
    body: { body: '再次追評應拒絕' },
  })
  check(
    'A9',
    '不可二次追評',
    follow2.status === 422 || follow2.json.error?.name === 'ALREADY_FOLLOWED',
    follow2.json.error?.name || follow2.json.message,
  )

  const toReview2 = await api('GET', '/api/v1/orders?status=to_review', { token, expectHttp: 200 })
  check(
    'A10',
    '評完後 to_review 不再含該單',
    !(toReview2.json.data.items || []).some((o) => o.id === completed.id),
    `count=${(toReview2.json.data.items || []).length}`,
  )

  // aftersale filter
  const asOrder = await paidOrder(token)
  const asRes = await api('POST', '/api/v1/aftersales', {
    token,
    body: {
      order_id: asOrder.id,
      order_item_id: asOrder.items[0].id,
      type: 'refund_only',
      refund_to: 'balance',
      description: 'M18 售後 Tab 驗收',
    },
    expectHttp: 201,
  })
  check('A11', '售後申請成功（觸發 aftersale_touched）', ok(asRes.json), asRes.json.data?.aftersale_no)

  const aftersaleList = await api('GET', '/api/v1/orders?status=aftersale', { token, expectHttp: 200 })
  check(
    'A12',
    'aftersale Tab 含觸及售後訂單',
    (aftersaleList.json.data.items || []).some((o) => o.id === asOrder.id),
    `count=${(aftersaleList.json.data.items || []).length}`,
  )

  const big = Buffer.alloc(1024 * 1024 + 10, 1)
  const fdBig = new FormData()
  fdBig.append('file', new Blob([big], { type: 'image/jpeg' }), 'big.jpg')
  const upBig = await api('POST', '/api/v1/uploads/review-image', { token, formData: fdBig })
  check(
    'A13',
    '超過 1MB 評價圖被拒',
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
