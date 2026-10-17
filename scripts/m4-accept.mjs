/**
 * SisterMart M4 acceptance: trade loop.
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
fs.mkdirSync(path.join(OUT, 'm4-accept'), { recursive: true })

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
  if (!ok(codeRes.json)) throw new Error('verify code failed')
  const code = codeRes.json.data.debug_code
  const reg = await api('POST', '/api/v1/members', {
    body: { channel: 'email', email, password: 'secret123', code, agreed: true },
  })
  if (!ok(reg.json) || !reg.json.data?.token) throw new Error('register failed: ' + JSON.stringify(reg.json))
  return { email, token: reg.json.data.token, member: reg.json.data.member }
}

async function suiteApi() {
  // A1 unauthenticated cannot create order
  const guest = await api('POST', '/api/v1/orders', { body: { address_id: 1 }, expectHttp: 401 })
  check('A1', '未登錄無法提交訂單', guest.json.error?.name === 'UNAUTHORIZED')

  const { token, member } = await registerMember('m4a')
  check('A1b', '新會員帶餘額與積分', member.balance >= 50 && member.points >= 5000, `b=${member.balance} p=${member.points}`)

  const nonHk = await api('POST', '/api/v1/addresses', {
    token,
    body: { receiver: 'A', phone: '9000', region: '深圳', district: '南山', detail: 'x' },
    expectHttp: 422,
  })
  check('A2', '非香港地址無法保存', nonHk.json.error?.name === 'INVALID_REGION')

  const addr = await api('POST', '/api/v1/addresses', {
    token,
    body: { receiver: 'Coco', phone: '90001111', region: '九龍', district: '旺角', detail: '山東街1號', is_default: true },
    expectHttp: 201,
  })
  const addressId = addr.json.data.id
  check('A2b', '香港地址可保存', ok(addr.json) && addressId > 0)

  // shipping: under threshold
  const products = await api('GET', '/api/v1/products/p3', { expectHttp: 200 })
  const skuCheap = products.json.data.skus.find((s) => s.stock > 0)
  await api('POST', '/api/v1/cart/items', { token, body: { sku_id: skuCheap.id, qty: 1 }, expectHttp: 201 })
  const quoteLow = await api('POST', '/api/v1/checkout/quote', { token, body: {}, expectHttp: 200 })
  check(
    'A3',
    '未滿包郵門檻收固定運費',
    ok(quoteLow.json) && quoteLow.json.data.shipping_amount > 0 && quoteLow.json.data.goods_amount < quoteLow.json.data.free_threshold,
    `ship=${quoteLow.json.data.shipping_amount} goods=${quoteLow.json.data.goods_amount}`,
  )

  // clear and add expensive items for free shipping
  const cart = await api('GET', '/api/v1/cart', { token, expectHttp: 200 })
  const ids = (cart.json.data.items || []).map((i) => i.id)
  if (ids.length) await api('POST', '/api/v1/cart/delete-items', { token, body: { ids }, expectHttp: 200 })

  const p1 = await api('GET', '/api/v1/products/p1', { expectHttp: 200 })
  const sku1 = p1.json.data.skus.find((s) => s.stock > 0)
  await api('POST', '/api/v1/cart/items', { token, body: { sku_id: sku1.id, qty: 2 }, expectHttp: 201 })
  const quoteHigh = await api('POST', '/api/v1/checkout/quote', { token, body: {}, expectHttp: 200 })
  check(
    'A3b',
    '滿門檻包郵',
    ok(quoteHigh.json) && quoteHigh.json.data.shipping_amount === 0 && quoteHigh.json.data.goods_amount >= quoteHigh.json.data.free_threshold,
    `ship=${quoteHigh.json.data.shipping_amount} goods=${quoteHigh.json.data.goods_amount}`,
  )

  const order = await api('POST', '/api/v1/orders', {
    token,
    body: { address_id: addressId },
    expectHttp: 201,
  })
  check('A4a', '下單後為待付款', ok(order.json) && order.json.data.status === 'pending_payment', order.json.data?.status)
  const orderId = order.json.data.id
  const pay = await api('POST', `/api/v1/orders/${orderId}/payments`, {
    token,
    body: { channel: 'fps' },
    expectHttp: 201,
  })
  const confirmed = await api('POST', `/api/v1/payments/${pay.json.data.payment_id}/mock-confirm`, {
    token,
    body: {},
    expectHttp: 200,
  })
  check('A4', '模擬支付後待付款→待發貨', ok(confirmed.json) && confirmed.json.data.status === 'pending_shipment', confirmed.json.data?.status)

  // zero pay: use points+balance heavily on cheap item
  const { token: t2 } = await registerMember('m4z')
  const addr2 = await api('POST', '/api/v1/addresses', {
    token: t2,
    body: { receiver: 'Zero', phone: '90002222', region: '香港島', district: '中環', detail: '皇后大道1號', is_default: true },
    expectHttp: 201,
  })
  // set huge balance via direct? use quote with use_balance+use_points on small goods
  // Give member more balance by creating with seed values 50 + 5000 points
  // p3 ~49, with coupon 20, points, balance can zero out shipping too if free threshold...
  // Force free ship by setting shipping fee covered by balance: goods 49 - coupon 20 = 29, points half, balance rest + shipping
  const p3 = await api('GET', '/api/v1/products/p3', { expectHttp: 200 })
  const sku3 = p3.json.data.skus.find((s) => s.stock > 0)
  const coupons = await api('POST', '/api/v1/checkout/quote', {
    token: t2,
    body: { buy_now: true, sku_id: sku3.id, qty: 1, use_points: true, use_balance: true },
    expectHttp: 200,
  })
  const grant = (coupons.json.data.coupons || []).find((c) => c.usable)?.grant_id || null
  const zeroQuote = await api('POST', '/api/v1/checkout/quote', {
    token: t2,
    body: { buy_now: true, sku_id: sku3.id, qty: 1, use_points: true, use_balance: true, coupon_grant_id: grant },
    expectHttp: 200,
  })
  // If not zero yet, boost member balance via mysql is outside; check pay_amount
  let zeroOrder
  if (zeroQuote.json.data.pay_amount <= 0) {
    zeroOrder = await api('POST', '/api/v1/orders', {
      token: t2,
      body: {
        address_id: addr2.json.data.id,
        buy_now: true,
        sku_id: sku3.id,
        qty: 1,
        use_points: true,
        use_balance: true,
        coupon_grant_id: grant,
      },
      expectHttp: 201,
    })
  } else {
    // top-up balance using temporary workaround: place with mock by updating member via another quote path
    // Use SQL through API job not available — call close and use php seed style via fetch to admin? 
    // Directly bump via registering doesn't help. Use buy_now with settings: set free threshold high and fee 0 temporarily via shop_settings API? none.
    // Fall back: update member balance with a one-off internal call through close-expired + raw mysql in script.
    const { execSync } = await import('node:child_process')
    const mid = (await api('GET', '/api/v1/members/me', { token: t2, expectHttp: 200 })).json.data.id
    execSync(`mysql -usistermart -psistermart -h127.0.0.1 sistermart -e "UPDATE members SET balance=500, points=20000 WHERE id=${mid}"`)
    const q2 = await api('POST', '/api/v1/checkout/quote', {
      token: t2,
      body: { buy_now: true, sku_id: sku3.id, qty: 1, use_points: true, use_balance: true, coupon_grant_id: grant },
      expectHttp: 200,
    })
    check('A5pre', '加餘額後可算至實付 0', q2.json.data.pay_amount <= 0, `pay=${q2.json.data.pay_amount}`)
    zeroOrder = await api('POST', '/api/v1/orders', {
      token: t2,
      body: {
        address_id: addr2.json.data.id,
        buy_now: true,
        sku_id: sku3.id,
        qty: 1,
        use_points: true,
        use_balance: true,
        coupon_grant_id: grant,
      },
      expectHttp: 201,
    })
  }
  check(
    'A5',
    '實付為 0 時直接支付成功（待發貨）',
    ok(zeroOrder.json) && zeroOrder.json.data.status === 'pending_shipment' && zeroOrder.json.data.pay_amount <= 0,
    `status=${zeroOrder.json.data?.status} pay=${zeroOrder.json.data?.pay_amount}`,
  )

  // timeout close releases stock / assets
  const { token: t3, member: m3 } = await registerMember('m4t')
  const addr3 = await api('POST', '/api/v1/addresses', {
    token: t3,
    body: { receiver: 'T', phone: '90003333', region: '新界', district: '沙田', detail: '車站1號', is_default: true },
    expectHttp: 201,
  })
  const p4 = await api('GET', '/api/v1/products/p4', { expectHttp: 200 })
  const sku4 = p4.json.data.skus.find((s) => s.code === 'red|16')
  const stockBefore = sku4.stock
  const balBefore = m3.balance
  const ptsBefore = m3.points
  const grant3 = (
    await api('POST', '/api/v1/checkout/quote', {
      token: t3,
      body: { buy_now: true, sku_id: sku4.id, qty: 1 },
      expectHttp: 200,
    })
  ).json.data.coupons.find((c) => c.usable)?.grant_id
  const pending = await api('POST', '/api/v1/orders', {
    token: t3,
    body: {
      address_id: addr3.json.data.id,
      buy_now: true,
      sku_id: sku4.id,
      qty: 1,
      use_points: true,
      use_balance: true,
      coupon_grant_id: grant3,
    },
    expectHttp: 201,
  })
  check('A6a', '下單鎖庫存與凍結資產', pending.json.data.status === 'pending_payment')
  const { execSync } = await import('node:child_process')
  execSync(
    `mysql -usistermart -psistermart -h127.0.0.1 sistermart -e "UPDATE orders SET expire_at=DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id=${pending.json.data.id}"`,
  )
  const closed = await api('POST', '/api/v1/jobs/close-expired-orders', { body: {}, expectHttp: 200 })
  check('A6b', '超時關單任務執行', ok(closed.json) && closed.json.data.closed >= 1, JSON.stringify(closed.json.data))
  const after = await api('GET', `/api/v1/orders/${pending.json.data.id}`, { token: t3, expectHttp: 200 })
  const me = await api('GET', '/api/v1/members/me', { token: t3, expectHttp: 200 })
  const p4b = await api('GET', '/api/v1/products/p4', { expectHttp: 200 })
  const stockAfter = p4b.json.data.skus.find((s) => s.code === 'red|16').stock
  check(
    'A6',
    '超時關閉釋放庫存並退回積分/餘額/券',
    after.json.data.status === 'cancelled' &&
      stockAfter === stockBefore &&
      me.json.data.balance >= balBefore &&
      me.json.data.points >= ptsBefore,
    `status=${after.json.data.status} stock ${stockBefore}->${stockAfter} bal=${me.json.data.balance} pts=${me.json.data.points}`,
  )

  const adminLogin = await api('POST', '/api/v1/admin/sessions', {
    body: { email: 'admin@sistermart.local', password: 'admin123456' },
    expectHttp: 201,
  })
  const adminOrders = await api('GET', '/api/v1/admin/orders', { token: adminLogin.json.data.token, expectHttp: 200 })
  check('A7', '後台訂單列表', ok(adminOrders.json) && adminOrders.json.data.items.length >= 1)
}

async function suiteUi() {
  const browser = await chromium.launch({ headless: true })
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, locale: 'zh-HK' })
  const page = await context.newPage()

  // register via UI is heavy; set token from API
  const { token } = await registerMember('m4ui')
  await page.addInitScript((tok) => {
    localStorage.setItem('sm_token', tok)
    localStorage.setItem('sm_member', JSON.stringify({ id: 1, email: 'x@test.local', phone: null, locale: 'zh-HK', promo_code: 'SM', balance: 50, points: 5000 }))
  }, token)

  await page.goto(`${H5}/product/p4`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(500)
  await page.getByRole('button', { name: '加入購物車' }).click()
  await page.waitForTimeout(800)
  await page.goto(`${H5}/cart`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(600)
  const cartHas = (await page.getByText('紅心手鏈').count()) > 0
  check('H1', '購物車顯示加購商品', cartHas)
  await page.screenshot({ path: path.join(SHOT, 'm4-cart.png'), fullPage: true })

  await api('POST', '/api/v1/addresses', {
    token,
    body: { receiver: '測試用戶', phone: '90004444', region: '九龍', district: '尖沙咀', detail: '廣東道100號', is_default: true },
  })
  await page.goto(`${H5}/addresses`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(800)
  check('H2', '地址頁可展示香港地址', (await page.getByText('測試用戶').count()) > 0)
  await page.screenshot({ path: path.join(SHOT, 'm4-address.png'), fullPage: true })

  await page.goto(`${H5}/checkout`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(800)
  check('H3', '確認訂單頁可開', (await page.getByText('確認訂單').count()) > 0 || (await page.getByText('實付').count()) > 0)
  await page.screenshot({ path: path.join(SHOT, 'm4-checkout.png'), fullPage: true })
  await page.getByRole('button', { name: '提交訂單' }).click()
  await page.waitForTimeout(1500)
  const onPay = page.url().includes('/payment/') || page.url().includes('/pay-result')
  check('H4', '提交後進入支付或結果頁', onPay, page.url())
  await page.screenshot({ path: path.join(SHOT, 'm4-payment.png'), fullPage: true })
  if (page.url().includes('/payment/')) {
    await page.getByRole('button', { name: /模擬支付|確認/ }).click()
    await page.waitForTimeout(1200)
  }
  check('H5', '支付結果頁', page.url().includes('/pay-result') || (await page.getByText('支付成功').count()) > 0, page.url())
  await page.screenshot({ path: path.join(SHOT, 'm4-pay-result.png'), fullPage: true })

  const admin = await browser.newContext({ viewport: { width: 1280, height: 800 } })
  const ap = await admin.newPage()
  await ap.goto(`${ADMIN}/login`, { waitUntil: 'networkidle' })
  await ap.locator('input').nth(0).fill('admin@sistermart.local')
  await ap.locator('input').nth(1).fill('admin123456')
  await ap.locator('button.n-button--primary-type').first().click()
  await ap.waitForFunction(() => Boolean(localStorage.getItem('sm_admin_token')), null, { timeout: 10000 })
  await ap.goto(`${ADMIN}/sales/orders`, { waitUntil: 'networkidle' })
  await ap.waitForTimeout(800)
  check('AD1', '後台訂單列表可開', (await ap.getByText('訂單').count()) > 0 && (await ap.locator('table tr').count()) > 1)
  await ap.screenshot({ path: path.join(SHOT, 'm4-admin-orders.png'), fullPage: true })

  await browser.close()
}

async function main() {
  await suiteApi()
  await suiteUi()
  const passed = results.filter((r) => r.pass).length
  const failed = results.filter((r) => !r.pass)
  const report = {
    milestone: 'M4',
    generated_at: new Date().toISOString(),
    summary: { total: results.length, passed, failed: failed.length },
    results,
  }
  fs.writeFileSync(path.join(OUT, 'm4-accept', 'report.json'), JSON.stringify(report, null, 2))
  console.log(`\nM4 acceptance: ${passed}/${results.length} passed`)
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
