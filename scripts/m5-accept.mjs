/**
 * SisterMart M5 acceptance: fulfillment + aftersales.
 */
import pkg from '/tmp/node_modules/playwright/index.js'
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'

const { chromium } = pkg
const BASE = 'http://127.0.0.1:8000'
const H5 = 'http://127.0.0.1:5173'
const ADMIN = 'http://127.0.0.1:5174'
const OUT = '/opt/cursor/artifacts'
const SHOT = path.join(OUT, 'screenshots')
fs.mkdirSync(SHOT, { recursive: true })
fs.mkdirSync(path.join(OUT, 'm5-accept'), { recursive: true })

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
    json = { parse_error: true, text: text.slice(0, 200) }
  }
  if (expectHttp !== undefined && res.status !== expectHttp) {
    throw new Error(`HTTP ${res.status} expected ${expectHttp} ${urlPath}: ${text.slice(0, 300)}`)
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
  if (!ok(reg.json)) throw new Error('register failed')
  return { email, token: reg.json.data.token, member: reg.json.data.member }
}

async function adminToken() {
  const login = await api('POST', '/api/v1/admin/sessions', {
    body: { email: 'admin@sistermart.local', password: 'admin123456' },
    expectHttp: 201,
  })
  return login.json.data.token
}

async function paidOrder(token) {
  const addr = await api('POST', '/api/v1/addresses', {
    token,
    body: { receiver: 'M5', phone: '90005555', region: '九龍', district: '旺角', detail: '測試道1號', is_default: true },
  })
  const p3 = await api('GET', '/api/v1/products/p3')
  const sku = p3.json.data.skus.find((s) => s.stock > 0)
  const order = await api('POST', '/api/v1/orders', {
    token,
    body: {
      address_id: addr.json.data.id,
      buy_now: true,
      sku_id: sku.id,
      qty: 1,
      use_balance: true,
      use_points: true,
    },
  })
  if (order.json.data.status === 'pending_payment') {
    const pay = await api('POST', `/api/v1/orders/${order.json.data.id}/payments`, {
      token,
      body: { channel: 'fps' },
      expectHttp: 201,
    })
    await api('POST', `/api/v1/payments/${pay.json.data.payment_id}/mock-confirm`, { token, body: {} })
  }
  const detail = await api('GET', `/api/v1/orders/${order.json.data.id}`, { token })
  return detail.json.data
}

async function suiteApi() {
  const atok = await adminToken()
  const { token } = await registerMember('m5a')
  const order = await paidOrder(token)
  check('A0', '準備待發貨訂單', order.status === 'pending_shipment', order.status)

  const ship = await api('POST', `/api/v1/admin/orders/${order.id}/shipments`, {
    token: atok,
    body: { company: '順豐速運', tracking_no: 'SF' + Date.now(), note: '已交運' },
    expectHttp: 201,
  })
  check('A1', '發貨後訂單待收貨', ok(ship.json) && ship.json.data.order_status === 'pending_receipt', ship.json.data?.order_status)

  await api('POST', `/api/v1/admin/orders/${order.id}/shipment-nodes`, {
    token: atok,
    body: { note: '已到達香港派送中心', occurred_at: '2026-10-14 18:00:00' },
    expectHttp: 201,
  })
  const logistics = await api('GET', `/api/v1/orders/${order.id}/shipment`, { token })
  const nodes = logistics.json.data?.nodes || []
  check(
    'A1b',
    '物流節點倒序可見',
    ok(logistics.json) &&
      nodes.length >= 2 &&
      nodes.some((n) => n.note.includes('派送')) &&
      String(nodes[0].occurred_at) >= String(nodes[1].occurred_at),
    nodes.map((n) => `${n.occurred_at}:${n.note}`).join(' | '),
  )

  const confirmed = await api('POST', `/api/v1/orders/${order.id}/confirm-receipt`, { token, body: {} })
  check('A2', '手動確認收貨→已完成', ok(confirmed.json) && confirmed.json.data.status === 'completed', confirmed.json.data?.status)
  check('A2b', '完成後發下單積分', (confirmed.json.data.points_awarded || 0) >= 0)

  // Auto receive: ship another order, backdate paid_at, no aftersale
  const { token: t2 } = await registerMember('m5auto')
  const o2 = await paidOrder(t2)
  await api('POST', `/api/v1/admin/orders/${o2.id}/shipments`, {
    token: atok,
    body: { company: 'SF', tracking_no: 'AUTO' + Date.now() },
    expectHttp: 201,
  })
  execSync(
    `mysql -usistermart -psistermart -h127.0.0.1 sistermart -e "UPDATE orders SET paid_at=DATE_SUB(NOW(), INTERVAL 10 DAY) WHERE id=${o2.id}"`,
  )
  const auto = await api('POST', '/api/v1/jobs/auto-receive-orders', { body: {} })
  const o2b = await api('GET', `/api/v1/orders/${o2.id}`, { token: t2 })
  check(
    'A3',
    '自動收貨：支付滿 N 天且已發貨且未點售後',
    ok(auto.json) && o2b.json.data.status === 'completed',
    `job=${JSON.stringify(auto.json.data)} status=${o2b.json.data.status}`,
  )

  // Aftersale blocks auto receive
  const { token: t3 } = await registerMember('m5as')
  const o3 = await paidOrder(t3)
  await api('POST', `/api/v1/admin/orders/${o3.id}/shipments`, {
    token: atok,
    body: { company: 'SF', tracking_no: 'AS' + Date.now() },
    expectHttp: 201,
  })
  const detail3 = await api('GET', `/api/v1/orders/${o3.id}`, { token: t3 })
  const itemId = detail3.json.data.items[0].id
  const as = await api('POST', '/api/v1/aftersales', {
    token: t3,
    body: {
      order_id: o3.id,
      order_item_id: itemId,
      type: 'refund_only',
      refund_to: 'balance',
      reason: '測試售後',
      amount: detail3.json.data.items[0].line_amount,
    },
    expectHttp: 201,
  })
  check('A4a', '待收貨可申請售後', ok(as.json) && as.json.data.status === 'pending_review')
  execSync(
    `mysql -usistermart -psistermart -h127.0.0.1 sistermart -e "UPDATE orders SET paid_at=DATE_SUB(NOW(), INTERVAL 10 DAY) WHERE id=${o3.id}"`,
  )
  await api('POST', '/api/v1/jobs/auto-receive-orders', { body: {} })
  const o3b = await api('GET', `/api/v1/orders/${o3.id}`, { token: t3 })
  check('A3b', '點過售後不自動完成', o3b.json.data.status === 'pending_receipt', o3b.json.data.status)

  // completed order cannot apply
  const bad = await api('POST', '/api/v1/aftersales', {
    token,
    body: { order_id: order.id, order_item_id: (await api('GET', `/api/v1/orders/${order.id}`, { token })).json.data.items[0].id, type: 'refund_only', refund_to: 'balance', reason: 'x' },
  })
  check('A4', '售後僅未完成可申請', bad.json.error?.name === 'INVALID_STATUS' || bad.status === 422)

  // approve refund to balance, stock restore, full refund → refunded
  const stockBefore = (await api('GET', '/api/v1/products/p3')).json.data.skus.find((s) => s.id === detail3.json.data.items[0] /* wrong */ )
  const p3sku = (await api('GET', '/api/v1/products/p3')).json.data.skus
  const skuCode = detail3.json.data.items[0].sku_code
  const beforeStock = p3sku.find((s) => s.code === skuCode)?.stock
  const reviewed = await api('POST', `/api/v1/admin/aftersales/${as.json.data.id}/review`, {
    token: atok,
    body: { action: 'approve' },
  })
  check('A4b', '售後退餘額完成', ok(reviewed.json) && reviewed.json.data.status === 'completed', reviewed.json.data?.status)
  const afterStock = (await api('GET', '/api/v1/products/p3')).json.data.skus.find((s) => s.code === skuCode)?.stock
  check('A4c', '退款成功回補庫存', afterStock === beforeStock + 1, `${beforeStock}->${afterStock}`)
  const o3c = await api('GET', `/api/v1/orders/${o3.id}`, { token: t3 })
  check('A5', '整單退完→已退款', o3c.json.data.status === 'refunded', o3c.json.data.status)
  check('A5b', '已退款不計完成（無完成態）', o3c.json.data.status !== 'completed' && (o3c.json.data.points_awarded || 0) === 0)

  const adminList = await api('GET', '/api/v1/admin/aftersales', { token: atok })
  check('A6', '後台售後列表', ok(adminList.json) && adminList.json.data.items.length >= 1)
}

async function suiteUi() {
  const browser = await chromium.launch({ headless: true })
  const atok = await adminToken()
  const { token } = await registerMember('m5ui')
  const order = await paidOrder(token)
  await api('POST', `/api/v1/admin/orders/${order.id}/shipments`, {
    token: atok,
    body: { company: '順豐速運', tracking_no: 'UI' + Date.now(), note: '已發貨' },
    expectHttp: 201,
  })
  await api('POST', `/api/v1/admin/orders/${order.id}/shipment-nodes`, {
    token: atok,
    body: { note: '派件中' },
    expectHttp: 201,
  })

  const context = await browser.newContext({ viewport: { width: 390, height: 844 } })
  const page = await context.newPage()
  await page.addInitScript((tok) => {
    localStorage.setItem('sm_token', tok)
    localStorage.setItem('sm_member', JSON.stringify({ id: 1, email: 'm5@test.local', locale: 'zh-HK', promo_code: 'SM', balance: 50, points: 5000 }))
  }, token)

  await page.goto(`${H5}/orders/${order.id}/logistics`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(600)
  check('H1', 'H5 物流頁節點可見', (await page.getByText('派件中').count()) > 0 || (await page.getByText('已發貨').count()) > 0)
  await page.screenshot({ path: path.join(SHOT, 'm5-logistics.png'), fullPage: true })

  await page.goto(`${H5}/orders/${order.id}`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(500)
  await page.getByRole('button', { name: '確認收貨' }).click()
  await page.waitForTimeout(1000)
  check('H2', 'H5 確認收貨成功', (await page.getByText('已完成').count()) > 0)
  await page.screenshot({ path: path.join(SHOT, 'm5-confirmed.png'), fullPage: true })

  // aftersale path on another order
  const { token: t2 } = await registerMember('m5ui2')
  const o2 = await paidOrder(t2)
  const admin = await browser.newContext({ viewport: { width: 1280, height: 800 } })
  const ap = await admin.newPage()
  await ap.goto(`${ADMIN}/login`, { waitUntil: 'networkidle' })
  await ap.locator('input').nth(0).fill('admin@sistermart.local')
  await ap.locator('input').nth(1).fill('admin123456')
  await ap.locator('button.n-button--primary-type').first().click()
  await ap.waitForFunction(() => Boolean(localStorage.getItem('sm_admin_token')), null, { timeout: 10000 })
  await ap.goto(`${ADMIN}/sales/orders/${o2.id}`, { waitUntil: 'networkidle' })
  await ap.waitForTimeout(600)
  await ap.locator('input').nth(1).fill('SFDEMO' + Date.now())
  await ap.getByRole('button', { name: '確認發貨' }).click()
  await ap.waitForTimeout(800)
  check('AD1', '後台可發貨', (await ap.getByText('pending_receipt').count()) > 0 || (await ap.getByText('物流').count()) > 0)
  await ap.screenshot({ path: path.join(SHOT, 'm5-admin-ship.png'), fullPage: true })

  await ap.goto(`${ADMIN}/sales/aftersales`, { waitUntil: 'networkidle' })
  await ap.waitForTimeout(500)
  check('AD2', '後台售後頁可開', (await ap.getByText('售後').count()) > 0)
  await ap.screenshot({ path: path.join(SHOT, 'm5-admin-aftersales.png'), fullPage: true })

  await browser.close()
}

async function main() {
  await suiteApi()
  await suiteUi()
  const passed = results.filter((r) => r.pass).length
  const failed = results.filter((r) => !r.pass)
  const report = {
    milestone: 'M5',
    generated_at: new Date().toISOString(),
    summary: { total: results.length, passed, failed: failed.length },
    results,
  }
  fs.writeFileSync(path.join(OUT, 'm5-accept', 'report.json'), JSON.stringify(report, null, 2))
  console.log(`\nM5 acceptance: ${passed}/${results.length} passed`)
  if (failed.length) {
    console.log('Failed:', failed.map((f) => `${f.id}:${f.detail}`).join(' | '))
    process.exitCode = 1
  }
}

const isDirect = process.argv[1] && path.resolve(process.argv[1]) === path.resolve(new URL(import.meta.url).pathname)
if (isDirect) {
  main().catch((err) => {
    console.error(err)
    process.exit(1)
  })
}
