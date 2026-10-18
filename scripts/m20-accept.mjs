/**
 * SisterMart M20: admin distribution UI APIs (commission product + withdrawals + ranking).
 */
import fs from 'node:fs'
import path from 'node:path'

const BASE = process.env.API_BASE || 'http://127.0.0.1:8000'
const OUT = '/opt/cursor/artifacts/m20-accept'
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
  const adminToken = await loginAdmin()

  const products = await api('GET', '/api/v1/admin/distribution/products', {
    token: adminToken,
    expectHttp: 200,
  })
  check('A1', '商品佣金一覽可用', ok(products.json) && Array.isArray(products.json.data.items))

  const p1 = await api('GET', '/api/v1/admin/products/p1', { token: adminToken, expectHttp: 200 })
  check('A2', '讀取商品詳情', ok(p1.json) && p1.json.data.db_id)

  const body = {
    code: p1.json.data.code,
    category_id: p1.json.data.category_id,
    status: p1.json.data.status,
    sold_count: p1.json.data.sold_count,
    name: p1.json.data.name,
    detail: p1.json.data.detail,
    badges: p1.json.data.badges || [],
    service_tag_ids: p1.json.data.service_tag_ids || [],
    media: p1.json.data.media || [],
    spec_groups: (p1.json.data.spec_groups || []).map((g) => ({
      code: g.code,
      name: g.name,
      options: (g.options || []).map((o) => ({ code: o.code, name: o.name, image: o.image })),
    })),
    skus: (p1.json.data.skus || []).map((s) => ({
      code: s.code,
      price: s.price,
      compare_at: s.compare_at,
      stock: s.stock,
      image: s.image,
      option_refs: Object.entries(s.selected || {}).map(([k, v]) => `${k}:${v}`),
    })),
    commission_type: 'percent',
    commission_value: 8.5,
  }
  // option_refs from selected may be wrong shape — prefer existing option_refs if present
  body.skus = (p1.json.data.skus || []).map((s) => ({
    code: s.code,
    price: s.price,
    compare_at: s.compare_at,
    stock: s.stock,
    image: s.image,
    option_refs: s.option_refs || Object.entries(s.selected || {}).map(([k, v]) => `${k}:${v}`),
  }))

  const saved = await api('PUT', `/api/v1/admin/products/${p1.json.data.db_id}`, {
    token: adminToken,
    body,
    expectHttp: 200,
  })
  check(
    'A3',
    '保存商品佣金',
    ok(saved.json) &&
      saved.json.data.commission_type === 'percent' &&
      Number(saved.json.data.commission_value) === 8.5,
    JSON.stringify({
      type: saved.json.data?.commission_type,
      value: saved.json.data?.commission_value,
    }),
  )

  const ranking = await api('GET', '/api/v1/admin/distribution/ranking?period=total', {
    token: adminToken,
    expectHttp: 200,
  })
  check('A4', '排行接口可用', ok(ranking.json) && Array.isArray(ranking.json.data.items))

  const commissions = await api('GET', '/api/v1/admin/distribution/commissions', {
    token: adminToken,
    expectHttp: 200,
  })
  check('A5', '佣金明細接口可用', ok(commissions.json) && Array.isArray(commissions.json.data.items))

  const withdrawals = await api('GET', '/api/v1/admin/withdrawals', {
    token: adminToken,
    expectHttp: 200,
  })
  check('A6', '提現列表可用', ok(withdrawals.json) && Array.isArray(withdrawals.json.data.items))

  // create a pending FPS withdrawal via member if possible
  const email = `m20w${Date.now()}@test.local`
  const codeRes = await api('POST', '/api/v1/verification-codes', {
    body: { channel: 'email', purpose: 'register', target: email },
  })
  const reg = await api('POST', '/api/v1/members', {
    body: { channel: 'email', email, password: 'secret123', code: codeRes.json.data.debug_code, agreed: true },
  })
  const memberToken = reg.json.data.token
  const memberId = reg.json.data.member.id
  // give commission_available
  const { execSync } = await import('node:child_process')
  execSync(
    `mysql -usistermart -psistermart -h127.0.0.1 sistermart -e "UPDATE members SET commission_available=200 WHERE id=${memberId}"`,
    { stdio: 'inherit' },
  )
  const wd = await api('POST', '/api/v1/distribution/withdrawals', {
    token: memberToken,
    body: { channel: 'fps', amount: 100, fps_name: 'M20', fps_id: '90002020' },
  })
  const wdId = wd.json.data?.withdrawal_id || wd.json.data?.id
  check(
    'A7',
    '會員可申請 FPS 提現',
    ok(wd.json) && wd.json.data?.status === 'pending_review' && wdId,
    JSON.stringify({ status: wd.json.data?.status, id: wdId, err: wd.json.error || wd.json.message }),
  )

  if (!wdId) {
    throw new Error('no withdrawal id: ' + JSON.stringify(wd.json))
  }
  const approved = await api('POST', `/api/v1/admin/withdrawals/${wdId}/review`, {
    token: adminToken,
    body: { action: 'approve', note: 'M20 accept' },
    expectHttp: 200,
  })
  check(
    'A8',
    '後台可審核通過提現',
    ok(approved.json) && approved.json.data.status !== 'pending_review',
    approved.json.data?.status,
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
