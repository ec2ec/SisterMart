/**
 * SisterMart M3 acceptance: catalog + content.
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
fs.mkdirSync(path.join(OUT, 'm3-accept'), { recursive: true })

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
    throw new Error(`HTTP ${res.status} expected ${expectHttp}: ${text.slice(0, 300)}`)
  }
  return { status: res.status, json }
}

function ok(json) {
  return json && json.code === 0 && 'data' in json
}

async function suiteApi() {
  const cats = await api('GET', '/api/v1/categories', { expectHttp: 200 })
  check('A1', '分類樹含一級與子類', ok(cats.json) && (cats.json.data.items || []).some((c) => (c.children || []).length > 0), `roots=${cats.json.data?.items?.length}`)

  const list = await api('GET', '/api/v1/products', { expectHttp: 200 })
  check('A2', '商品列表至少 4 個', ok(list.json) && list.json.data.items.length >= 4, `n=${list.json.data?.items?.length}`)

  const p4 = await api('GET', '/api/v1/products/p4', { expectHttp: 200 })
  const skus = p4.json.data?.skus || []
  const prices = new Set(skus.map((s) => s.price))
  const stocks = new Set(skus.map((s) => s.stock))
  check('A3', 'p4 多 SKU 價格庫存獨立', ok(p4.json) && skus.length >= 6 && prices.size > 1 && stocks.size > 1, `skus=${skus.length} prices=${[...prices]} stocks=${[...stocks]}`)
  check('A4', '存在庫存為 0 的 SKU', skus.some((s) => s.stock === 0), skus.filter((s) => s.stock === 0).map((s) => s.code).join(','))

  const enName = p4.json.data?.name?.en
  const zhName = p4.json.data?.name?.['zh-HK']
  check('A5', '商品雙語名稱齊全', Boolean(enName && zhName && enName !== zhName), `${zhName} / ${enName}`)

  const search = await api('GET', '/api/v1/products?q=Heart', { expectHttp: 200 })
  check('A6', '搜索 Heart 命中紅心手鏈', ok(search.json) && search.json.data.items.some((p) => p.code === 'p4'))

  const byCat = await api('GET', '/api/v1/products?category=bracelets', { expectHttp: 200 })
  check('A7', '大類 bracelets 含下屬商品', ok(byCat.json) && byCat.json.data.items.some((p) => p.code === 'p4' || p.code === 'p3'))

  const terms = await api('GET', '/api/v1/articles/terms', { expectHttp: 200 })
  check('A8', '協議文章 terms 可讀', ok(terms.json) && terms.json.data.code === 'terms' && terms.json.data.title['zh-HK'])

  const login = await api('POST', '/api/v1/admin/sessions', {
    body: { email: 'admin@sistermart.local', password: 'admin123456' },
    expectHttp: 201,
  })
  const token = login.json.data?.token
  check('A9', '後台可登錄', ok(login.json) && Boolean(token))

  const adminProducts = await api('GET', '/api/v1/admin/products', { token, expectHttp: 200 })
  check('A10', '後台商品列表', ok(adminProducts.json) && adminProducts.json.data.items.length >= 4)

  const adminCats = await api('GET', '/api/v1/admin/categories', { token, expectHttp: 200 })
  check('A11', '後台分類樹', ok(adminCats.json) && adminCats.json.data.items.length >= 1)

  const stamp = Date.now().toString(36)
  const create = await api('POST', '/api/v1/admin/products', {
    token,
    expectHttp: 201,
    body: {
      code: `t-${stamp}`,
      category_id: adminCats.json.data.items[0].id,
      status: 1,
      sold_count: 3,
      name: { 'zh-HK': `測試商品${stamp}`, en: `Test ${stamp}` },
      detail: { 'zh-HK': '詳情', en: 'Detail' },
      badges: ['new'],
      service_tag_ids: [],
      media: [{ media_type: 'image', url: '/mock/%E7%94%A2%E5%93%81%E5%9C%96-1.jpg' }],
      spec_groups: [
        {
          code: 'style',
          name: { 'zh-HK': '款式', en: 'Style' },
          options: [
            { code: 'a', name: { 'zh-HK': 'A', en: 'A' } },
            { code: 'b', name: { 'zh-HK': 'B', en: 'B' } },
          ],
        },
      ],
      skus: [
        { code: 'a', price: 10, compare_at: 20, stock: 5, option_refs: ['style:a'] },
        { code: 'b', price: 12, compare_at: 22, stock: 0, option_refs: ['style:b'] },
      ],
    },
  })
  check('A12', '後台可上架帶 SKU 的新商品', ok(create.json) && create.json.data.skus?.length === 2, create.json.data?.code)

  const sold = await api('PUT', '/api/v1/admin/settings/sold-display', {
    token,
    expectHttp: 200,
    body: { sold_display_mode: 'exact' },
  })
  check('A13', '已售展示可改為 exact', ok(sold.json) && sold.json.data.sold_display_mode === 'exact')
  await api('PUT', '/api/v1/admin/settings/sold-display', {
    token,
    expectHttp: 200,
    body: { sold_display_mode: 'fuzzy' },
  })
}

async function suiteUi() {
  const browser = await chromium.launch({ headless: true })
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    locale: 'zh-HK',
  })
  const page = await context.newPage()

  await page.goto(`${H5}/category`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(500)
  const sideCount = await page.locator('.van-sidebar-item').count()
  check('H1', '分類頁左側有大類', sideCount >= 3, `sides=${sideCount}`)
  await page.locator('.van-sidebar-item').nth(1).click()
  await page.waitForTimeout(300)
  const allCell = page.getByText('全部商品').first()
  check('H2', '右側含「全部商品」', await allCell.isVisible())
  await page.screenshot({ path: path.join(SHOT, 'm3-category.png'), fullPage: true })

  await page.goto(`${H5}/product/p4`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(600)
  const title = await page.locator('h1').first().innerText()
  check('H3', '詳情頁載入紅心手鏈', title.includes('紅心') || title.includes('手鏈'), title)
  await page.getByRole('button', { name: '金色' }).click()
  await page.getByRole('button', { name: '18 cm' }).click()
  await page.waitForTimeout(200)
  const stockText = await page.locator('.sub').innerText()
  check('H4', '切到無貨 SKU 顯示庫存 0', /0/.test(stockText), stockText)
  const addDisabled = await page.locator('.buybar .ghost').isDisabled()
  check('H5', '庫存 0 時加購按鈕禁用', addDisabled)
  await page.screenshot({ path: path.join(SHOT, 'm3-pdp-oos.png'), fullPage: true })

  await page.getByRole('button', { name: '紅色愛心' }).click()
  await page.getByRole('button', { name: '16 cm' }).click()
  await page.waitForTimeout(200)
  await page.locator('.gallery .shot').first().click()
  await page.waitForTimeout(400)
  const zoomOk = (await page.locator('.viewer').count()) > 0
  check('H6', '點圖進入全屏看圖', zoomOk, zoomOk ? 'viewer open' : 'viewer missing')
  await page.screenshot({ path: path.join(SHOT, 'm3-pdp-zoom.png'), fullPage: true })
  await page.locator('.viewer .bar button').first().click().catch(() => {})

  await page.goto(`${H5}/search`, { waitUntil: 'networkidle' })
  await page.fill('.van-field__control', '葫蘆')
  await page.waitForTimeout(500)
  const found = await page.getByText('葫蘆項鏈').count()
  check('H7', '搜索葫蘆命中商品卡', found > 0, `hits=${found}`)
  await page.screenshot({ path: path.join(SHOT, 'm3-search.png'), fullPage: true })

  await page.addInitScript(() => localStorage.setItem('sm_locale', 'en'))
  await page.goto(`${H5}/product/p4`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(500)
  const enTitle = await page.locator('h1').first().innerText()
  check('H8', '英文語言下商品名切換', /Heart|Bracelet/i.test(enTitle), enTitle)
  await page.screenshot({ path: path.join(SHOT, 'm3-pdp-en.png'), fullPage: true })

  await page.goto(`${H5}/article/terms`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(400)
  const articleBody = await page.locator('article p').innerText()
  check('H9', '文章頁讀真實協議', articleBody.length > 20, articleBody.slice(0, 40))
  await page.screenshot({ path: path.join(SHOT, 'm3-article.png'), fullPage: true })

  const admin = await browser.newContext({ viewport: { width: 1280, height: 800 } })
  const ap = await admin.newPage()
  await ap.goto(`${ADMIN}/login`, { waitUntil: 'networkidle' })
  await ap.locator('input').nth(0).fill('admin@sistermart.local')
  await ap.locator('input').nth(1).fill('admin123456')
  await ap.locator('button[type="submit"], button.n-button--primary-type').first().click()
  await ap.waitForFunction(() => Boolean(localStorage.getItem('sm_admin_token')), null, { timeout: 10000 })
  await ap.goto(`${ADMIN}/catalog/categories`, { waitUntil: 'networkidle' })
  await ap.waitForTimeout(800)
  const catOk = (await ap.getByText('分類列表').count()) > 0
  check('AD1', '後台分類頁可開', catOk, `url=${ap.url()} body=${(await ap.locator('body').innerText()).slice(0, 80)}`)
  await ap.screenshot({ path: path.join(SHOT, 'm3-admin-categories.png'), fullPage: true })

  await ap.goto(`${ADMIN}/catalog/products`, { waitUntil: 'networkidle' })
  await ap.waitForTimeout(800)
  const prodOk = (await ap.getByText('紅心手鏈').count()) > 0 || (await ap.getByText('p4').count()) > 0
  check('AD2', '後台商品列表可開', prodOk)
  await ap.screenshot({ path: path.join(SHOT, 'm3-admin-products.png'), fullPage: true })

  await ap.goto(`${ADMIN}/cms/articles`, { waitUntil: 'networkidle' })
  await ap.waitForTimeout(800)
  check('AD3', '後台文章頁可開', (await ap.getByText('terms').count()) > 0)
  await ap.screenshot({ path: path.join(SHOT, 'm3-admin-articles.png'), fullPage: true })

  await browser.close()
}

async function main() {
  await suiteApi()
  await suiteUi()
  const passed = results.filter((r) => r.pass).length
  const failed = results.filter((r) => !r.pass)
  const report = {
    milestone: 'M3',
    generated_at: new Date().toISOString(),
    summary: { total: results.length, passed, failed: failed.length },
    results,
  }
  fs.writeFileSync(path.join(OUT, 'm3-accept', 'report.json'), JSON.stringify(report, null, 2))
  console.log(`\nM3 acceptance: ${passed}/${results.length} passed`)
  if (failed.length) {
    console.log('Failed:', failed.map((f) => f.id).join(', '))
    process.exitCode = 1
  }
}

main().catch((err) => {
  console.error(err)
  process.exit(1)
})
