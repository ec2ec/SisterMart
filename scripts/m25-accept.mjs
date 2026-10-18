/**
 * SisterMart M25: review moderation — author always sees; others only after approve.
 */
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'

const BASE = process.env.API_BASE || 'http://127.0.0.1:8000'
const OUT = '/opt/cursor/artifacts/m25-accept'
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
      receiver: 'M25',
      phone: '90002525',
      region: '九龍',
      district: '旺角',
      detail: 'M25街1號',
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
    body: { company: 'SF', tracking_no: 'SFM25' + Date.now() },
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
  const author = await registerMember('m25a')
  const other = await registerMember('m25b')

  const pending = await paidOrder(author.token)
  const completed = await completeOrder(author.token, adminToken, pending.id)
  const productCode = completed.items[0].product_code

  const created = await api('POST', '/api/v1/reviews', {
    token: author.token,
    body: {
      order_item_id: completed.items[0].id,
      stars: 5,
      body: 'M25 待審評價：本人可見測試',
    },
    expectHttp: 201,
  })
  const reviewId = created.json.data.id
  check(
    'A1',
    '主評成功且為 pending',
    ok(created.json) && created.json.data.moderation_status === 'pending',
    created.json.data?.moderation_status,
  )

  const pdpPublic = await api('GET', `/api/v1/products/${productCode}`, { expectHttp: 200 })
  check(
    'A2',
    '他人（未登錄）PDP 看不到待審評價',
    !(pdpPublic.json.data.reviews || []).some((r) => r.id === reviewId),
    `n=${(pdpPublic.json.data.reviews || []).length}`,
  )

  const pdpAuthor = await api('GET', `/api/v1/products/${productCode}`, {
    token: author.token,
    expectHttp: 200,
  })
  const mine = (pdpAuthor.json.data.reviews || []).find((r) => r.id === reviewId)
  check(
    'A3',
    '本人帶 token 可見待審評價',
    mine && mine.moderation_status === 'pending' && mine.is_mine === true,
    mine ? mine.moderation_status : 'missing',
  )

  const pdpOther = await api('GET', `/api/v1/products/${productCode}`, {
    token: other.token,
    expectHttp: 200,
  })
  check(
    'A4',
    '其他會員看不到待審評價',
    !(pdpOther.json.data.reviews || []).some((r) => r.id === reviewId),
  )

  const pendingList = await api('GET', '/api/v1/admin/reviews?moderation_status=pending', {
    token: adminToken,
    expectHttp: 200,
  })
  check(
    'A5',
    '後台待審列表含該評價',
    (pendingList.json.data.items || []).some((r) => r.id === reviewId),
  )

  const approved = await api('POST', `/api/v1/admin/reviews/${reviewId}/moderate`, {
    token: adminToken,
    body: { status: 'approved' },
    expectHttp: 200,
  })
  check(
    'A6',
    '審核通過',
    ok(approved.json) && approved.json.data.moderation_status === 'approved',
  )

  const pdpAfter = await api('GET', `/api/v1/products/${productCode}`, { expectHttp: 200 })
  check(
    'A7',
    '通過後公開 PDP 可見',
    (pdpAfter.json.data.reviews || []).some((r) => r.id === reviewId),
  )

  const follow = await api('POST', `/api/v1/reviews/${reviewId}/follow`, {
    token: author.token,
    body: { body: '追評後應重新待審' },
    expectHttp: 200,
  })
  check(
    'A8',
    '追評後重回 pending',
    ok(follow.json) && follow.json.data.moderation_status === 'pending',
    follow.json.data?.moderation_status,
  )

  const pdpFollow = await api('GET', `/api/v1/products/${productCode}`, { expectHttp: 200 })
  check(
    'A9',
    '追評待審時他人不可見',
    !(pdpFollow.json.data.reviews || []).some((r) => r.id === reviewId),
  )

  const pdpAuthor2 = await api('GET', `/api/v1/products/${productCode}`, {
    token: author.token,
    expectHttp: 200,
  })
  check(
    'A10',
    '本人仍可見含追評內容',
    (pdpAuthor2.json.data.reviews || []).some(
      (r) => r.id === reviewId && r.follow === '追評後應重新待審',
    ),
  )

  await api('POST', `/api/v1/admin/reviews/${reviewId}/moderate`, {
    token: adminToken,
    body: { status: 'approved' },
    expectHttp: 200,
  })

  const rejected = await api('POST', `/api/v1/admin/reviews/${reviewId}/moderate`, {
    token: adminToken,
    body: { status: 'rejected' },
    expectHttp: 200,
  })
  check(
    'A11',
    '拒絕成功',
    ok(rejected.json) && rejected.json.data.moderation_status === 'rejected',
  )

  const pdpRejected = await api('GET', `/api/v1/products/${productCode}`, { expectHttp: 200 })
  check(
    'A12',
    '拒絕後他人不可見',
    !(pdpRejected.json.data.reviews || []).some((r) => r.id === reviewId),
  )

  const pdpAuthor3 = await api('GET', `/api/v1/products/${productCode}`, {
    token: author.token,
    expectHttp: 200,
  })
  check(
    'A13',
    '拒絕後本人仍可見',
    (pdpAuthor3.json.data.reviews || []).some(
      (r) => r.id === reviewId && r.moderation_status === 'rejected',
    ),
  )

  const bad = await api('POST', `/api/v1/admin/reviews/${reviewId}/moderate`, {
    token: adminToken,
    body: { status: 'pending' },
  })
  check(
    'A14',
    '不可直接設為 pending',
    !ok(bad.json) && (bad.status === 422 || bad.json.code !== 0),
  )

  const noAuth = await api('POST', `/api/v1/admin/reviews/${reviewId}/moderate`, {
    body: { status: 'approved' },
  })
  check('A15', '未登錄不可審核', noAuth.status === 401 || !ok(noAuth.json), String(noAuth.status))

  const failed = results.filter((r) => !r.pass)
  fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify({ results, failed: failed.length }, null, 2))
  console.log(`\n${results.length - failed.length}/${results.length} passed`)
  if (failed.length) process.exit(1)
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
