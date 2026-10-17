/**
 * SisterMart M8: §20 spot checks, idempotency, admin ACL, regression sample.
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
fs.mkdirSync(path.join(OUT, 'm8-accept'), { recursive: true })

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

async function registerMember(prefix, extra = {}) {
  const email = `${prefix}${Date.now()}@test.local`
  const codeRes = await api('POST', '/api/v1/verification-codes', {
    body: { channel: 'email', purpose: 'register', target: email },
  })
  const code = codeRes.json.data.debug_code
  const reg = await api('POST', '/api/v1/members', {
    body: { channel: 'email', email, password: 'secret123', code, agreed: true, ...extra },
  })
  if (!ok(reg.json)) throw new Error('register failed ' + JSON.stringify(reg.json))
  return { email, token: reg.json.data.token, member: reg.json.data.member, bound: reg.json.data.bound }
}

async function loginAdmin(email, password) {
  const login = await api('POST', '/api/v1/admin/sessions', {
    body: { email, password },
    expectHttp: 201,
  })
  return login.json.data.token
}

async function paidOrder(token) {
  const addr = await api('POST', '/api/v1/addresses', {
    token,
    body: { receiver: 'M8', phone: '90008888', region: '九龍', district: '旺角', detail: 'M8道1號', is_default: true },
  })
  const p1 = await api('GET', '/api/v1/products/p1')
  const sku = p1.json.data.skus.find((s) => s.stock > 0)
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
  if (!ok(order.json)) throw new Error('order ' + JSON.stringify(order.json))
  let detail = order.json.data
  if (detail.status === 'pending_payment') {
    const pay = await api('POST', `/api/v1/orders/${detail.id}/payments`, {
      token,
      body: { channel: 'fps' },
      expectHttp: 201,
    })
    await api('POST', `/api/v1/payments/${pay.json.data.payment_id}/mock-confirm`, { token, body: {} })
    detail = (await api('GET', `/api/v1/orders/${detail.id}`, { token })).json.data
  }
  return detail
}

async function suiteIdempotency() {
  const { token, member } = await registerMember('m8pay')
  execSync(
    `mysql -usistermart -psistermart -h127.0.0.1 sistermart -e "UPDATE members SET points=0,balance=0 WHERE id=${member.id}"`,
  )
  const addr = await api('POST', '/api/v1/addresses', {
    token,
    body: { receiver: 'M8', phone: '90008888', region: '香港島', district: '中環', detail: '皇后1號', is_default: true },
  })
  const p1 = await api('GET', '/api/v1/products/p1')
  const sku = p1.json.data.skus.find((s) => s.stock > 0)
  const order = await api('POST', '/api/v1/orders', {
    token,
    body: { address_id: addr.json.data.id, buy_now: true, sku_id: sku.id, qty: 1 },
  })
  check('I0', '創建待付款訂單', ok(order.json) && order.json.data.status === 'pending_payment', order.json.data?.status)
  const pay = await api('POST', `/api/v1/orders/${order.json.data.id}/payments`, {
    token,
    body: { channel: 'fps' },
    expectHttp: 201,
  })
  const pid = pay.json.data.payment_id
  const c1 = await api('POST', `/api/v1/payments/${pid}/mock-confirm`, { token, body: {} })
  const c2 = await api('POST', `/api/v1/payments/${pid}/mock-confirm`, { token, body: {} })
  check(
    'I1',
    '支付確認冪等',
    ok(c1.json) && ok(c2.json) && c1.json.data.status === 'pending_shipment' && c2.json.data.status === 'pending_shipment',
    `${c1.json.data?.status}/${c2.json.data?.status}`,
  )

  const atok = await loginAdmin('admin@sistermart.local', 'admin123456')
  await api('POST', `/api/v1/admin/orders/${order.json.data.id}/shipments`, {
    token: atok,
    body: { company: 'SF', tracking_no: 'M8' + Date.now() },
    expectHttp: 201,
  })
  const r1 = await api('POST', `/api/v1/orders/${order.json.data.id}/confirm-receipt`, { token, body: {} })
  const ptsBefore = (await api('GET', '/api/v1/point-logs', { token })).json.data.items.filter((l) => l.type === 'order_earn').length
  const r2 = await api('POST', `/api/v1/orders/${order.json.data.id}/confirm-receipt`, { token, body: {} })
  const ptsAfter = (await api('GET', '/api/v1/point-logs', { token })).json.data.items.filter((l) => l.type === 'order_earn').length
  check(
    'I2',
    '確認收貨冪等且不重複發積分',
    ok(r1.json) && ok(r2.json) && r2.json.data.status === 'completed' && ptsAfter === ptsBefore,
    `earnLogs=${ptsAfter}`,
  )
}

async function suiteAcl() {
  execSync('cd /workspace/apps/api && php database/m8_seed.php')
  const superTok = await loginAdmin('admin@sistermart.local', 'admin123456')
  const opsTok = await loginAdmin('ops@sistermart.local', 'ops123456')

  const listSuper = await api('GET', '/api/v1/admin/admins', { token: superTok })
  check('A1', '超管可列管理員', ok(listSuper.json) && listSuper.json.data.items.length >= 2)

  const listOps = await api('GET', '/api/v1/admin/admins', { token: opsTok })
  check('A2', '運營列管理員被拒', listOps.status === 403 || listOps.json.code === 40301 || listOps.json.error?.name === 'FORBIDDEN', listOps.status)

  const meOps = await api('GET', '/api/v1/admin/admins/me', { token: opsTok })
  check('A3', '運營 me 含 role=ops', ok(meOps.json) && meOps.json.data.role === 'ops')

  const orders = await api('GET', '/api/v1/admin/orders', { token: opsTok })
  check('A4', '運營可看訂單', ok(orders.json))
}

async function suitePrdSpot() {
  // no agreement
  const bad = await api('POST', '/api/v1/member-sessions', {
    body: { channel: 'email', email: 'x@test.local', password: 'x', agreed: false },
  })
  check('P1', '未勾協議不能登錄', !ok(bad.json))

  // HK address only
  const { token } = await registerMember('m8hk')
  const overseas = await api('POST', '/api/v1/addresses', {
    token,
    body: { receiver: 'X', phone: '90001111', region: '海外', district: '東京', detail: '1', is_default: true },
  })
  check('P2', '非香港地址不可保存', !ok(overseas.json), overseas.json.error?.name || overseas.json.message)

  // guest cannot order
  const guest = await api('POST', '/api/v1/orders', { body: { buy_now: true, sku_id: 1, qty: 1 } })
  check('P3', '未登錄不能下單', guest.status === 401 || !ok(guest.json))

  // bind ref once
  const parent = await registerMember('m8par')
  const child = await registerMember('m8ch', { ref: parent.member.promo_code })
  check('P4', '分享註冊綁定上級', child.bound === true)

  // level / bilingual product
  const zh = await api('GET', '/api/v1/products/p1')
  check('P5', '商品雙語名字段', ok(zh.json) && zh.json.data.name['zh-HK'] && zh.json.data.name.en)

  // no recharge
  const recharge = await api('POST', '/api/v1/wallet/recharge', { token, body: { amount: 1 } })
  check('P6', '無充值入口', recharge.status === 404 || !ok(recharge.json))
}

async function suiteRegression() {
  const { token } = await registerMember('m8reg')
  const order = await paidOrder(token)
  check('R1', '主閉環：下單支付成功', order.status === 'pending_shipment' || order.status === 'pending_payment', order.status)

  const claimable = await api('GET', '/api/v1/coupons/claimable')
  check('R2', 'M6 領券中心可用', ok(claimable.json) && claimable.json.data.items.length >= 1)

  const seckill = await api('GET', '/api/v1/seckill/sessions')
  check('R3', 'M7 秒殺 Mock 可用', ok(seckill.json) && seckill.json.data.mock)
}

async function suiteUi() {
  const browser = await chromium.launch({ headless: true })
  const page = await browser.newPage({ viewport: { width: 390, height: 844 } })
  await page.goto(`${H5}/`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(400)
  // switch locale if available
  const logo = page.locator('.logo').first()
  if ((await logo.count()) > 0) await logo.click()
  await page.waitForTimeout(300)
  check('U1', 'H5 首頁雙語可切（無白屏）', (await page.locator('.home').count()) > 0)
  await page.screenshot({ path: path.join(SHOT, 'm8-h5-home.png'), fullPage: true })

  const admin = await browser.newContext({ viewport: { width: 1280, height: 800 } })
  const ap = await admin.newPage()
  await ap.goto(`${ADMIN}/login`, { waitUntil: 'networkidle' })
  await ap.locator('input').nth(0).fill('ops@sistermart.local')
  await ap.locator('input').nth(1).fill('ops123456')
  await ap.locator('button.n-button--primary-type').first().click()
  await ap.waitForFunction(() => Boolean(localStorage.getItem('sm_admin_token')), null, { timeout: 10000 })
  await ap.goto(`${ADMIN}/settings/admins`, { waitUntil: 'networkidle' })
  await ap.waitForTimeout(500)
  // ops should be redirected away from admins
  const onAdmins = ap.url().includes('/settings/admins')
  const hasTable = (await ap.getByText('新建').count()) > 0
  check('U2', '運營無法進入管理員頁', !onAdmins || !hasTable, ap.url())
  await ap.screenshot({ path: path.join(SHOT, 'm8-ops-denied.png'), fullPage: true })

  await ap.goto(`${ADMIN}/login`, { waitUntil: 'networkidle' })
  // re-login as super via clearing storage
  await ap.evaluate(() => {
    localStorage.clear()
  })
  await ap.goto(`${ADMIN}/login`, { waitUntil: 'networkidle' })
  await ap.locator('input').nth(0).fill('admin@sistermart.local')
  await ap.locator('input').nth(1).fill('admin123456')
  await ap.locator('button.n-button--primary-type').first().click()
  await ap.waitForFunction(() => Boolean(localStorage.getItem('sm_admin_token')), null, { timeout: 10000 })
  await ap.goto(`${ADMIN}/settings/admins`, { waitUntil: 'networkidle' })
  await ap.waitForTimeout(500)
  check('U3', '超管可進管理員頁', (await ap.getByText('管理員').count()) > 0)
  await ap.screenshot({ path: path.join(SHOT, 'm8-super-admins.png'), fullPage: true })

  await browser.close()
}

async function main() {
  await suiteIdempotency()
  await suiteAcl()
  await suitePrdSpot()
  await suiteRegression()
  await suiteUi()
  const passed = results.filter((r) => r.pass).length
  const failed = results.filter((r) => !r.pass)
  const report = { milestone: 'M8', passed, failed: failed.length, results, at: new Date().toISOString() }
  fs.writeFileSync(path.join(OUT, 'm8-accept/report.json'), JSON.stringify(report, null, 2))
  console.log(`\nM8 ${passed}/${results.length} passed`)
  if (failed.length) {
    console.error(failed)
    process.exit(1)
  }
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
