/**
 * SisterMart M24: home marquee + footer slogan (with M19 banners smoke).
 */
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'

const BASE = process.env.API_BASE || 'http://127.0.0.1:8000'
const OUT = '/opt/cursor/artifacts/m24-accept'
fs.mkdirSync(OUT, { recursive: true })

const results = []
function check(id, title, pass, detail = '') {
  results.push({ id, title, pass: Boolean(pass), detail: String(detail || '') })
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${id}  ${title}${detail ? ' — ' + detail : ''}`)
}

async function api(method, urlPath, { body, token, expectHttp } = {}) {
  const headers = { Accept: 'application/json' }
  if (token) headers.Authorization = `Bearer ${token}`
  if (body !== undefined) headers['Content-Type'] = 'application/json'
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

async function loginAdmin() {
  const res = await api('POST', '/api/v1/admin/sessions', {
    body: { email: 'admin@sistermart.local', password: 'admin123456' },
  })
  return res.json.data.token
}

async function main() {
  execSync('php database/m19_seed.php', { cwd: path.resolve('apps/api'), stdio: 'inherit' })
  execSync('php database/m24_seed.php', { cwd: path.resolve('apps/api'), stdio: 'inherit' })

  const adminToken = await loginAdmin()

  const banners = await api('GET', '/api/v1/home/banners', { expectHttp: 200 })
  check(
    'A1',
    '公開輪播可用',
    ok(banners.json) && Array.isArray(banners.json.data.items) && banners.json.data.items.length >= 1,
    `n=${banners.json.data?.items?.length}`,
  )

  const ops0 = await api('GET', '/api/v1/home/ops', { expectHttp: 200 })
  check(
    'A2',
    '公開字幕含雙語文案',
    ok(ops0.json) &&
      ops0.json.data.marquee &&
      ops0.json.data.marquee.enabled === true &&
      ops0.json.data.marquee.text['zh-HK'] &&
      ops0.json.data.marquee.text.en,
    JSON.stringify(ops0.json.data?.marquee?.text),
  )

  const updated = await api('PUT', '/api/v1/admin/home-ops', {
    token: adminToken,
    body: {
      marquee: {
        text_zh: 'M24 字幕驗收 · 點我',
        text_en: 'M24 marquee accept',
        link_type: 'product',
        link_value: 'p1',
        status: 1,
      },
      footer_slogan: {
        'zh-HK': '* M24 標語 *',
        en: '* M24 slogan *',
      },
    },
    expectHttp: 200,
  })
  check(
    'A3',
    '後台更新字幕與標語',
    ok(updated.json) &&
      updated.json.data.marquee.text['zh-HK'] === 'M24 字幕驗收 · 點我' &&
      updated.json.data.footer_slogan['zh-HK'] === '* M24 標語 *',
    JSON.stringify(updated.json.data?.marquee?.text),
  )

  const ops1 = await api('GET', '/api/v1/home/ops', { expectHttp: 200 })
  check(
    'A4',
    '公開讀到更新後字幕與商品跳轉',
    ok(ops1.json) &&
      ops1.json.data.marquee.text['zh-HK'] === 'M24 字幕驗收 · 點我' &&
      ops1.json.data.marquee.link_type === 'product' &&
      ops1.json.data.marquee.link_value === 'p1' &&
      ops1.json.data.footer_slogan.en === '* M24 slogan *',
    JSON.stringify(ops1.json.data?.marquee),
  )

  await api('PUT', '/api/v1/admin/home-ops', {
    token: adminToken,
    body: {
      marquee: {
        text_zh: '隱藏字幕',
        text_en: 'hidden',
        link_type: 'none',
        link_value: '',
        status: 0,
      },
    },
    expectHttp: 200,
  })
  const opsOff = await api('GET', '/api/v1/home/ops', { expectHttp: 200 })
  check(
    'A5',
    '停用字幕後公開 marquee 為 null',
    ok(opsOff.json) && opsOff.json.data.marquee === null,
    JSON.stringify(opsOff.json.data?.marquee),
  )

  const adminStill = await api('GET', '/api/v1/admin/home-ops', {
    token: adminToken,
    expectHttp: 200,
  })
  check(
    'A6',
    '後台仍可讀停用字幕',
    ok(adminStill.json) &&
      adminStill.json.data.marquee.status === 0 &&
      adminStill.json.data.marquee.text['zh-HK'] === '隱藏字幕',
  )

  const bad = await api('PUT', '/api/v1/admin/home-ops', {
    token: adminToken,
    body: {
      marquee: {
        text_zh: '',
        text_en: '',
        link_type: 'none',
        status: 1,
      },
    },
  })
  check(
    'A7',
    '空字幕文案被拒',
    !ok(bad.json) && (bad.status === 422 || bad.json.code !== 0),
    bad.json.message,
  )

  // restore enabled for later demos
  await api('PUT', '/api/v1/admin/home-ops', {
    token: adminToken,
    body: {
      marquee: {
        text_zh: '首次下單最高享 4 折優惠 · 立即選購',
        text_en: 'Get up to 40% off on your 1st order · Shop now',
        link_type: 'none',
        link_value: '',
        status: 1,
      },
      footer_slogan: {
        'zh-HK': '* 我們守護您的美麗 *',
        en: '* We guard your beauty *',
      },
    },
    expectHttp: 200,
  })

  const bannersAdmin = await api('GET', '/api/v1/admin/home-banners', {
    token: adminToken,
    expectHttp: 200,
  })
  check(
    'A8',
    '輪播 CRUD 未回歸（後台列表≥1）',
    ok(bannersAdmin.json) && (bannersAdmin.json.data.items || []).length >= 1,
    `n=${bannersAdmin.json.data?.items?.length}`,
  )

  const failed = results.filter((r) => !r.pass)
  fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify({ results, failed: failed.length }, null, 2))
  console.log(`\n${results.length - failed.length}/${results.length} passed`)
  if (failed.length) process.exit(1)
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
