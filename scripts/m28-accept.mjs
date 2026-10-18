/**
 * SisterMart M28: home recommendations + server favorites + avatar upload.
 */
import fs from 'node:fs'
import path from 'node:path'
import { execSync } from 'node:child_process'

const BASE = process.env.API_BASE || 'http://127.0.0.1:8000'
const OUT = '/opt/cursor/artifacts/m28-accept'
fs.mkdirSync(OUT, { recursive: true })

const results = []
function check(id, title, pass, detail = '') {
  results.push({ id, title, pass: Boolean(pass), detail: String(detail || '') })
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${id}  ${title}${detail ? ' — ' + detail : ''}`)
}

async function api(method, urlPath, { body, token, formData, expectHttp } = {}) {
  const headers = { Accept: 'application/json' }
  if (token) headers.Authorization = `Bearer ${token}`
  let payload
  if (formData) {
    payload = formData
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    payload = JSON.stringify(body)
  }
  const res = await fetch(`${BASE}${urlPath}`, {
    method,
    headers,
    body: payload,
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

/** 1x1 JPEG */
function tinyJpeg() {
  return Buffer.from(
    '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGfAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAQUCf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQMBAT8Bf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQIBAT8Bf//Z',
    'base64',
  )
}

async function main() {
  execSync('php database/m28_seed.php', { cwd: path.resolve('apps/api'), stdio: 'inherit' })

  const a = await registerMember('m28a')

  const hot = await api('GET', '/api/v1/home/recommendations?tab=hot&limit=10', { expectHttp: 200 })
  check(
    'A1',
    '熱銷推薦',
    ok(hot.json) &&
      hot.json.data.tab === 'hot' &&
      Array.isArray(hot.json.data.items) &&
      hot.json.data.items.length > 0,
    `n=${hot.json.data?.items?.length} source=${hot.json.data?.source}`,
  )

  const neu = await api('GET', '/api/v1/home/recommendations?tab=new&limit=10', { expectHttp: 200 })
  check(
    'A2',
    '新品推薦',
    ok(neu.json) && neu.json.data.tab === 'new' && neu.json.data.source === 'newest',
    `n=${neu.json.data?.items?.length}`,
  )

  const guestForYou = await api('GET', '/api/v1/home/recommendations?tab=for_you&limit=10', {
    expectHttp: 200,
  })
  check(
    'A3',
    '訪客猜你喜歡回退熱銷',
    ok(guestForYou.json) &&
      guestForYou.json.data.tab === 'for_you' &&
      guestForYou.json.data.source === 'hot_fallback',
    guestForYou.json.data?.source,
  )

  // 瀏覽商品寫入足跡後，for_you 應按分類補貨
  await api('GET', '/api/v1/products/p1', { token: a.token, expectHttp: 200 })
  const forYou = await api('GET', '/api/v1/home/recommendations?tab=for_you&limit=10', {
    token: a.token,
    expectHttp: 200,
  })
  check(
    'A4',
    '登錄後猜你喜歡（足跡分類或回退）',
    ok(forYou.json) &&
      forYou.json.data.tab === 'for_you' &&
      ['browse_categories', 'hot_fallback'].includes(forYou.json.data.source) &&
      (forYou.json.data.items || []).length > 0,
    forYou.json.data?.source,
  )

  const emptyFav = await api('GET', '/api/v1/favorites', { token: a.token, expectHttp: 200 })
  check('A5', '收藏列表空', ok(emptyFav.json) && (emptyFav.json.data.items || []).length === 0)

  const add = await api('POST', '/api/v1/favorites', {
    token: a.token,
    body: { product_code: 'p1' },
    expectHttp: 201,
  })
  check('A6', '加入收藏', ok(add.json) && add.json.data.favorited === true, add.json.data?.product_code)

  const codes = await api('GET', '/api/v1/favorites/codes', { token: a.token, expectHttp: 200 })
  check(
    'A7',
    '收藏碼含 p1',
    ok(codes.json) && (codes.json.data.product_codes || []).includes('p1'),
    JSON.stringify(codes.json.data?.product_codes),
  )

  const list = await api('GET', '/api/v1/favorites', { token: a.token, expectHttp: 200 })
  check(
    'A8',
    '收藏列表含商品卡',
    (list.json.data.items || []).some((r) => r.code === 'p1'),
    `n=${(list.json.data.items || []).length}`,
  )

  const toggleOff = await api('POST', '/api/v1/favorites/toggle', {
    token: a.token,
    body: { product_code: 'p1' },
    expectHttp: 200,
  })
  check('A9', '取消收藏', ok(toggleOff.json) && toggleOff.json.data.favorited === false)

  const toggleOn = await api('POST', '/api/v1/favorites/toggle', {
    token: a.token,
    body: { product_code: 'p1' },
    expectHttp: 200,
  })
  check('A10', '再收藏', ok(toggleOn.json) && toggleOn.json.data.favorited === true)

  const guestFav = await api('GET', '/api/v1/favorites')
  check('A11', '未登錄不可讀收藏', guestFav.status === 401 || !ok(guestFav.json), String(guestFav.status))

  const fd = new FormData()
  fd.append('file', new Blob([tinyJpeg()], { type: 'image/jpeg' }), 'avatar.jpg')
  const av = await api('POST', '/api/v1/uploads/avatar', {
    token: a.token,
    formData: fd,
    expectHttp: 201,
  })
  const avatarUrl = av.json.data?.url || ''
  check(
    'A12',
    '上傳頭像並落庫',
    ok(av.json) &&
      typeof avatarUrl === 'string' &&
      avatarUrl.startsWith('/storage/avatar/') &&
      av.json.data.member?.avatar_url === avatarUrl,
    avatarUrl,
  )

  const me = await api('GET', '/api/v1/members/me', { token: a.token, expectHttp: 200 })
  check(
    'A13',
    'me 返回 avatar_url',
    ok(me.json) && me.json.data.avatar_url === avatarUrl,
    me.json.data?.avatar_url,
  )

  // 靜態文件可讀
  const asset = await fetch(`${BASE}${avatarUrl}`)
  check('A14', '頭像文件可訪問', asset.ok && asset.headers.get('content-type')?.includes('image'), asset.status)

  const failed = results.filter((r) => !r.pass)
  const summary = {
    total: results.length,
    passed: results.length - failed.length,
    failed: failed.map((r) => r.id),
    results,
  }
  fs.writeFileSync(path.join(OUT, 'summary.json'), JSON.stringify(summary, null, 2))
  console.log(`\n${summary.passed}/${summary.total} passed`)
  if (failed.length) process.exit(1)
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})
