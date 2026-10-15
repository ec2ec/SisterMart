/**
 * SisterMart M7 acceptance: mock marketing UI + APIs.
 */
import pkg from '/tmp/node_modules/playwright/index.js'
import fs from 'node:fs'
import path from 'node:path'

const { chromium } = pkg
const BASE = 'http://127.0.0.1:8000'
const H5 = 'http://127.0.0.1:5173'
const OUT = '/opt/cursor/artifacts'
const SHOT = path.join(OUT, 'screenshots')
fs.mkdirSync(SHOT, { recursive: true })
fs.mkdirSync(path.join(OUT, 'm7-accept'), { recursive: true })

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

async function suiteApi() {
  const sessions = await api('GET', '/api/v1/seckill/sessions')
  check('S1', '秒殺場次 Mock', ok(sessions.json) && sessions.json.data.mock && sessions.json.data.items.length >= 1)

  const mall = await api('GET', '/api/v1/points-mall/products')
  check('P1', '積分商城 Mock', ok(mall.json) && mall.json.data.mock && mall.json.data.items.length >= 1)

  const { token, member } = await registerMember('m7a')
  const cal = await api('GET', '/api/v1/checkins/calendar', { token })
  check('C1', '簽到日曆', ok(cal.json) && Array.isArray(cal.json.data.days))
  const sign = await api('POST', '/api/v1/checkins', { token, body: {}, expectHttp: 201 })
  check('C2', '簽到加積分', ok(sign.json) && sign.json.data.reward > 0)
  const again = await api('POST', '/api/v1/checkins', { token, body: {} })
  check('C3', '同日不可重複簽', !ok(again.json))

  const dist = await api('GET', '/api/v1/distribution/overview', { token })
  check('D1', '分銷概覽', ok(dist.json) && dist.json.data.promo_code === member.promo_code)
  const wd = await api('POST', '/api/v1/distribution/withdrawals', {
    token,
    body: { channel: 'balance', amount: 10 },
    expectHttp: 201,
  })
  check('D2', '提現轉餘額 Mock', ok(wd.json) && wd.json.data.status === 'done')

  const msgs = await api('GET', '/api/v1/messages', { token })
  check('M1', '消息雙列表', ok(msgs.json) && Array.isArray(msgs.json.data.order) && Array.isArray(msgs.json.data.system))
  const unread = await api('GET', '/api/v1/messages/unread-count', { token })
  check('M2', '未讀計數', ok(unread.json) && unread.json.data.unread >= 1)
  const mid = msgs.json.data.system[0].id
  const marked = await api('POST', '/api/v1/messages/read', { token, body: { id: mid } })
  const unread2 = await api('GET', '/api/v1/messages/unread-count', { token })
  check(
    'M3',
    '標記已讀減少未讀',
    ok(marked.json) && unread2.json.data.unread < unread.json.data.unread,
    `${unread.json.data.unread}->${unread2.json.data.unread}`,
  )

  // share ref browse
  const share = await api('POST', '/api/v1/share-views', {
    body: { ref: member.promo_code, product_id: 'p1' },
    expectHttp: 201,
  })
  check('R1', '分享瀏覽計積分 Mock', ok(share.json) && share.json.data.credited === true)
  const share2 = await api('POST', '/api/v1/share-views', {
    body: { ref: member.promo_code, product_id: 'p1' },
    expectHttp: 201,
  })
  check('R2', '同 IP 同連結不重複', ok(share2.json) && share2.json.data.credited === false)

  // M4 regress: still can quote/order
  const addr = await api('POST', '/api/v1/addresses', {
    token,
    body: { receiver: 'M7', phone: '90007777', region: '九龍', district: '旺角', detail: 'M7道1號', is_default: true },
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
  check('X1', 'M4 真實下單仍可用', ok(order.json) && order.json.data.id)
}

async function suiteUi() {
  const browser = await chromium.launch({ headless: true })
  const { token, member } = await registerMember('m7ui')
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } })
  const page = await context.newPage()
  await page.addInitScript(
    (payload) => {
      localStorage.setItem('sm_token', payload.token)
      localStorage.setItem('sm_member', JSON.stringify(payload.member))
    },
    { token, member },
  )

  const pages = [
    ['/seckill', 'H1', '秒殺列表', '秒殺'],
    ['/points-mall', 'H2', '積分商城', '積分'],
    ['/checkin', 'H3', '簽到頁', '簽到'],
    ['/distribution', 'H4', '分銷中心', '推廣'],
    ['/distribution/downlines', 'H5', '下級列表', '下級'],
    ['/distribution/withdraw', 'H6', '提現頁', '提現'],
    ['/distribution/poster', 'H7', '海報頁', '海報'],
    ['/messages', 'H8', '消息雙列表', '訂單消息'],
  ]

  for (const [url, id, title, needle] of pages) {
    await page.goto(`${H5}${url}`, { waitUntil: 'networkidle' })
    await page.waitForTimeout(450)
    const hasMock = (await page.getByText('Mock').count()) > 0
    const hasNeedle = (await page.getByText(needle).count()) > 0
    check(id, title, hasMock || hasNeedle, `mock=${hasMock} needle=${hasNeedle}`)
    await page.screenshot({ path: path.join(SHOT, `m7-${id.toLowerCase()}.png`), fullPage: true })
  }

  // header unread badge path
  await page.goto(`${H5}/`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(500)
  check('H9', '首頁可開（無白屏）', (await page.locator('.home').count()) > 0)
  await page.screenshot({ path: path.join(SHOT, 'm7-home.png'), fullPage: true })

  // share with ref
  await page.goto(`${H5}/product/p1?ref=${member.promo_code}`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(600)
  check('H10', '帶 ref 商品頁可開', (await page.locator('.pdp').count()) > 0)
  await page.screenshot({ path: path.join(SHOT, 'm7-product-ref.png'), fullPage: true })

  await browser.close()
}

async function main() {
  await suiteApi()
  await suiteUi()
  const passed = results.filter((r) => r.pass).length
  const failed = results.filter((r) => !r.pass)
  const report = { milestone: 'M7', passed, failed: failed.length, results, at: new Date().toISOString() }
  fs.writeFileSync(path.join(OUT, 'm7-accept/report.json'), JSON.stringify(report, null, 2))
  console.log(`\nM7 ${passed}/${results.length} passed`)
  if (failed.length) {
    console.error(failed)
    process.exit(1)
  }
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
