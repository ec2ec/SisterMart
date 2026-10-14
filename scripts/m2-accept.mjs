/**
 * SisterMart M2 acceptance runner.
 * Writes JSON report + screenshots under /opt/cursor/artifacts.
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
fs.mkdirSync(path.join(OUT, 'm2-accept'), { recursive: true })

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
    const err = new Error(`HTTP ${res.status} expected ${expectHttp}: ${text.slice(0, 300)}`)
    err.res = res
    err.json = json
    throw err
  }
  return { status: res.status, json }
}

function envelopeOk(json) {
  return json && typeof json === 'object' && json.code === 0 && 'data' in json && typeof json.message === 'string'
}

function envelopeFail(json) {
  return json && typeof json === 'object' && json.code !== 0 && json.data === null && json.error?.name
}

async function apiSuite() {
  // Health
  const health = await api('GET', '/api/v1/health', { expectHttp: 200 })
  check('A1', 'GET /api/v1/health REST 信封且數據庫已連接', envelopeOk(health.json) && health.json.data?.db?.connected === true, JSON.stringify(health.json.data?.db))

  // Tabbar public
  const tab = await api('GET', '/api/v1/tabbar', { expectHttp: 200 })
  check(
    'A2',
    'GET /api/v1/tabbar 返回四個固定槽位',
    envelopeOk(tab.json) &&
      Array.isArray(tab.json.data.items) &&
      tab.json.data.items.length === 4 &&
      ['home', 'category', 'cart', 'account'].every((s) => tab.json.data.items.some((i) => i.slot === s)),
    `slots=${(tab.json.data?.items || []).map((i) => i.slot).join(',')}`,
  )

  // Need agree
  const needAgree = await api('POST', '/api/v1/member-sessions', {
    body: { channel: 'email', email: 'nobody@sistermart.local', password: 'secret123', agreed: false },
    expectHttp: 422,
  })
  check('A3', '未勾選協議登錄返回 NEED_AGREE', envelopeFail(needAgree.json) && needAgree.json.error.name === 'NEED_AGREE')

  // WhatsApp placeholder
  const wa = await api('POST', '/api/v1/verification-codes', {
    body: { channel: 'whatsapp', purpose: 'register', target: '91234567' },
    expectHttp: 422,
  })
  check('A4', 'WhatsApp 註冊驗證碼占位失敗 WHATSAPP_UNAVAILABLE', envelopeFail(wa.json) && wa.json.error.name === 'WHATSAPP_UNAVAILABLE')

  // Admin session
  const adminLogin = await api('POST', '/api/v1/admin/sessions', {
    body: { email: 'admin@sistermart.local', password: 'admin123456' },
    expectHttp: 201,
  })
  const adminToken = adminLogin.json.data?.token
  check('A5', '管理員登錄創建 session 並返回 token', envelopeOk(adminLogin.json) && Boolean(adminToken) && adminLogin.json.data.admin?.role === 'super')

  const adminMe = await api('GET', '/api/v1/admin/admins/me', { token: adminToken, expectHttp: 200 })
  check('A6', '管理員令牌可訪問 /admin/admins/me', envelopeOk(adminMe.json) && adminMe.json.data.email === 'admin@sistermart.local')

  // Register + login + reset
  const email = `accept${Date.now()}@sistermart.local`
  const codeRes = await api('POST', '/api/v1/verification-codes', {
    body: { channel: 'email', purpose: 'register', target: email },
    expectHttp: 201,
  })
  const regCode = codeRes.json.data?.debug_code
  check('A7', '郵箱註冊驗證碼可發（debug_code）', envelopeOk(codeRes.json) && /^\d{6}$/.test(String(regCode)))

  const reg = await api('POST', '/api/v1/members', {
    body: { channel: 'email', email, password: 'secret123', code: regCode, agreed: true, locale: 'zh-HK' },
    expectHttp: 201,
  })
  const memberToken = reg.json.data?.token
  const promo = reg.json.data?.member?.promo_code
  check('A8', '郵箱註冊返回會員 token 與推廣號', envelopeOk(reg.json) && Boolean(memberToken) && String(promo).startsWith('SM'))

  const me = await api('GET', '/api/v1/members/me', { token: memberToken, expectHttp: 200 })
  check('A9', '會員令牌可讀 /members/me', envelopeOk(me.json) && me.json.data.email === email)

  const locale = await api('PATCH', '/api/v1/members/me', {
    token: memberToken,
    body: { locale: 'en' },
    expectHttp: 200,
  })
  check('A10', 'PATCH /members/me 可寫入語言偏好', envelopeOk(locale.json) && locale.json.data.locale === 'en')

  await api('DELETE', '/api/v1/member-sessions/current', { token: memberToken, expectHttp: 200 })
  const meAfter = await api('GET', '/api/v1/members/me', { token: memberToken, expectHttp: 401 })
  check('A11', '退出後令牌作廢', envelopeFail(meAfter.json) && meAfter.json.error.name === 'UNAUTHORIZED')

  const login = await api('POST', '/api/v1/member-sessions', {
    body: { channel: 'email', email, password: 'secret123', agreed: true },
    expectHttp: 201,
  })
  check('A12', '郵箱密碼重新登錄成功', envelopeOk(login.json) && Boolean(login.json.data?.token))

  const resetCode = await api('POST', '/api/v1/verification-codes', {
    body: { channel: 'email', purpose: 'reset', target: email },
    expectHttp: 201,
  })
  const rcode = resetCode.json.data?.debug_code
  const reset = await api('PATCH', '/api/v1/member-passwords', {
    body: { identity: email, code: rcode, password: 'secret456' },
    expectHttp: 200,
  })
  check('A13', '忘記密碼（驗證碼重置）可用', envelopeOk(reset.json) && reset.json.data?.reset === true)

  const loginNew = await api('POST', '/api/v1/member-sessions', {
    body: { channel: 'email', email, password: 'secret456', agreed: true },
    expectHttp: 201,
  })
  check('A14', '新密碼可登錄', envelopeOk(loginNew.json) && Boolean(loginNew.json.data?.token))

  // Admin tabbar put roundtrip
  const current = await api('GET', '/api/v1/admin/tabbar', { token: adminToken, expectHttp: 200 })
  const items = current.json.data.items.map((i) => ({ ...i }))
  const home = items.find((i) => i.slot === 'home')
  const originalZh = home.label_zh
  home.label_zh = '驗收首頁'
  const put = await api('PUT', '/api/v1/admin/tabbar', {
    token: adminToken,
    body: { bar_bg: current.json.data.bar_bg, items },
    expectHttp: 200,
  })
  const pub = await api('GET', '/api/v1/tabbar', { expectHttp: 200 })
  const live = pub.json.data.items.find((i) => i.slot === 'home')?.label_zh
  check('A15', '後台 PUT 底欄後公開接口立即生效', envelopeOk(put.json) && live === '驗收首頁', `live=${live}`)
  home.label_zh = originalZh
  await api('PUT', '/api/v1/admin/tabbar', {
    token: adminToken,
    body: { bar_bg: current.json.data.bar_bg, items },
    expectHttp: 200,
  })

  return { email, password: 'secret456' }
}

async function uiSuite(account) {
  const browser = await chromium.launch({ headless: true })
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    recordVideo: { dir: path.join(OUT, 'm2-accept'), size: { width: 390, height: 844 } },
  })
  const page = await context.newPage()
  page.on('pageerror', (e) => {
    throw e
  })

  // Browse logged out
  await page.goto(`${H5}/`, { waitUntil: 'networkidle' })
  await page.waitForSelector('.van-tabbar')
  const tabCount = await page.locator('.van-tabbar-item').count()
  check('U1', '未登錄可瀏覽首頁且底欄四鍵可見', tabCount === 4)

  // Login page: no tabbar + need agree
  await page.goto(`${H5}/login?redirect=/wishlist`, { waitUntil: 'networkidle' })
  const loginTabbar = await page.locator('.van-tabbar').count()
  check('U2', '登錄頁不顯示底欄', loginTabbar === 0)
  await page.locator('input[type=email]').fill(account.email)
  await page.locator('input[autocomplete=current-password]').fill(account.password)
  await page.locator('.submit').click()
  await page.waitForSelector('.van-toast')
  const needAgreeToast = await page.locator('.van-toast').innerText()
  check('U3', '未勾選協議無法登錄（頁面提示）', needAgreeToast.includes('協議') || needAgreeToast.includes('agree') || needAgreeToast.includes('Agree'), needAgreeToast)
  await page.waitForSelector('.van-toast', { state: 'hidden' })

  // Login with agree + redirect
  await page.locator('.check input').check()
  await page.locator('.submit').click()
  await page.waitForFunction(() => location.pathname === '/wishlist', null, { timeout: 10000 })
  check('U4', '登錄成功回跳來原頁（wishlist）', new URL(page.url()).pathname === '/wishlist', page.url())
  await page.screenshot({ path: path.join(SHOT, 'm2_accept_login_redirect.png') })

  // Register need agree
  await page.evaluate(() => {
    localStorage.removeItem('sm_token')
    localStorage.removeItem('sm_member')
  })
  await page.goto(`${H5}/register`, { waitUntil: 'networkidle' })
  await page.locator('.submit').click()
  await page.waitForSelector('.van-toast')
  const regAgree = await page.locator('.van-toast').innerText()
  check('U5', '未勾選協議無法註冊（頁面提示）', regAgree.includes('協議') || regAgree.includes('agree') || regAgree.includes('Agree'), regAgree)
  await page.waitForSelector('.van-toast', { state: 'hidden' })

  // Language switch
  await page.goto(`${H5}/`, { waitUntil: 'networkidle' })
  await page.waitForSelector('.van-tabbar')
  const before = await page.locator('.van-tabbar-item').first().innerText()
  // logo toggles locale on home header
  const logo = page.locator('header .brand, .logo, a.brand, .app-header .brand').first()
  if (await logo.count()) {
    await logo.click()
  } else {
    await page.goto(`${H5}/account`, { waitUntil: 'networkidle' })
    await page.getByRole('button', { name: /English|繁體/ }).first().click()
    await page.goto(`${H5}/`, { waitUntil: 'networkidle' })
  }
  await page.waitForTimeout(400)
  const after = await page.locator('.van-tabbar-item').first().innerText()
  // ensure en or zh switched - if logo didn't work try account button
  let switched = before !== after
  if (!switched) {
    await page.goto(`${H5}/account`, { waitUntil: 'networkidle' })
    await page.getByRole('button', { name: /English|繁體|Switch/ }).first().click()
    await page.goto(`${H5}/`, { waitUntil: 'networkidle' })
    await page.waitForTimeout(400)
    const after2 = await page.locator('.van-tabbar-item').first().innerText()
    switched = before !== after2
    check('U6', '語言切換後面板/底欄文案切換', switched, `${before} -> ${after2}`)
    await page.screenshot({ path: path.join(SHOT, 'm2_accept_locale_en.png') })
  } else {
    check('U6', '語言切換後面板/底欄文案切換', true, `${before} -> ${after}`)
    await page.screenshot({ path: path.join(SHOT, 'm2_accept_locale_en.png') })
  }

  // Tabbar fetch on enter + admin change live
  const adminCtx = await browser.newContext({ viewport: { width: 1280, height: 800 } })
  const admin = await adminCtx.newPage()
  await admin.goto(`${ADMIN}/login`, { waitUntil: 'networkidle' })
  await admin.locator('input[type=password]').fill('admin123456')
  await admin.getByRole('button', { name: '登錄' }).click()
  await admin.waitForURL(`${ADMIN}/`)
  await admin.goto(`${ADMIN}/settings/tabbar`, { waitUntil: 'networkidle' })
  await admin.waitForSelector('text=底欄背景')
  await admin.screenshot({ path: path.join(SHOT, 'm2_accept_admin_tabbar.png') })
  const firstText = admin.locator('.n-input__input-el').first()
  await firstText.fill('驗收主頁')
  await admin.getByRole('button', { name: '保存' }).click()
  await admin.waitForSelector('text=底部導航已保存')

  await page.goto(`${H5}/category`, { waitUntil: 'networkidle' })
  await page.waitForFunction(() => document.body.innerText.includes('驗收主頁'), null, { timeout: 8000 })
  const tabText = await page.locator('.van-tabbar').innerText()
  check('U7', '進入帶底欄頁拉取配置且後台改動生效', tabText.includes('驗收主頁'), tabText.replace(/\s+/g, ' '))
  await page.screenshot({ path: path.join(SHOT, 'm2_accept_tabbar_live.png') })

  // restore
  await firstText.fill('首頁')
  await admin.getByRole('button', { name: '保存' }).click()
  await admin.waitForSelector('text=底部導航已保存')

  // Tabbar fallback: poison API via offline? simulate by blocking route then clearing cache
  await page.evaluate(() => sessionStorage.removeItem('sm_tabbar'))
  await page.route('**/api/v1/tabbar', (route) => route.abort())
  await page.goto(`${H5}/cart`, { waitUntil: 'domcontentloaded' })
  await page.waitForSelector('.van-tabbar')
  const fallback = await page.locator('.van-tabbar-item').count()
  const fallbackText = await page.locator('.van-tabbar').innerText()
  check('U8', '底欄接口失敗時仍有內建四鍵可點', fallback === 4 && /首頁|Home|購物車|Cart/.test(fallbackText), fallbackText.replace(/\s+/g, ' '))
  await page.unroute('**/api/v1/tabbar')
  await page.screenshot({ path: path.join(SHOT, 'm2_accept_tabbar_fallback.png') })

  // Forgot password UI path — dedicated account so API suite rate-limit does not collide
  const forgotEmail = `forgot${Date.now()}@sistermart.local`
  const regCode = await api('POST', '/api/v1/verification-codes', {
    body: { channel: 'email', purpose: 'register', target: forgotEmail },
    expectHttp: 201,
  })
  await api('POST', '/api/v1/members', {
    body: {
      channel: 'email',
      email: forgotEmail,
      password: 'secret123',
      code: regCode.json.data.debug_code,
      agreed: true,
      locale: 'zh-HK',
    },
    expectHttp: 201,
  })
  await page.goto(`${H5}/forgot`, { waitUntil: 'networkidle' })
  const forgotTab = await page.locator('.van-tabbar').count()
  check('U9', '忘記密碼頁不顯示底欄', forgotTab === 0)
  await page.locator('input[autocomplete=username]').fill(forgotEmail)
  const forgotRespPromise = page.waitForResponse(
    (res) => res.url().includes('/api/v1/verification-codes') && res.request().method() === 'POST',
  )
  await page.locator('button.send').click()
  const forgotResp = await forgotRespPromise
  const forgotBody = await forgotResp.json()
  const fcode = forgotBody?.data?.debug_code
  check(
    'U10',
    '忘記密碼可發送驗證碼',
    forgotResp.status() === 201 && forgotBody.code === 0 && /^\d{6}$/.test(String(fcode)),
    `http=${forgotResp.status()} code=${forgotBody.code} debug=${fcode}`,
  )
  await page.waitForSelector('.van-toast', { state: 'hidden' }).catch(() => {})
  if (fcode) {
    await page.locator('input[inputmode=numeric]').fill(String(fcode))
    await page.locator('input[autocomplete=new-password]').first().fill('secret789')
    await page.locator('input[autocomplete=new-password]').nth(1).fill('secret789')
    await page.locator('.submit').click()
    await page.waitForFunction(() => location.pathname === '/login', null, { timeout: 10000 })
    check('U11', '重設密碼後回到登錄頁', new URL(page.url()).pathname === '/login', page.url())
    await page.screenshot({ path: path.join(SHOT, 'm2_accept_forgot_done.png') })
  } else {
    check('U11', '重設密碼後回到登錄頁', false, 'no code')
  }

  await adminCtx.close()
  await context.close()
  await browser.close()

  // move video
  const vids = fs.readdirSync(path.join(OUT, 'm2-accept')).filter((f) => f.endsWith('.webm'))
  if (vids.length) {
    const src = path.join(OUT, 'm2-accept', vids.sort().pop())
    const dest = path.join(OUT, 'm2_accept_h5_flow.webm')
    fs.renameSync(src, dest)
  }
}

const account = await apiSuite()
await uiSuite(account)

const failed = results.filter((r) => !r.pass)
const report = {
  milestone: 'M2',
  at: new Date().toISOString(),
  total: results.length,
  passed: results.filter((r) => r.pass).length,
  failed: failed.length,
  results,
}
fs.writeFileSync(path.join(OUT, 'm2_accept_report.json'), JSON.stringify(report, null, 2))
console.log('\nSUMMARY', `${report.passed}/${report.total} passed`)
if (failed.length) {
  console.error('FAILED ITEMS', failed)
  process.exit(1)
}
