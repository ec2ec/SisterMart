/**
 * SisterMart M23: admin review hide / reply.
 */
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'

const BASE = process.env.API_BASE || 'http://127.0.0.1:8000'
const OUT = '/opt/cursor/artifacts/m23-accept'
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
  return { token: reg.json.data.token }
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
      receiver: 'M23',
      phone: '90002323',
      region: '九龍',
      district: '旺角',
      detail: 'M23街1號',
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
    body: { company: 'SF', tracking_no: 'SFM23' + Date.now() },
    expectHttp: 201,
  })
  await api('POST', `/api/v1/orders/${orderId}/confirm-receipt`, { token, expectHttp: 200 })
  return (await api('GET', `/api/v1/orders/${orderId}`, { token, expectHttp: 200 })).json.data
}

async function main() {
  execSync('php database/m18_seed.php', { cwd: path.resolve('apps/api'), stdio: 'inherit' })
  execSync('php database/m23_seed.php', { cwd: path.resolve('apps/api'), stdio: 'inherit' })
  execSync('php database/m25_seed.php', { cwd: path.resolve('apps/api'), stdio: 'inherit' })

  const adminToken = await loginAdmin()
  const { token } = await registerMember('m23')

  const pending = await paidOrder(token)
  const completed = await completeOrder(token, adminToken, pending.id)
  const productCode = completed.items[0].product_code

  const created = await api('POST', '/api/v1/reviews', {
    token,
    body: {
      order_item_id: completed.items[0].id,
      stars: 5,
      body: 'M23 驗收評價：手感很好',
    },
    expectHttp: 201,
  })
  const reviewId = created.json.data.id
  check('A1', '會員主評成功', ok(created.json) && reviewId, `id=${reviewId}`)

  // M25：新建為 pending，需審核通過後公開可見
  const pdpPending = await api('GET', `/api/v1/products/${productCode}`, { expectHttp: 200 })
  check(
    'A2a',
    '待審時公開 PDP 不可見',
    !(pdpPending.json.data.reviews || []).some((r) => r.id === reviewId),
  )
  await api('POST', `/api/v1/admin/reviews/${reviewId}/moderate`, {
    token: adminToken,
    body: { status: 'approved' },
    expectHttp: 200,
  })
  const pdp1 = await api('GET', `/api/v1/products/${productCode}`, { expectHttp: 200 })
  check(
    'A2',
    '審核通過後 PDP 可見',
    (pdp1.json.data.reviews || []).some((r) => r.id === reviewId),
    `n=${(pdp1.json.data.reviews || []).length}`,
  )

  const adminList = await api('GET', '/api/v1/admin/reviews?is_hidden=0', {
    token: adminToken,
    expectHttp: 200,
  })
  check(
    'A3',
    '後台列表含該評價',
    ok(adminList.json) && (adminList.json.data.items || []).some((r) => r.id === reviewId && r.is_hidden === 0),
  )

  const hidden = await api('POST', `/api/v1/admin/reviews/${reviewId}/visibility`, {
    token: adminToken,
    body: { is_hidden: 1 },
    expectHttp: 200,
  })
  check('A4', '隱藏成功', ok(hidden.json) && hidden.json.data.is_hidden === 1)

  const pdp2 = await api('GET', `/api/v1/products/${productCode}`, { expectHttp: 200 })
  check(
    'A5',
    '隱藏後 PDP 不再展示',
    !(pdp2.json.data.reviews || []).some((r) => r.id === reviewId),
  )

  const hiddenList = await api('GET', '/api/v1/admin/reviews?is_hidden=1', {
    token: adminToken,
    expectHttp: 200,
  })
  check(
    'A6',
    '後台仍可見已隱藏評價',
    (hiddenList.json.data.items || []).some((r) => r.id === reviewId),
  )

  const shown = await api('POST', `/api/v1/admin/reviews/${reviewId}/visibility`, {
    token: adminToken,
    body: { is_hidden: 0 },
    expectHttp: 200,
  })
  check('A7', '恢復顯示', ok(shown.json) && shown.json.data.is_hidden === 0)

  const emptyReply = await api('POST', `/api/v1/admin/reviews/${reviewId}/reply`, {
    token: adminToken,
    body: { reply: '' },
  })
  check(
    'A8',
    '空回覆被拒',
    !ok(emptyReply.json) && (emptyReply.status === 422 || emptyReply.json.code !== 0),
    emptyReply.json.message,
  )

  const replied = await api('POST', `/api/v1/admin/reviews/${reviewId}/reply`, {
    token: adminToken,
    body: { reply: '感謝支持，歡迎再來～' },
    expectHttp: 200,
  })
  check(
    'A9',
    '商家回覆寫入',
    ok(replied.json) && replied.json.data.reply === '感謝支持，歡迎再來～',
    replied.json.data?.reply,
  )

  const pdp3 = await api('GET', `/api/v1/products/${productCode}`, { expectHttp: 200 })
  const pub = (pdp3.json.data.reviews || []).find((r) => r.id === reviewId)
  check(
    'A10',
    'PDP 展示商家回覆',
    pub && pub.reply === '感謝支持，歡迎再來～',
    pub?.reply,
  )

  const noAuth = await api('POST', `/api/v1/admin/reviews/${reviewId}/visibility`, {
    body: { is_hidden: 1 },
  })
  check('A11', '未登錄不可改可見性', noAuth.status === 401 || !ok(noAuth.json), String(noAuth.status))

  const failed = results.filter((r) => !r.pass)
  fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify({ results, failed: failed.length }, null, 2))
  console.log(`\n${results.length - failed.length}/${results.length} passed`)
  if (failed.length) process.exit(1)
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
