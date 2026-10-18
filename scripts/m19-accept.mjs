/**
 * SisterMart M19: home banners real API + admin CRUD.
 */
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'

const BASE = process.env.API_BASE || 'http://127.0.0.1:8000'
const OUT = '/opt/cursor/artifacts/m19-accept'
fs.mkdirSync(OUT, { recursive: true })

const results = []
function check(id, title, pass, detail = '') {
  results.push({ id, title, pass: Boolean(pass), detail: String(detail || '') })
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${id}  ${title}${detail ? ' — ' + detail : ''}`)
}

async function api(method, urlPath, { body, token, expectHttp, formData } = {}) {
  const headers = { Accept: 'application/json' }
  if (token) headers.Authorization = `Bearer ${token}`
  if (body !== undefined && !formData) headers['Content-Type'] = 'application/json'
  const res = await fetch(`${BASE}${urlPath}`, {
    method,
    headers,
    body: formData || (body === undefined ? undefined : JSON.stringify(body)),
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
  const adminToken = await loginAdmin()

  const pub = await api('GET', '/api/v1/home/banners', { expectHttp: 200 })
  check('A1', '公開輪播 ≥1', ok(pub.json) && (pub.json.data.items || []).length >= 1, `n=${(pub.json.data.items || []).length}`)

  const admin = await api('GET', '/api/v1/admin/home-banners', { token: adminToken, expectHttp: 200 })
  check('A2', '後台可列輪播', ok(admin.json) && (admin.json.data.items || []).length >= 1)

  const created = await api('POST', '/api/v1/admin/home-banners', {
    token: adminToken,
    body: {
      image_url: '/mock/home-banner-1.png',
      title: { 'zh-HK': 'M19 驗收', en: 'M19 accept' },
      link_type: 'product',
      link_value: 'p1',
      sort_order: 5,
      status: 1,
    },
    expectHttp: 201,
  })
  check('A3', '後台可新建', ok(created.json) && created.json.data.link_type === 'product', created.json.data?.id)

  const pub2 = await api('GET', '/api/v1/home/banners', { expectHttp: 200 })
  check(
    'A4',
    '公開列表含新建項',
    (pub2.json.data.items || []).some((b) => b.id === created.json.data.id),
  )

  const disabled = await api('PUT', `/api/v1/admin/home-banners/${created.json.data.id}`, {
    token: adminToken,
    body: {
      image_url: '/mock/home-banner-1.png',
      title: { 'zh-HK': 'M19 停用', en: 'off' },
      link_type: 'product',
      link_value: 'p1',
      sort_order: 5,
      status: 0,
    },
    expectHttp: 200,
  })
  check('A5', '可停用', ok(disabled.json) && disabled.json.data.status === 0)

  const pub3 = await api('GET', '/api/v1/home/banners', { expectHttp: 200 })
  check(
    'A6',
    '停用後公開列表消失',
    !(pub3.json.data.items || []).some((b) => b.id === created.json.data.id),
  )

  const productLink = (pub3.json.data.items || []).find((b) => b.link_type === 'product' && b.link_value)
  check('A7', '存在可跳轉商品輪播', Boolean(productLink), productLink?.link_value)

  await api('DELETE', `/api/v1/admin/home-banners/${created.json.data.id}`, {
    token: adminToken,
    expectHttp: 200,
  })
  check('A8', '可刪除輪播', true)

  const failed = results.filter((r) => !r.pass)
  fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify({ results, failed: failed.length }, null, 2))
  console.log(`\n${results.length - failed.length}/${results.length} passed`)
  if (failed.length) process.exit(1)
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
