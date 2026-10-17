/**
 * SisterMart M9: production build smoke + demo login + mock pay.
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
fs.mkdirSync(path.join(OUT, 'm9-accept'), { recursive: true })

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

function suiteDocsAndBuild() {
  const docs = [
    'docs/验收/M9-部署与环境变量.md',
    'docs/验收/M9-演示账号.md',
    'scripts/deploy/build-frontends.sh',
    'scripts/deploy/backup.sh',
    'scripts/deploy/rollback.sh',
    'scripts/deploy/install.sh',
    'scripts/deploy/nginx.sistermart.conf.example',
  ]
  for (const d of docs) {
    check(`D-${path.basename(d)}`, `交付物存在 ${d}`, fs.existsSync(d))
  }

  execSync('php apps/api/database/m9_seed.php', { stdio: 'inherit' })
  execSync('bash scripts/deploy/build-frontends.sh', {
    stdio: 'inherit',
    env: { ...process.env, VITE_API_BASE: 'http://127.0.0.1:8000' },
  })
  check('B1', 'H5 dist 已生成', fs.existsSync('apps/h5/dist/index.html'))
  check('B2', 'Admin dist 已生成', fs.existsSync('apps/admin/dist/index.html'))
}

async function suiteDemoPay() {
  const health = await api('GET', '/api/v1/health')
  check('H1', 'API 健康', ok(health.json) && health.json.data?.db?.connected)

  const login = await api('POST', '/api/v1/member-sessions', {
    body: { channel: 'email', email: 'demo@sistermart.local', password: 'demo123456', agreed: true },
  })
  check('L1', '演示会员可登录', ok(login.json) && login.json.data?.token, login.json.message)
  const token = login.json.data.token

  const admin = await api('POST', '/api/v1/admin/sessions', {
    body: { email: 'admin@sistermart.local', password: 'admin123456' },
    expectHttp: 201,
  })
  check('L2', '演示超管可登录', ok(admin.json))

  execSync(
    `mysql -usistermart -psistermart -h127.0.0.1 sistermart -e "UPDATE members SET points=0,balance=0 WHERE email='demo@sistermart.local'"`,
  )
  const addr = await api('POST', '/api/v1/addresses', {
    token,
    body: { receiver: 'Demo', phone: '90009999', region: '九龍', district: '旺角', detail: '演示道1號', is_default: true },
  })
  const p1 = await api('GET', '/api/v1/products/p1')
  const sku = p1.json.data.skus.find((s) => s.stock > 0)
  const order = await api('POST', '/api/v1/orders', {
    token,
    body: { address_id: addr.json.data.id, buy_now: true, sku_id: sku.id, qty: 1 },
  })
  check('O1', '演示下单', ok(order.json), order.json.data?.status)
  if (order.json.data.status === 'pending_payment') {
    const pay = await api('POST', `/api/v1/orders/${order.json.data.id}/payments`, {
      token,
      body: { channel: 'fps' },
      expectHttp: 201,
    })
    const confirmed = await api('POST', `/api/v1/payments/${pay.json.data.payment_id}/mock-confirm`, {
      token,
      body: {},
    })
    check('O2', '模拟支付成功→待发货', ok(confirmed.json) && confirmed.json.data.status === 'pending_shipment')
  } else {
    check('O2', '零元/抵扣直接待发货', order.json.data.status === 'pending_shipment', order.json.data.status)
  }
}

async function suiteUi() {
  const browser = await chromium.launch({ headless: true })
  const page = await browser.newPage({ viewport: { width: 390, height: 844 } })
  await page.goto(`${H5}/login`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(400)
  // fill login — fields vary; try email/password inputs
  const inputs = page.locator('input')
  const n = await inputs.count()
  if (n >= 2) {
    await inputs.nth(0).fill('demo@sistermart.local')
    await inputs.nth(1).fill('demo123456')
  }
  await page.screenshot({ path: path.join(SHOT, 'm9-h5-login.png'), fullPage: true })
  check('U1', 'H5 登录页可开', (await page.getByText('登录').count()) + (await page.getByText('登錄').count()) > 0)

  const admin = await browser.newContext({ viewport: { width: 1280, height: 800 } })
  const ap = await admin.newPage()
  await ap.goto(`${ADMIN}/login`, { waitUntil: 'networkidle' })
  await ap.locator('input').nth(0).fill('admin@sistermart.local')
  await ap.locator('input').nth(1).fill('admin123456')
  await ap.locator('button.n-button--primary-type').first().click()
  await ap.waitForFunction(() => Boolean(localStorage.getItem('sm_admin_token')), null, { timeout: 10000 })
  await ap.screenshot({ path: path.join(SHOT, 'm9-admin-home.png'), fullPage: true })
  check('U2', 'Admin 登录成功', true)

  await browser.close()
}

async function main() {
  suiteDocsAndBuild()
  await suiteDemoPay()
  await suiteUi()
  const passed = results.filter((r) => r.pass).length
  const failed = results.filter((r) => !r.pass)
  const report = {
    milestone: 'M9',
    passed,
    failed: failed.length,
    results,
    access: {
      api: BASE,
      h5: H5,
      admin: ADMIN,
      note: '公网 VPS URL 待主机提供后写入 M9-验收记录',
    },
    demo: {
      member: 'demo@sistermart.local / demo123456',
      admin: 'admin@sistermart.local / admin123456',
      ops: 'ops@sistermart.local / ops123456',
    },
    at: new Date().toISOString(),
  }
  fs.writeFileSync(path.join(OUT, 'm9-accept/report.json'), JSON.stringify(report, null, 2))
  console.log(`\nM9 ${passed}/${results.length} passed`)
  if (failed.length) {
    console.error(failed)
    process.exit(1)
  }
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
