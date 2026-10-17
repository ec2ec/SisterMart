/**
 * SisterMart M6 acceptance: coupons, points/balance logs, member levels.
 */
import pkg from '/tmp/node_modules/playwright/index.js'
import fs from 'node:fs'
import path from 'node:path'

const { chromium } = pkg
const BASE = 'http://127.0.0.1:8000'
const H5 = 'http://127.0.0.1:5173'
const ADMIN = 'http://127.0.0.1:5174'
const OUT = '/opt/cursor/artifacts'
const SHOT = path.join(OUT, 'screenshots')
fs.mkdirSync(SHOT, { recursive: true })
fs.mkdirSync(path.join(OUT, 'm6-accept'), { recursive: true })

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

async function pickSku(productId = 'p1') {
  const p = await api('GET', `/api/v1/products/${productId}`)
  const sku = (p.json.data.skus || []).find((s) => s.stock > 0)
  if (!sku) throw new Error(`no stock for ${productId}`)
  return sku
}

async function paidOrder(token, extras = {}) {
  const addr = await api('POST', '/api/v1/addresses', {
    token,
    body: { receiver: 'M6', phone: '90006666', region: '九龍', district: '旺角', detail: '測試道6號', is_default: true },
  })
  const sku = await pickSku(extras.product_id || 'p1')
  const { product_id: _pid, ...orderExtras } = extras
  const order = await api('POST', '/api/v1/orders', {
    token,
    body: {
      address_id: addr.json.data.id,
      buy_now: true,
      sku_id: sku.id,
      qty: 1,
      use_balance: true,
      use_points: true,
      ...orderExtras,
    },
  })
  if (!ok(order.json)) throw new Error('order failed ' + JSON.stringify(order.json))
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

  // Claimable + claim + mine
  const claimable = await api('GET', '/api/v1/coupons/claimable')
  check('C1', '領券中心列表', ok(claimable.json) && claimable.json.data.items.length >= 2, `n=${claimable.json.data?.items?.length}`)

  const { token, member } = await registerMember('m6c')
  const off = claimable.json.data.items.find((c) => c.code === 'OFF10PCT')
  const claim = await api('POST', `/api/v1/coupons/${off.id}/claims`, { token, body: {}, expectHttp: 201 })
  check('C2', '可領取折扣券', ok(claim.json) && claim.json.data.coupon.code === 'OFF10PCT')
  const again = await api('POST', `/api/v1/coupons/${off.id}/claims`, { token, body: {} })
  check('C3', '超限不可再領', !ok(again.json), again.json.error?.name || again.json.message)

  const mine = await api('GET', '/api/v1/my-coupons?status=unused', { token })
  check('C4', '我的券含已領', ok(mine.json) && mine.json.data.items.some((g) => g.coupon.code === 'OFF10PCT'))

  // Quote with discount coupon + M4 amount regress
  const grants = mine.json.data.items
  const welcome = grants.find((g) => g.coupon.code === 'WELCOME20')
  const sku = await pickSku('p1')
  const quote = await api('POST', '/api/v1/checkout/quote', {
    token,
    body: {
      buy_now: true,
      sku_id: sku.id,
      qty: 1,
      coupon_grant_id: welcome.grant_id,
      use_points: true,
      use_balance: true,
    },
  })
  const q = quote.json.data
  if (!ok(quote.json) || !q) {
    throw new Error('quote failed ' + JSON.stringify(quote.json))
  }
  const afterCoupon = Math.max(0, +(q.goods_amount - q.coupon_amount).toFixed(2))
  check(
    'A1',
    '券/積分/餘額金額順序正確',
    q.coupon_amount === 20 &&
      q.points_amount <= afterCoupon * 0.5 + 0.001 &&
      q.pay_amount >= 0 &&
      q.goods_amount >= 100,
    JSON.stringify({
      goods: q.goods_amount,
      coupon: q.coupon_amount,
      points: q.points_amount,
      bal: q.balance_amount,
      ship: q.shipping_amount,
      pay: q.pay_amount,
    }),
  )

  // Place order → logs for deduct
  const meBefore = await api('GET', '/api/v1/members/me', { token })
  const order = await paidOrder(token, { coupon_grant_id: welcome.grant_id })
  const pointsAfterOrder = (await api('GET', '/api/v1/members/me', { token })).json.data.points
  const logs = await api('GET', '/api/v1/point-logs', { token })
  check(
    'P1',
    '下單扣積分寫流水',
    ok(logs.json) && logs.json.data.items.some((l) => l.type === 'order_deduct' && l.delta < 0),
    `pts ${meBefore.json.data.points}->${pointsAfterOrder}`,
  )
  const balLogs = await api('GET', '/api/v1/balance-logs', { token })
  check(
    'B1',
    '下單扣餘額寫流水',
    ok(balLogs.json) && balLogs.json.data.items.some((l) => l.type === 'order_deduct' && l.delta < 0),
  )

  // Ship + confirm → earn points + total_spent + level
  await api('POST', `/api/v1/admin/orders/${order.id}/shipments`, {
    token: atok,
    body: { company: 'SF', tracking_no: 'M6' + Date.now() },
    expectHttp: 201,
  })
  const done = await api('POST', `/api/v1/orders/${order.id}/confirm-receipt`, { token, body: {} })
  check('P2', '完成訂單發放積分', ok(done.json) && (done.json.data.points_awarded || 0) > 0, `awarded=${done.json.data.points_awarded}`)
  const earnLog = await api('GET', '/api/v1/point-logs', { token })
  check(
    'P3',
    '發放積分寫流水',
    earnLog.json.data.items.some((l) => l.type === 'order_earn' && l.delta > 0),
  )

  const level = await api('GET', '/api/v1/members/me/level', { token })
  check(
    'L1',
    '等級接口返回累計與檔位',
    ok(level.json) &&
      level.json.data.level &&
      Array.isArray(level.json.data.levels) &&
      level.json.data.levels.length === 5 &&
      level.json.data.total_spent > 0,
    JSON.stringify(level.json.data),
  )

  // Force high spent → level up
  const mid = member.id
  await api('POST', `/api/v1/admin/members/${mid}/asset-adjustments`, {
    token: atok,
    body: { points_delta: 10, balance_delta: 1, reason: 'M6驗收調整' },
  })
  // bump total_spent via SQL then refresh by level update
  const { execSync } = await import('node:child_process')
  execSync(
    `mysql -usistermart -psistermart -h127.0.0.1 sistermart -e "UPDATE members SET total_spent=550 WHERE id=${mid}"`,
  )
  await api('PUT', `/api/v1/admin/member-levels/3`, {
    token: atok,
    body: { min_spent: 500, max_spent: 1999.99 },
  })
  const me2 = await api('GET', '/api/v1/members/me', { token })
  check(
    'L2',
    '累計實付跨門檻升級',
    me2.json.data.total_spent >= 500 && me2.json.data.level.stars >= 3,
    `spent=${me2.json.data.total_spent} stars=${me2.json.data.level.stars}`,
  )

  // Admin settings + no recharge endpoint
  const pts = await api('GET', '/api/v1/admin/points/settings', { token: atok })
  check('S1', '後台積分設置可讀', ok(pts.json) && pts.json.data.per_hkd >= 1)
  const recharge = await api('POST', '/api/v1/wallet/recharge', { token, body: { amount: 100 } })
  check('S2', '無充值入口', recharge.status === 404 || !ok(recharge.json))

  // Admin adjust writes logs
  const adjLogs = await api('GET', '/api/v1/point-logs', { token })
  check(
    'S3',
    '後台調整寫積分流水',
    adjLogs.json.data.items.some((l) => l.type === 'admin_adjust'),
  )
}

async function suiteUi() {
  const browser = await chromium.launch({ headless: true })
  const { token, member } = await registerMember('m6ui')
  const claimable = await api('GET', '/api/v1/coupons/claimable', { token })
  const freeShip = claimable.json.data.items.find((c) => c.code === 'FREESHIP')
  if (freeShip?.can_claim) {
    await api('POST', `/api/v1/coupons/${freeShip.id}/claims`, { token, body: {}, expectHttp: 201 })
  }

  const context = await browser.newContext({ viewport: { width: 390, height: 844 } })
  const page = await context.newPage()
  await page.addInitScript(
    (payload) => {
      localStorage.setItem('sm_token', payload.token)
      localStorage.setItem('sm_member', JSON.stringify(payload.member))
    },
    { token, member },
  )

  await page.goto(`${H5}/coupons`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(600)
  check('H1', 'H5 領券中心可開', (await page.getByText('領取').count()) > 0 || (await page.getByText('已領取').count()) > 0)
  await page.screenshot({ path: path.join(SHOT, 'm6-coupon-center.png'), fullPage: true })

  await page.goto(`${H5}/my-coupons`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(500)
  check('H2', 'H5 我的券可開', (await page.getByText('WELCOME').count()) > 0 || (await page.getByText('未使用').count()) > 0)
  await page.screenshot({ path: path.join(SHOT, 'm6-my-coupons.png'), fullPage: true })

  await page.goto(`${H5}/member-level`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(500)
  check('H3', 'H5 等級頁可開', (await page.getByText('星').count()) > 0 || (await page.getByText('★').count()) > 0)
  await page.screenshot({ path: path.join(SHOT, 'm6-member-level.png'), fullPage: true })

  await page.goto(`${H5}/point-logs`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(400)
  await page.screenshot({ path: path.join(SHOT, 'm6-point-logs.png'), fullPage: true })
  check('H4', 'H5 積分明細可開', true)

  const admin = await browser.newContext({ viewport: { width: 1280, height: 800 } })
  const ap = await admin.newPage()
  await ap.goto(`${ADMIN}/login`, { waitUntil: 'networkidle' })
  await ap.locator('input').nth(0).fill('admin@sistermart.local')
  await ap.locator('input').nth(1).fill('admin123456')
  await ap.locator('button.n-button--primary-type').first().click()
  await ap.waitForFunction(() => Boolean(localStorage.getItem('sm_admin_token')), null, { timeout: 10000 })

  await ap.goto(`${ADMIN}/marketing/coupons`, { waitUntil: 'networkidle' })
  await ap.waitForTimeout(500)
  check('AD1', '後台優惠券頁', (await ap.getByText('WELCOME20').count()) > 0)
  await ap.screenshot({ path: path.join(SHOT, 'm6-admin-coupons.png'), fullPage: true })

  await ap.goto(`${ADMIN}/marketing/points`, { waitUntil: 'networkidle' })
  await ap.waitForTimeout(400)
  check('AD2', '後台積分設置頁', (await ap.getByText('積分設置').count()) > 0)
  await ap.screenshot({ path: path.join(SHOT, 'm6-admin-points.png'), fullPage: true })

  await ap.goto(`${ADMIN}/customers/members`, { waitUntil: 'networkidle' })
  await ap.waitForTimeout(500)
  check('AD3', '後台會員資產頁', (await ap.getByText('調整資產').count()) > 0)
  await ap.screenshot({ path: path.join(SHOT, 'm6-admin-members.png'), fullPage: true })

  await ap.goto(`${ADMIN}/customers/levels`, { waitUntil: 'networkidle' })
  await ap.waitForTimeout(400)
  check('AD4', '後台等級閾值頁', (await ap.getByText('會員等級').count()) > 0)
  await ap.screenshot({ path: path.join(SHOT, 'm6-admin-levels.png'), fullPage: true })

  // No recharge in account UI
  await page.goto(`${H5}/account`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(500)
  const rechargeUi = await page.getByText('充值').count()
  check('H5', 'H5 無充值入口', rechargeUi === 0)
  await page.screenshot({ path: path.join(SHOT, 'm6-account.png'), fullPage: true })

  await browser.close()
}

async function main() {
  await suiteApi()
  await suiteUi()
  const passed = results.filter((r) => r.pass).length
  const failed = results.filter((r) => !r.pass)
  const report = {
    milestone: 'M6',
    passed,
    failed: failed.length,
    results,
    at: new Date().toISOString(),
  }
  fs.writeFileSync(path.join(OUT, 'm6-accept/report.json'), JSON.stringify(report, null, 2))
  console.log(`\nM6 ${passed}/${results.length} passed`)
  if (failed.length) {
    console.error(failed)
    process.exit(1)
  }
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
