/**
 * SisterMart M16 acceptance: commission clawback + real share-view points.
 */
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'

const BASE = process.env.API_BASE || 'http://127.0.0.1:8000'
const OUT = '/opt/cursor/artifacts/m16-accept'
fs.mkdirSync(OUT, { recursive: true })

const results = []
function check(id, title, pass, detail = '') {
  results.push({ id, title, pass: Boolean(pass), detail: String(detail || '') })
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${id}  ${title}${detail ? ' — ' + detail : ''}`)
}

async function api(method, urlPath, { body, token, expectHttp } = {}) {
  const headers = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (token) headers.Authorization = `Bearer ${token}`
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

async function registerMember(prefix, extra = {}) {
  const email = `${prefix}${Date.now()}@test.local`
  const codeRes = await api('POST', '/api/v1/verification-codes', {
    body: { channel: 'email', purpose: 'register', target: email },
  })
  if (!ok(codeRes.json)) throw new Error('verify code failed: ' + JSON.stringify(codeRes.json))
  const code = codeRes.json.data.debug_code
  const reg = await api('POST', '/api/v1/members', {
    body: { channel: 'email', email, password: 'secret123', code, agreed: true, ...extra },
  })
  if (!ok(reg.json) || !reg.json.data?.token) throw new Error('register failed: ' + JSON.stringify(reg.json))
  return { email, token: reg.json.data.token, member: reg.json.data.member, bound: reg.json.data.bound }
}

async function loginAdmin() {
  const res = await api('POST', '/api/v1/admin/sessions', {
    body: { email: 'admin@sistermart.local', password: 'admin123456' },
  })
  if (!ok(res.json) || !res.json.data?.token) throw new Error('admin login failed')
  return res.json.data.token
}

async function ensureAddress(token, tag) {
  const addr = await api('POST', '/api/v1/addresses', {
    token,
    body: {
      receiver: tag,
      phone: '90001616',
      region: '九龍',
      district: '旺角',
      detail: `${tag}街1號`,
      is_default: true,
    },
    expectHttp: 201,
  })
  return addr.json.data.id
}

async function paidOrder(token, addressId) {
  const product = await api('GET', '/api/v1/products/p1', { expectHttp: 200 })
  const sku = (product.json.data.skus || []).find((s) => s.stock > 0)
  if (!sku) throw new Error('no sku stock')
  const orderRes = await api('POST', '/api/v1/orders', {
    token,
    body: { address_id: addressId, buy_now: true, sku_id: sku.id, qty: 1 },
    expectHttp: 201,
  })
  const order = orderRes.json.data
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

function seedDb() {
  execSync('php database/m16_seed.php', {
    cwd: path.resolve('apps/api'),
    stdio: 'inherit',
  })
}

async function main() {
  seedDb()
  const adminToken = await loginAdmin()

  // --- P1: pending void on aftersale before complete ---
  const parent = await registerMember('m16p')
  const child = await registerMember('m16c', { ref: parent.member.promo_code })
  check('A1', '下級綁定上級', child.bound === true || child.member?.parent_id > 0, `bound=${child.bound}`)

  const childAddr = await ensureAddress(child.token, 'M16C')
  const order1 = await paidOrder(child.token, childAddr)
  check('A2', '下級支付成功', order1.status === 'pending_shipment', order1.status)

  const pendingOv = await api('GET', '/api/v1/distribution/overview', { token: parent.token, expectHttp: 200 })
  check(
    'A3',
    '支付後有 pending 佣金',
    ok(pendingOv.json) && pendingOv.json.data.mock === false && pendingOv.json.data.pending_commission > 0,
    JSON.stringify({
      pending: pendingOv.json.data?.pending_commission,
      available: pendingOv.json.data?.available_commission,
    }),
  )

  const item1 = order1.items[0]
  const as1 = await api('POST', '/api/v1/aftersales', {
    token: child.token,
    body: {
      order_id: order1.id,
      order_item_id: item1.id,
      type: 'refund_only',
      refund_to: 'balance',
      reason: 'M16 pending clawback',
      amount: item1.line_amount,
    },
    expectHttp: 201,
  })
  const review1 = await api('POST', `/api/v1/admin/aftersales/${as1.json.data.id}/review`, {
    token: adminToken,
    body: { action: 'approve' },
    expectHttp: 200,
  })
  check('A4', '待發貨售後退款完成', ok(review1.json) && review1.json.data.status === 'completed', review1.json.data?.status)

  const afterVoid = await api('GET', '/api/v1/distribution/commissions', { token: parent.token, expectHttp: 200 })
  const voided = (afterVoid.json.data?.items || []).find((c) => c.order_no === order1.order_no)
  check('A5', 'pending 佣金已 void', voided?.status === 'void', JSON.stringify(voided))

  const afterVoidOv = await api('GET', '/api/v1/distribution/overview', { token: parent.token, expectHttp: 200 })
  check(
    'A6',
    'void 後 available 不增加',
    afterVoidOv.json.data.available_commission === pendingOv.json.data.available_commission,
    `avail=${afterVoidOv.json.data?.available_commission}`,
  )

  // --- P1: settled clawback after complete + return_refund ---
  const order2 = await paidOrder(child.token, childAddr)
  await api('POST', `/api/v1/admin/orders/${order2.id}/shipments`, {
    token: adminToken,
    body: { company: 'SF', tracking_no: 'SFM16' + Date.now(), note: 'M16 ship' },
    expectHttp: 201,
  })
  await api('POST', `/api/v1/orders/${order2.id}/confirm-receipt`, { token: child.token, expectHttp: 200 })

  const settledOv = await api('GET', '/api/v1/distribution/overview', { token: parent.token, expectHttp: 200 })
  const availBefore = settledOv.json.data.available_commission
  check('A7', '完成後佣金已入帳', availBefore > afterVoidOv.json.data.available_commission, `avail=${availBefore}`)

  const detail2 = await api('GET', `/api/v1/orders/${order2.id}`, { token: child.token, expectHttp: 200 })
  const item2 = detail2.json.data.items[0]
  const as2 = await api('POST', '/api/v1/aftersales', {
    token: child.token,
    body: {
      order_id: order2.id,
      order_item_id: item2.id,
      type: 'return_refund',
      refund_to: 'balance',
      reason: 'M16 settled clawback',
      amount: item2.line_amount,
    },
    expectHttp: 201,
  })
  await api('POST', `/api/v1/admin/aftersales/${as2.json.data.id}/review`, {
    token: adminToken,
    body: { action: 'approve' },
    expectHttp: 200,
  })
  const returned = await api('POST', `/api/v1/admin/aftersales/${as2.json.data.id}/mark-returned`, {
    token: adminToken,
    expectHttp: 200,
  })
  check('A8', '已完成單退貨退款完成', ok(returned.json) && returned.json.data.status === 'completed', returned.json.data?.status)

  const clawedList = await api('GET', '/api/v1/distribution/commissions', { token: parent.token, expectHttp: 200 })
  const clawed = (clawedList.json.data?.items || []).find((c) => c.order_no === detail2.json.data.order_no)
  check('A9', '已入帳佣金 clawed_back', clawed?.status === 'clawed_back', JSON.stringify(clawed))

  const afterClawOv = await api('GET', '/api/v1/distribution/overview', { token: parent.token, expectHttp: 200 })
  check(
    'A10',
    '沖回後 available 下降',
    afterClawOv.json.data.available_commission < availBefore,
    `before=${availBefore} after=${afterClawOv.json.data?.available_commission}`,
  )

  // idempotent re-entry: completed aftersale re-enter executeRefund must not double-claw
  const availAfterClaw = afterClawOv.json.data.available_commission
  const again = await api('POST', `/api/v1/admin/aftersales/${as2.json.data.id}/force-balance`, {
    token: adminToken,
    body: { admin_note: 'm16 idempotent' },
    expectHttp: 200,
  })
  const againOv = await api('GET', '/api/v1/distribution/overview', { token: parent.token, expectHttp: 200 })
  check(
    'A11',
    '同一售後重入不重複沖回',
    ok(again.json) && again.json.data.status === 'completed' && againOv.json.data.available_commission === availAfterClaw,
    `avail=${againOv.json.data?.available_commission}`,
  )

  // --- P2 share views ---
  const share1 = await api('POST', '/api/v1/share-views', {
    body: { ref: parent.member.promo_code, product_id: 'p1' },
    expectHttp: 201,
  })
  check(
    'A12',
    '分享瀏覽 mock:false 且計分',
    ok(share1.json) && share1.json.data.mock === false && share1.json.data.credited === true,
    JSON.stringify(share1.json.data),
  )

  const share2 = await api('POST', '/api/v1/share-views', {
    body: { ref: parent.member.promo_code, product_id: 'p1' },
    expectHttp: 201,
  })
  check(
    'A13',
    '同 IP 重複不计分',
    ok(share2.json) && share2.json.data.mock === false && share2.json.data.credited === false && share2.json.data.reason === 'duplicate',
    JSON.stringify(share2.json.data),
  )

  const selfShare = await api('POST', '/api/v1/share-views', {
    token: parent.token,
    body: { ref: parent.member.promo_code, product_id: 'p1' },
    expectHttp: 201,
  })
  check(
    'A14',
    '自看不计分',
    ok(selfShare.json) && selfShare.json.data.credited === false && selfShare.json.data.reason === 'self',
    JSON.stringify(selfShare.json.data),
  )

  const badRef = await api('POST', '/api/v1/share-views', {
    body: { ref: 'NOTEXIST', product_id: 'p1' },
    expectHttp: 201,
  })
  check(
    'A15',
    '無效 ref 不计分',
    ok(badRef.json) && badRef.json.data.credited === false && badRef.json.data.reason === 'unknown_ref',
    JSON.stringify(badRef.json.data),
  )

  // regression
  const msgs = await api('GET', '/api/v1/messages', { token: parent.token, expectHttp: 200 })
  check('A16', '消息回歸 mock:false', ok(msgs.json) && msgs.json.data?.mock === false)

  const checkin = await api('POST', '/api/v1/checkins', { token: parent.token, expectHttp: 201 })
  check('A17', '簽到回歸 mock:false', ok(checkin.json) && checkin.json.data?.mock === false, JSON.stringify(checkin.json.data))

  const failed = results.filter((r) => !r.pass)
  fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify({ results, failed: failed.length }, null, 2))
  console.log(`\n${results.length - failed.length}/${results.length} passed`)
  if (failed.length) process.exit(1)
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
