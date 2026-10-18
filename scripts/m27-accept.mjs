/**
 * SisterMart M27: newsletter subscribe + system message (targets / email / HTML).
 */
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'

const BASE = process.env.API_BASE || 'http://127.0.0.1:8000'
const OUT = '/opt/cursor/artifacts/m27-accept'
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

async function registerMember(prefix) {
  const email = `${prefix}${Date.now()}@test.local`
  const codeRes = await api('POST', '/api/v1/verification-codes', {
    body: { channel: 'email', purpose: 'register', target: email },
  })
  const code = codeRes.json.data.debug_code
  const reg = await api('POST', '/api/v1/members', {
    body: { channel: 'email', email, password: 'secret123', code, agreed: true },
  })
  if (!ok(reg.json)) throw new Error('register failed ' + JSON.stringify(reg.json))
  return {
    token: reg.json.data.token,
    email,
    id: reg.json.data.member?.id || reg.json.data.id,
  }
}

async function loginAdmin() {
  const res = await api('POST', '/api/v1/admin/sessions', {
    body: { email: 'admin@sistermart.local', password: 'admin123456' },
  })
  return res.json.data.token
}

async function main() {
  execSync('php database/m27_seed.php', { cwd: path.resolve('apps/api'), stdio: 'inherit' })

  const adminToken = await loginAdmin()
  const a = await registerMember('m27a')
  const b = await registerMember('m27b')

  const me0 = await api('GET', '/api/v1/newsletter/me', { token: a.token, expectHttp: 200 })
  check('A1', '未訂閱狀態', ok(me0.json) && me0.json.data.subscribed === false)

  const sub = await api('POST', '/api/v1/newsletter/subscribe', {
    token: a.token,
    body: { email: a.email },
    expectHttp: 201,
  })
  check('A2', '訂閱成功', ok(sub.json) && sub.json.data.status === 1, sub.json.data?.email)

  const me1 = await api('GET', '/api/v1/newsletter/me', { token: a.token, expectHttp: 200 })
  check('A3', '訂閱後 me.subscribed=true', me1.json.data.subscribed === true)

  const list = await api('GET', '/api/v1/admin/newsletter-subscribers?status=1', {
    token: adminToken,
    expectHttp: 200,
  })
  const row = (list.json.data.items || []).find((r) => r.email === a.email)
  check('A4', '後台名單含訂閱', Boolean(row), row?.id)

  const closed = await api('POST', `/api/v1/admin/newsletter-subscribers/${row.id}/status`, {
    token: adminToken,
    body: { status: 0 },
    expectHttp: 200,
  })
  check('A5', '關閉訂閱', ok(closed.json) && closed.json.data.status === 0)

  const me2 = await api('GET', '/api/v1/newsletter/me', { token: a.token, expectHttp: 200 })
  check('A6', '關閉後前端視為未訂閱', me2.json.data.subscribed === false)

  await api('POST', `/api/v1/admin/newsletter-subscribers/${row.id}/status`, {
    token: adminToken,
    body: { status: 1 },
    expectHttp: 200,
  })
  const del = await api('DELETE', `/api/v1/admin/newsletter-subscribers/${row.id}`, {
    token: adminToken,
    expectHttp: 200,
  })
  check('A7', '刪除訂閱', ok(del.json))

  const guestEmail = `guest${Date.now()}@test.local`
  const guestSub = await api('POST', '/api/v1/newsletter/subscribe', {
    body: { email: guestEmail },
    expectHttp: 201,
  })
  check('A8', '訪客可訂閱', ok(guestSub.json) && guestSub.json.data.email === guestEmail)

  const msg = await api('POST', '/api/v1/admin/messages', {
    token: adminToken,
    body: {
      title_zh: 'M27 富文本',
      title_en: 'M27 rich',
      body_zh: '<p>你好 <b>SisterMart</b></p>',
      body_en: '<p>Hello <b>SisterMart</b></p>',
      member_ids: [a.id],
      send_email: true,
    },
    expectHttp: 201,
  })
  check(
    'A9',
    '指定用戶推送+郵件入隊',
    ok(msg.json) &&
      msg.json.data.scope === 'selected' &&
      msg.json.data.sent === 1 &&
      msg.json.data.email_queued >= 1,
    JSON.stringify(msg.json.data),
  )

  const inbox = await api('GET', '/api/v1/messages?type=system', { token: a.token, expectHttp: 200 })
  const items = inbox.json.data.items || inbox.json.data.system || []
  check(
    'A10',
    '指定用戶收到站內信含 HTML',
    items.some((m) => String(m.body?.['zh-HK'] || m.body || '').includes('<b>SisterMart</b>')),
  )

  const other = await api('GET', '/api/v1/messages?type=system', { token: b.token, expectHttp: 200 })
  const otherItems = other.json.data.items || other.json.data.system || []
  check(
    'A11',
    '未選用戶收不到該批',
    !otherItems.some((m) => String(m.body?.['zh-HK'] || '').includes('<b>SisterMart</b>')),
  )

  const broadcast = await api('POST', '/api/v1/admin/messages', {
    token: adminToken,
    body: {
      title_zh: '全局廣播',
      body_zh: '<p>全員可見</p>',
      send_email: false,
    },
    expectHttp: 201,
  })
  check(
    'A12',
    '全局推送',
    ok(broadcast.json) && broadcast.json.data.scope === 'all' && broadcast.json.data.sent >= 2,
    String(broadcast.json.data?.sent),
  )

  const batches = await api('GET', '/api/v1/admin/messages', { token: adminToken, expectHttp: 200 })
  check(
    'A13',
    '批次列表含 scope/send_email',
    (batches.json.data.items || []).some(
      (r) => r.batch_id === msg.json.data.batch_id && r.scope === 'selected' && r.send_email === true,
    ),
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
