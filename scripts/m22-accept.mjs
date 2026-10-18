/**
 * SisterMart M22: aftersale evidence review workflow.
 */
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'

const BASE = process.env.API_BASE || 'http://127.0.0.1:8000'
const OUT = '/opt/cursor/artifacts/m22-accept'
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

function tinyPng() {
  return Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
  )
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
  return { token: reg.json.data.token, member: reg.json.data.member }
}

async function loginAdmin() {
  const res = await api('POST', '/api/v1/admin/sessions', {
    body: { email: 'admin@sistermart.local', password: 'admin123456' },
  })
  return res.json.data.token
}

async function paidOrder(token) {
  const addr = await api('POST', '/api/v1/addresses', {
    token,
    body: {
      receiver: 'M22',
      phone: '90002222',
      region: '九龍',
      district: '旺角',
      detail: 'M22街1號',
      is_default: true,
    },
    expectHttp: 201,
  })
  const p1 = await api('GET', '/api/v1/products/p1')
  const sku = p1.json.data.skus.find((s) => s.stock > 0)
  const orderRes = await api('POST', '/api/v1/orders', {
    token,
    body: { address_id: addr.json.data.id, buy_now: true, sku_id: sku.id, qty: 1 },
    expectHttp: 201,
  })
  let order = orderRes.json.data
  if (order.status === 'pending_payment') {
    const pay = await api('POST', `/api/v1/orders/${order.id}/payments`, {
      token,
      body: { channel: 'fps' },
      expectHttp: 201,
    })
    await api('POST', `/api/v1/payments/${pay.json.data.payment_id}/mock-confirm`, {
      token,
      expectHttp: 200,
    })
  }
  const detail = await api('GET', `/api/v1/orders/${order.id}`, { token, expectHttp: 200 })
  return detail.json.data
}

async function completeOrder(token, adminToken, orderId) {
  await api('POST', `/api/v1/admin/orders/${orderId}/shipments`, {
    token: adminToken,
    body: { company: 'SF', tracking_no: 'SFM22' + Date.now() },
    expectHttp: 201,
  })
  await api('POST', `/api/v1/orders/${orderId}/confirm-receipt`, { token, expectHttp: 200 })
  const detail = await api('GET', `/api/v1/orders/${orderId}`, { token, expectHttp: 200 })
  return detail.json.data
}

async function uploadEvidence(token) {
  const fd = new FormData()
  fd.append('file', new Blob([tinyPng()], { type: 'image/png' }), 'e.png')
  const up = await api('POST', '/api/v1/uploads/aftersale-evidence', {
    token,
    formData: fd,
    expectHttp: 201,
  })
  return up.json.data.url
}

async function main() {
  execSync('php database/m17_seed.php', { cwd: path.resolve('apps/api'), stdio: 'inherit' })
  const adminToken = await loginAdmin()
  const { token } = await registerMember('m22')

  // A1: refund_only + evidence → admin sees URLs
  const o1 = await paidOrder(token)
  const url1 = await uploadEvidence(token)
  const as1 = await api('POST', '/api/v1/aftersales', {
    token,
    body: {
      order_id: o1.id,
      order_item_id: o1.items[0].id,
      type: 'refund_only',
      refund_to: 'balance',
      description: 'M22 僅退款有證據',
      evidence: [url1],
    },
    expectHttp: 201,
  })
  const listEv = await api('GET', '/api/v1/admin/aftersales?has_evidence=1&status=pending_review', {
    token: adminToken,
    expectHttp: 200,
  })
  check(
    'A1',
    '有證據售後出現在 has_evidence=1 列表',
    ok(listEv.json) && (listEv.json.data.items || []).some((x) => x.id === as1.json.data.id && x.evidence_count >= 1),
    `n=${listEv.json.data?.items?.length}`,
  )

  // A2: reject requires note
  const noNote = await api('POST', `/api/v1/admin/aftersales/${as1.json.data.id}/review`, {
    token: adminToken,
    body: { action: 'reject', admin_note: '' },
  })
  check(
    'A2',
    '駁回無備註被拒',
    !ok(noNote.json) && (noNote.status === 422 || noNote.json.code !== 0),
    noNote.json.message || noNote.json.error?.name,
  )

  // A3: reject with note
  const rejected = await api('POST', `/api/v1/admin/aftersales/${as1.json.data.id}/review`, {
    token: adminToken,
    body: { action: 'reject', admin_note: '證據不足以證明質量問題' },
    expectHttp: 200,
  })
  check(
    'A3',
    '駁回寫入自訂備註',
    ok(rejected.json) &&
      rejected.json.data.status === 'rejected' &&
      rejected.json.data.admin_note === '證據不足以證明質量問題',
    rejected.json.data?.admin_note,
  )

  const memberDetail = await api('GET', `/api/v1/aftersales/${as1.json.data.id}`, {
    token,
    expectHttp: 200,
  })
  check(
    'A4',
    '會員詳情可見審核備註',
    ok(memberDetail.json) && memberDetail.json.data.admin_note === '證據不足以證明質量問題',
    memberDetail.json.data?.admin_note,
  )

  // A5: refund_only with evidence → approve → completed
  const o2 = await paidOrder(token)
  const url2 = await uploadEvidence(token)
  const as2 = await api('POST', '/api/v1/aftersales', {
    token,
    body: {
      order_id: o2.id,
      order_item_id: o2.items[0].id,
      type: 'refund_only',
      refund_to: 'balance',
      description: 'M22 僅退款通過',
      evidence: [url2],
    },
    expectHttp: 201,
  })
  const approved = await api('POST', `/api/v1/admin/aftersales/${as2.json.data.id}/review`, {
    token: adminToken,
    body: { action: 'approve', admin_note: '同意僅退款' },
    expectHttp: 200,
  })
  check(
    'A5',
    '僅退款有證據可通過並完成',
    ok(approved.json) && approved.json.data.status === 'completed',
    approved.json.data?.status,
  )

  // A6: return_refund without evidence → approve blocked
  const o3 = await paidOrder(token)
  const completed3 = await completeOrder(token, adminToken, o3.id)
  const as3 = await api('POST', '/api/v1/aftersales', {
    token,
    body: {
      order_id: completed3.id,
      order_item_id: completed3.items[0].id,
      type: 'return_refund',
      refund_to: 'balance',
      description: 'M22 退貨無證據應攔截',
    },
    expectHttp: 201,
  })
  check('A6', '退貨無證據可申請（evidence=[]）', ok(as3.json) && as3.json.data.evidence_count === 0)

  const blocked = await api('POST', `/api/v1/admin/aftersales/${as3.json.data.id}/review`, {
    token: adminToken,
    body: { action: 'approve' },
  })
  check(
    'A7',
    '退貨無證據通過被 EVIDENCE_REQUIRED 攔截',
    blocked.json.error?.name === 'EVIDENCE_REQUIRED' || blocked.status === 422,
    blocked.json.error?.name || blocked.json.message,
  )

  // A8: return_refund with evidence → approve → mark returned
  const o4 = await paidOrder(token)
  const completed4 = await completeOrder(token, adminToken, o4.id)
  const url4 = await uploadEvidence(token)
  const as4 = await api('POST', '/api/v1/aftersales', {
    token,
    body: {
      order_id: completed4.id,
      order_item_id: completed4.items[0].id,
      type: 'return_refund',
      refund_to: 'balance',
      description: 'M22 退貨有證據',
      evidence: [url4],
    },
    expectHttp: 201,
  })
  const ap4 = await api('POST', `/api/v1/admin/aftersales/${as4.json.data.id}/review`, {
    token: adminToken,
    body: { action: 'approve', admin_note: '同意退貨' },
    expectHttp: 200,
  })
  check(
    'A8',
    '退貨有證據通過 → awaiting_return',
    ok(ap4.json) && ap4.json.data.status === 'awaiting_return',
    ap4.json.data?.status,
  )
  const done = await api('POST', `/api/v1/admin/aftersales/${as4.json.data.id}/mark-returned`, {
    token: adminToken,
    body: {},
    expectHttp: 200,
  })
  check(
    'A9',
    '確認退貨後退款完成',
    ok(done.json) && done.json.data.status === 'completed',
    done.json.data?.status,
  )

  const none = await api('GET', '/api/v1/admin/aftersales?has_evidence=0&status=pending_review', {
    token: adminToken,
    expectHttp: 200,
  })
  check(
    'A10',
    'has_evidence=0 過濾可用',
    ok(none.json) && (none.json.data.items || []).every((x) => (x.evidence_count || 0) === 0),
    `n=${none.json.data?.items?.length}`,
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
