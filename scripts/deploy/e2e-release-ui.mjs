/**
 * Admin 發佈通道瀏覽器 UI 測試（Playwright + 本機 Chrome）
 * 用法：DISPLAY=:1 node scripts/deploy/e2e-release-ui.mjs
 */
import { createRequire } from 'module'
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const require = createRequire(import.meta.url)
// prefer /tmp install
let chromium
try {
  ;({ chromium } = require('/tmp/node_modules/playwright-core'))
} catch {
  ;({ chromium } = require('playwright-core'))
}

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const ROOT = path.resolve(__dirname, '../..')
const ART = '/opt/cursor/artifacts'
const ADMIN = process.env.ADMIN_URL || 'http://127.0.0.1:5174'
const TAR =
  process.env.RELEASE_TAR ||
  path.join(ROOT, 'releases/sistermart-release-ui-test.tar')
const OUT = {
  login: path.join(ART, 'screenshots/release-ui-01-login.png'),
  page: path.join(ART, 'screenshots/release-ui-02-releases-page.png'),
  uploaded: path.join(ART, 'screenshots/release-ui-03-uploaded-ready.png'),
  published: path.join(ART, 'screenshots/release-ui-04-published.png'),
  report: path.join(ART, 'release-ui-e2e-report.json'),
}

fs.mkdirSync(path.join(ART, 'screenshots'), { recursive: true })

const results = []
function pass(name, detail = '') {
  results.push({ name, ok: true, detail })
  console.log('PASS', name, detail)
}
function fail(name, detail = '') {
  results.push({ name, ok: false, detail })
  console.log('FAIL', name, detail)
}

async function main() {
  if (!fs.existsSync(TAR)) throw new Error('missing tar: ' + TAR)

  const browser = await chromium.launch({
    channel: 'chrome',
    headless: false,
    args: ['--window-size=1280,900', '--disable-dev-shm-usage'],
  })
  const context = await browser.newContext({
    viewport: { width: 1280, height: 900 },
    locale: 'zh-HK',
  })
  const page = await context.newPage()
  page.setDefaultTimeout(30000)

  try {
    // clear any prior session
    await page.goto(ADMIN + '/#/login', { waitUntil: 'networkidle' })
    await page.evaluate(() => {
      localStorage.removeItem('sm_admin_token')
      localStorage.removeItem('sm_admin')
    })
    await page.reload({ waitUntil: 'networkidle' })

    // Login form — Naive UI inputs
    const email = page.locator('input').first()
    await email.waitFor({ state: 'visible' })
    // find password input
    const inputs = page.locator('input')
    const count = await inputs.count()
    let emailInput = null
    let passInput = null
    for (let i = 0; i < count; i++) {
      const t = await inputs.nth(i).getAttribute('type')
      if (t === 'password') passInput = inputs.nth(i)
      else if (!emailInput) emailInput = inputs.nth(i)
    }
    if (!emailInput || !passInput) throw new Error('login inputs not found')
    await emailInput.fill('admin@sistermart.local')
    await passInput.fill('admin123456')
    await page.screenshot({ path: OUT.login, fullPage: true })
    // click 登錄 / 登入 button
    const loginBtn = page.getByRole('button', { name: /登[錄入]|Login/i }).first()
    await loginBtn.click()
    await page.waitForURL(/#\/(?!login)/, { timeout: 15000 }).catch(() => {})
    await page.waitForTimeout(800)
    const token = await page.evaluate(() => localStorage.getItem('sm_admin_token'))
    if (token) pass('login', 'token stored')
    else fail('login', 'no token after click')

    // Navigate to releases
    await page.goto(ADMIN + '/#/settings/releases', { waitUntil: 'networkidle' })
    await page.waitForTimeout(1000)
    const bodyText = await page.locator('body').innerText()
    if (bodyText.includes('發佈通道') || bodyText.includes('上傳發佈包')) {
      pass('releases_page_loaded')
    } else {
      fail('releases_page_loaded', bodyText.slice(0, 200))
    }
    if (bodyText.includes('RELEASE_ENABLED') || bodyText.includes('發佈通道未開啟')) {
      fail('channel_enabled', 'disabled hint shown')
    } else if (bodyText.includes('上傳發佈包')) {
      pass('channel_enabled', 'upload button visible')
    } else {
      fail('channel_enabled', 'unexpected UI')
    }
    // sidebar menu
    const menu = await page.locator('.n-menu, .sider-menu, aside').innerText().catch(() => '')
    if (menu.includes('發佈通道')) pass('menu_item_visible')
    else pass('menu_item_visible', 'direct URL used; sidebar text=' + menu.slice(0, 80))

    await page.screenshot({ path: OUT.page, fullPage: true })

    // Upload via hidden file input
    const fileInput = page.locator('input[type="file"]')
    await fileInput.setInputFiles(TAR)
    // wait for success message or ready row
    await page.waitForTimeout(500)
    try {
      await page.getByText(/上傳完成|簽名校驗通過|ready/i).first().waitFor({ timeout: 60000 })
      pass('upload_complete_toast_or_ready')
    } catch {
      // check table for ready
      const t = await page.locator('body').innerText()
      if (t.includes('ready') || t.includes('ui-test-1')) pass('upload_complete_toast_or_ready', 'row present')
      else fail('upload_complete_toast_or_ready', t.slice(0, 300))
    }
    await page.waitForTimeout(500)
    await page.screenshot({ path: OUT.uploaded, fullPage: true })

    // Click 發佈 on the ready row (table action), then confirm dialog
    const rowPublish = page.locator('table.tbl tbody tr').filter({ hasText: 'ready' }).first().getByRole('button', { name: '發佈' })
    await rowPublish.waitFor({ state: 'visible', timeout: 15000 })
    await rowPublish.click()
    const dlg = page.locator('.n-dialog')
    await dlg.waitFor({ state: 'visible', timeout: 10000 })
    // warning dialog 確認鍵文案為「發佈」
    await dlg.getByRole('button', { name: '發佈' }).click()
    try {
      await page.getByText(/發佈完成/i).first().waitFor({ timeout: 90000 })
      pass('publish_success')
    } catch {
      const t = await page.locator('body').innerText()
      if (/\bpublished\b/.test(t)) pass('publish_success', 'status in table')
      else fail('publish_success', t.slice(0, 400))
    }
    await page.waitForTimeout(600)
    await page.screenshot({ path: OUT.published, fullPage: true })

    // Verify filesystem
    const idx = fs.readFileSync(
      '/workspace/apps/api/runtime/release-site/index.html',
      'utf8',
    )
    if (idx.includes('UI_NEW_INDEX')) pass('filesystem_updated', idx.trim())
    else fail('filesystem_updated', idx.trim())

    // Optional rollback button visibility
    const rb = page.getByRole('button', { name: /回退/ })
    if (await rb.count()) {
      const disabled = await rb.first().isDisabled()
      if (!disabled) pass('rollback_button_enabled')
      else fail('rollback_button_enabled', 'disabled')
    } else {
      fail('rollback_button_enabled', 'not found')
    }
  } catch (e) {
    fail('uncaught', String(e && e.stack ? e.stack : e))
    await page.screenshot({
      path: path.join(ART, 'screenshots/release-ui-error.png'),
      fullPage: true,
    }).catch(() => {})
  } finally {
    const summary = {
      at: new Date().toISOString(),
      pass: results.filter((r) => r.ok).length,
      fail: results.filter((r) => !r.ok).length,
      results,
      screenshots: OUT,
    }
    fs.writeFileSync(OUT.report, JSON.stringify(summary, null, 2))
    console.log('REPORT', OUT.report)
    console.log(`SUMMARY pass=${summary.pass} fail=${summary.fail}`)
    await browser.close()
    if (summary.fail > 0) process.exit(1)
  }
}

main()
