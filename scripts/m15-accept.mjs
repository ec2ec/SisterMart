/**
 * SisterMart M15 acceptance: real inbox messages.
 */
import fs from 'node:fs'
import path from 'node:path'

const BASE = process.env.API_BASE || 'http://127.0.0.1:8000'
const OUT = '/opt/cursor/artifacts/m15-accept'
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

async function registerMember(prefix) {
  const email = `${prefix}${Date.now()}@test.local`
  const codeRes = await api('POST', '/api/v1/verification-codes', {
    body: { channel: 'email', purpose: 'register', target: email },
  })
  if (!ok(codeRes.json)) throw new Error('verify code failed: ' + JSON.stringify(codeRes.json))
  const code = codeRes.json.data.debug_code
  const reg = await api('POST', '/api/v1/members', {
    body: { channel: 'email', email, password: 'secret123', code, agreed: true },
  })
  if (!ok(reg.json) || !reg.json.data?.token) throw new Error('register failed: ' + JSON.stringify(reg.json))
  return { email, token: reg.json.data.token, member: reg.json.data.member }
}

async function loginAdmin() {
  const res = await api('POST', '/api/v1/admin/sessions', {
    body: { email: 'admin@sistermart.local', password: 'admin123456' },
  })
  if (!ok(res.json) || !res.json.data?.token) throw new Error('admin login failed')
  return res.json.data.token
}

async function main() {
  const guest = await api('GET', '/api/v1/messages', { expectHttp: 401 })
  check('A1', '未登錄消息接口 401', guest.json.error?.name === 'UNAUTHORIZED')

  const { token, member } = await registerMember('m15')
  const adminToken = await loginAdmin()

  const seedWelcome = await api('GET', '/api/v1/messages', { token, expectHttp: 200 })
  check(
    'A2',
    '列表 mock:false（新會員無 seed 亦可）',
    ok(seedWelcome.json) && seedWelcome.json.data?.mock === false,
    `mock=${seedWelcome.json.data?.mock}`,
  )

  const broadcast = await api('POST', '/api/v1/admin/messages', {
    token: adminToken,
    body: {
      title_zh: 'M15 驗收廣播',
      title_en: 'M15 broadcast',
      body_zh: '系統消息真實化驗收',
      body_en: 'Inbox real acceptance',
      member_id: member.id,
    },
    expectHttp: 201,
  })
  check('A3', '後台可發系統消息', ok(broadcast.json) && broadcast.json.data?.sent === 1, JSON.stringify(broadcast.json.data))

  const afterSys = await api('GET', '/api/v1/messages', { token, expectHttp: 200 })
  const sysItems = afterSys.json.data?.system || []
  check(
    'A4',
    '系統消息可見',
    sysItems.some((m) => (m.title?.['zh-HK'] || '').includes('M15')),
    `n=${sysItems.length}`,
  )
  const unread1 = afterSys.json.data?.unread ?? -1
  check('A5', '未讀數 > 0', unread1 > 0, `unread=${unread1}`)

  const addr = await api('POST', '/api/v1/addresses', {
    token,
    body: {
      receiver: 'M15',
      phone: '90001515',
      region: '九龍',
      district: '旺角',
      detail: '山東街15號',
      is_default: true,
    },
    expectHttp: 201,
  })
  const addressId = addr.json.data.id
  const product = await api('GET', '/api/v1/products/p1', { expectHttp: 200 })
  const sku = (product.json.data.skus || []).find((s) => s.stock > 0)
  if (!sku) throw new Error('no sku stock')
  await api('POST', '/api/v1/cart/items', { token, body: { sku_id: sku.id, qty: 1 }, expectHttp: 201 })
  const orderRes = await api('POST', '/api/v1/orders', {
    token,
    body: { address_id: addressId },
    expectHttp: 201,
  })
  const order = orderRes.json.data
  check('A6', '下單成功', ok(orderRes.json) && order?.id > 0, `status=${order?.status}`)

  const afterCreate = await api('GET', '/api/v1/messages', { token, expectHttp: 200 })
  const orderMsgs1 = afterCreate.json.data?.order || []
  check(
    'A7',
    '下單產生訂單消息',
    orderMsgs1.some((m) => m.order_id === order.id && (m.title?.['zh-HK'] || '').includes('下單')),
    orderMsgs1.map((m) => m.title?.['zh-HK']).join('|'),
  )

  const pay = await api('POST', `/api/v1/orders/${order.id}/payments`, {
    token,
    body: { channel: 'fps' },
    expectHttp: 201,
  })
  const paymentId = pay.json.data?.payment_id
  const confirm = await api('POST', `/api/v1/payments/${paymentId}/mock-confirm`, {
    token,
    expectHttp: 200,
  })
  check('A8', 'mock 支付成功', ok(confirm.json) && confirm.json.data?.status === 'pending_shipment')

  const afterPay = await api('GET', '/api/v1/messages', { token, expectHttp: 200 })
  const orderMsgs2 = afterPay.json.data?.order || []
  check(
    'A9',
    '支付產生訂單消息',
    orderMsgs2.some((m) => m.order_id === order.id && (m.title?.['zh-HK'] || '').includes('支付')),
    orderMsgs2.map((m) => m.title?.['zh-HK']).slice(0, 5).join('|'),
  )
  check('A10', '列表仍 mock:false', afterPay.json.data?.mock === false)

  const ship = await api('POST', `/api/v1/admin/orders/${order.id}/shipments`, {
    token: adminToken,
    body: { company: 'SF', tracking_no: 'SFM15' + Date.now(), note: 'M15 ship' },
    expectHttp: 201,
  })
  check('A11', '後台發貨', ok(ship.json))

  const afterShip = await api('GET', '/api/v1/messages/unread-count', { token, expectHttp: 200 })
  const unreadShip = afterShip.json.data?.unread ?? 0
  check('A12', '發貨後未讀增加且 mock:false', afterShip.json.data?.mock === false && unreadShip > 0, `unread=${unreadShip}`)

  const shipMsg = (await api('GET', '/api/v1/messages', { token })).json.data.order.find(
    (m) => m.order_id === order.id && (m.title?.['zh-HK'] || '').includes('發貨'),
  )
  check('A13', '發貨消息存在', Boolean(shipMsg), shipMsg?.id)

  const read = await api('POST', '/api/v1/messages/read', {
    token,
    body: { id: shipMsg.id },
    expectHttp: 200,
  })
  check(
    'A14',
    '標記已讀後 unread 下降',
    ok(read.json) && read.json.data.unread === unreadShip - 1,
    `before=${unreadShip} after=${read.json.data?.unread}`,
  )

  await api('POST', `/api/v1/orders/${order.id}/confirm-receipt`, { token, expectHttp: 200 })
  const afterDone = await api('GET', '/api/v1/messages', { token, expectHttp: 200 })
  check(
    'A15',
    '確認收貨產生完成消息',
    (afterDone.json.data?.order || []).some(
      (m) => m.order_id === order.id && (m.title?.['zh-HK'] || '').includes('完成'),
    ),
  )

  // cross-member isolation
  const other = await registerMember('m15b')
  const otherList = await api('GET', '/api/v1/messages', { token: other.token, expectHttp: 200 })
  const leaked = (otherList.json.data?.order || []).some((m) => m.order_id === order.id)
  check('A16', '跨會員看不到他人訂單消息', !leaked)

  // checkin still works
  const checkin = await api('POST', '/api/v1/checkins', { token, expectHttp: 201 })
  check('A17', '簽到回歸 mock:false', ok(checkin.json) && checkin.json.data?.mock === false, JSON.stringify(checkin.json.data))

  const adminList = await api('GET', '/api/v1/admin/messages', { token: adminToken, expectHttp: 200 })
  check('A18', '後台消息批次列表', ok(adminList.json) && Array.isArray(adminList.json.data?.items))

  const failed = results.filter((r) => !r.pass)
  fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify({ results, failed: failed.length }, null, 2))
  console.log(`\n${results.length - failed.length}/${results.length} passed`)
  if (failed.length) process.exit(1)
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
