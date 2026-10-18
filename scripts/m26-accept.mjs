/**
 * SisterMart M26: browsing history + balance ledger pages.
 */
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'

const BASE = process.env.API_BASE || 'http://127.0.0.1:8000'
const OUT = '/opt/cursor/artifacts/m26-accept'
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
  return { token: reg.json.data.token, id: reg.json.data.id }
}

async function loginAdmin() {
  const res = await api('POST', '/api/v1/admin/sessions', {
    body: { email: 'admin@sistermart.local', password: 'admin123456' },
  })
  return res.json.data.token
}

async function main() {
  execSync('php database/m26_seed.php', { cwd: path.resolve('apps/api'), stdio: 'inherit' })

  const adminToken = await loginAdmin()
  const { token } = await registerMember('m26')

  const empty = await api('GET', '/api/v1/browse-history', { token, expectHttp: 200 })
  check('A1', '足跡列表空', ok(empty.json) && (empty.json.data.items || []).length === 0)

  const p1 = await api('GET', '/api/v1/products/p1', { token, expectHttp: 200 })
  check('A2', '帶 token 讀 PDP', ok(p1.json) && p1.json.data.code === 'p1')

  const hist1 = await api('GET', '/api/v1/browse-history', { token, expectHttp: 200 })
  check(
    'A3',
    '瀏覽後足跡含 p1',
    (hist1.json.data.items || []).some((r) => r.code === 'p1'),
    `n=${(hist1.json.data.items || []).length}`,
  )

  await api('GET', '/api/v1/products/p2', { token, expectHttp: 200 })
  await api('GET', '/api/v1/products/p1', { token, expectHttp: 200 })
  const hist2 = await api('GET', '/api/v1/browse-history', { token, expectHttp: 200 })
  const codes = (hist2.json.data.items || []).map((r) => r.code)
  check(
    'A4',
    '同一商品只保留一條且 p1 最新',
    codes.filter((c) => c === 'p1').length === 1 && codes[0] === 'p1',
    codes.join(','),
  )

  const guest = await api('GET', '/api/v1/browse-history')
  check('A5', '未登錄不可讀足跡', guest.status === 401 || !ok(guest.json), String(guest.status))

  const cleared = await api('DELETE', '/api/v1/browse-history', { token, expectHttp: 200 })
  check('A6', '清空足跡', ok(cleared.json))
  const hist3 = await api('GET', '/api/v1/browse-history', { token, expectHttp: 200 })
  check('A7', '清空後為空', (hist3.json.data.items || []).length === 0)

  // 餘額流水：後台調帳產生一筆
  const memberId = (
    await api('GET', '/api/v1/members/me', { token, expectHttp: 200 })
  ).json.data.id
  await api('POST', `/api/v1/admin/members/${memberId}/asset-adjustments`, {
    token: adminToken,
    body: { balance_delta: 12.5, reason: 'M26 驗收調帳' },
    expectHttp: 200,
  })

  const bills = await api('GET', '/api/v1/balance-logs', { token, expectHttp: 200 })
  check(
    'A8',
    '賬單明細含調帳',
    (bills.json.data.items || []).some(
      (r) => r.type === 'admin_adjust' && Number(r.delta) === 12.5,
    ),
    `n=${(bills.json.data.items || []).length}`,
  )

  const billsGuest = await api('GET', '/api/v1/balance-logs')
  check(
    'A9',
    '未登錄不可讀賬單',
    billsGuest.status === 401 || !ok(billsGuest.json),
    String(billsGuest.status),
  )

  // 下單扣餘額再出現一筆 order_deduct
  const addr = await api('POST', '/api/v1/addresses', {
    token,
    body: {
      receiver: 'M26',
      phone: '90002626',
      region: '九龍',
      district: '旺角',
      detail: 'M26街1號',
      is_default: true,
    },
    expectHttp: 201,
  })
  const product = await api('GET', '/api/v1/products/p1')
  const sku = product.json.data.skus.find((s) => s.stock > 0)
  const orderRes = await api('POST', '/api/v1/orders', {
    token,
    body: {
      address_id: addr.json.data.id,
      buy_now: true,
      sku_id: sku.id,
      qty: 1,
      use_balance: true,
    },
    expectHttp: 201,
  })
  const order = orderRes.json.data
  check(
    'A10',
    '下單使用餘額',
    Number(order.balance_amount || 0) > 0,
    String(order.balance_amount),
  )

  const bills2 = await api('GET', '/api/v1/balance-logs', { token, expectHttp: 200 })
  check(
    'A11',
    '賬單含下單扣餘額',
    (bills2.json.data.items || []).some((r) => r.type === 'order_deduct' && Number(r.delta) < 0),
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
