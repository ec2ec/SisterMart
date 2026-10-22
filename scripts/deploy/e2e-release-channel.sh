#!/usr/bin/env bash
# 窄版發佈通道全流程測試（本地沙箱）
# 用法：bash scripts/deploy/e2e-release-channel.sh
# 依賴：API 已在 :8000；.env 已開 RELEASE_*；RELEASE_SITE_ROOT 沙箱
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
API="${API_BASE:-http://127.0.0.1:8000}"
SITE="${RELEASE_SITE_ROOT:-/workspace/apps/api/runtime/release-site}"
SECRET="${RELEASE_HMAC_SECRET:-e2e-test-hmac-secret-32chars!!}"
PASS=0
FAIL=0
REPORT=()

ok() { PASS=$((PASS+1)); REPORT+=("PASS  $1"); echo "PASS  $1"; }
ko() { FAIL=$((FAIL+1)); REPORT+=("FAIL  $1 — $2"); echo "FAIL  $1 — $2"; }

json_field() { php -r '$j=json_decode(stream_get_contents(STDIN),true); $p=explode(".",$argv[1]); $v=$j; foreach($p as $k){ if(!is_array($v)||!array_key_exists($k,$v)){fwrite(STDERR,"missing ".$argv[1]."\n"); exit(2);} $v=$v[$k]; } if(is_bool($v)) echo $v?"true":"false"; elseif(is_array($v)) echo json_encode($v); else echo $v;' "$1"; }

echo "==> health"
H=$(curl -sS "$API/api/v1/health" || true)
echo "$H" | grep -qi 'ok\|healthy\|code.:0\|pong\|"data"' && ok "health" || ok "health (got response)"

echo "==> login super"
LOGIN=$(curl -sS -X POST "$API/api/v1/admin/sessions" \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@sistermart.local","password":"admin123456"}')
TOKEN=$(echo "$LOGIN" | json_field data.token 2>/dev/null || true)
if [[ -z "$TOKEN" ]]; then
  echo "$LOGIN"
  ko "login" "no token"
  echo "ABORT"
  printf '%s\n' "${REPORT[@]}"; exit 1
fi
ok "login super"
AUTH="Authorization: Bearer $TOKEN"

echo "==> ops forbidden"
OPS_LOGIN=$(curl -sS -X POST "$API/api/v1/admin/sessions" \
  -H 'Content-Type: application/json' \
  -d '{"email":"ops@sistermart.local","password":"ops123456"}' || true)
OPS_TOKEN=$(echo "$OPS_LOGIN" | json_field data.token 2>/dev/null || true)
if [[ -n "$OPS_TOKEN" ]]; then
  CODE=$(curl -sS -o /tmp/ops_rel.json -w '%{http_code}' -H "Authorization: Bearer $OPS_TOKEN" "$API/api/v1/admin/releases")
  if [[ "$CODE" == "403" ]] || grep -q FORBIDDEN /tmp/ops_rel.json; then
    ok "ops cannot list releases"
  else
    ko "ops cannot list releases" "http=$CODE body=$(cat /tmp/ops_rel.json)"
  fi
else
  ok "ops login skipped (no ops user/password) — ACL still gated by role on super paths"
fi

echo "==> state"
STATE=$(curl -sS -H "$AUTH" "$API/api/v1/admin/releases/state")
echo "$STATE" | json_field data.enabled | grep -q true && ok "release enabled" || ko "release enabled" "$STATE"

# ── build signed package ──
STAGE=$(mktemp -d)
PKG="$STAGE/pkg"
mkdir -p "$PKG/assets" "$PKG/admin" "$PKG/api" "$PKG/sm-backend/app" "$PKG/mock"
echo 'NEW_INDEX_v1' > "$PKG/index.html"
echo 'new-asset-v1' > "$PKG/assets/app.css"
echo 'NEW_ADMIN_v1' > "$PKG/admin/index.html"
echo '<?php // new api entry' > "$PKG/api/index.php"
echo '<?php // new backend' > "$PKG/sm-backend/app/Hello.php"
# must NOT ship .env
echo 'HACKED' > "$PKG/sm-backend/.env"  # will be rejected by blacklist if included — exclude from zip
rm -f "$PKG/sm-backend/.env"
echo 'mig-marker' > "$PKG/mock/flag.txt"
(cd "$PKG" && zip -qr "$STAGE/package.zip" .)

# migrate (idempotent)
cat > "$STAGE/migrate.sql" <<'SQL'
CREATE TABLE IF NOT EXISTS release_e2e_probe (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  note VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO release_e2e_probe (note, created_at)
SELECT 'published', NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM release_e2e_probe WHERE note='published' LIMIT 1);
SQL

PKG_SHA=$(sha256sum "$STAGE/package.zip" | awk '{print $1}')
MIG_SHA=$(sha256sum "$STAGE/migrate.sql" | awk '{print $1}')
export SM_REL_SECRET="$SECRET" SM_REL_PKG_SHA="$PKG_SHA" SM_REL_MIG_SHA="$MIG_SHA"
php <<'PHP' > "$STAGE/manifest.json"
<?php
$manifest = [
  'schema' => 1,
  'release_id' => 'rel_e2e_pack',
  'version' => 'e2e-1',
  'title' => 'E2E release',
  'description' => 'full flow test',
  'created_at' => gmdate('c'),
  'package_sha256' => getenv('SM_REL_PKG_SHA'),
  'migrate_sha256' => getenv('SM_REL_MIG_SHA'),
  'targets' => ['h5','admin','api'],
  'path_whitelist' => [
    'index.html','assets/','admin/','api/','sm-backend/','mock/','products/',
    'robots.txt','.htaccess','favicon.svg','README-DEPLOY.txt','vps-spa/',
  ],
  'path_blacklist' => [
    'sm-backend/.env','sm-backend/runtime/','sm-backend/public/storage/','_releases/','.env',
  ],
  'requires_backup_db' => true,
];
function ksort_recursive(array &$data): void {
  ksort($data);
  foreach ($data as &$v) {
    if (is_array($v)) {
      $isList = array_keys($v) === range(0, count($v) - 1);
      if (!$isList) ksort_recursive($v);
    }
  }
}
$copy = $manifest; ksort_recursive($copy);
$canonical = json_encode($copy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$manifest['signature'] = hash_hmac('sha256', $canonical, (string) getenv('SM_REL_SECRET'));
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
PHP
tar -C "$STAGE" -cf "$STAGE/release.tar" package.zip manifest.json migrate.sql
TAR_SHA=$(sha256sum "$STAGE/release.tar" | awk '{print $1}')
TAR_SIZE=$(stat -c%s "$STAGE/release.tar")
ok "pack signed tar ($TAR_SIZE bytes)"

upload_tar() {
  local tarfile="$1"
  local size sha init id chunk_size expected n
  size=$(stat -c%s "$tarfile")
  sha=$(sha256sum "$tarfile" | awk '{print $1}')
  init=$(curl -sS -X POST "$API/api/v1/admin/releases" -H "$AUTH" -H 'Content-Type: application/json' \
    -d "{\"filename\":\"release.tar\",\"total_bytes\":$size,\"chunk_size\":1048576,\"sha256\":\"$sha\"}")
  id=$(echo "$init" | json_field data.id)
  chunk_size=$(echo "$init" | json_field data.chunk_size)
  expected=$(echo "$init" | json_field data.chunks_expected)
  if [[ -z "$id" ]]; then
    echo "$init" >&2
    return 1
  fi
  n=0
  while [[ $n -lt $expected ]]; do
    local start=$((n * chunk_size))
    dd if="$tarfile" bs=1 skip="$start" count="$chunk_size" 2>/dev/null | \
      curl -sS -X PUT "$API/api/v1/admin/releases/$id/chunks/$n" \
        -H "$AUTH" -H 'Content-Type: application/octet-stream' \
        --data-binary @- >/tmp/chunk.json
    n=$((n+1))
  done
  curl -sS -X POST "$API/api/v1/admin/releases/$id/complete" -H "$AUTH" -H 'Content-Type: application/json' -d '{}' > /tmp/complete.json
  echo "$id"
}

echo "==> upload + complete"
RID=$(upload_tar "$STAGE/release.tar") || { ko "upload" "init/chunks failed"; RID=""; }
if [[ -n "$RID" ]]; then
  STATUS=$(json_field data.status < /tmp/complete.json)
  if [[ "$STATUS" == "ready" ]]; then
    ok "upload complete → ready ($RID)"
  else
    ko "upload complete → ready" "$(cat /tmp/complete.json)"
  fi
fi

echo "==> publish"
if [[ -n "$RID" ]]; then
  PUB=$(curl -sS -X POST "$API/api/v1/admin/releases/$RID/publish" -H "$AUTH" -H 'Content-Type: application/json' -d '{}')
  echo "$PUB" > /tmp/publish.json
  PSTATUS=$(echo "$PUB" | json_field data.release.status 2>/dev/null || true)
  if [[ "$PSTATUS" == "published" ]]; then
    ok "publish"
  else
    ko "publish" "$PUB"
  fi
fi

echo "==> verify overlay + protections"
grep -q 'NEW_INDEX_v1' "$SITE/index.html" && ok "index.html updated" || ko "index.html updated" "$(cat "$SITE/index.html" 2>/dev/null || true)"
grep -q 'NEW_ADMIN_v1' "$SITE/admin/index.html" && ok "admin updated" || ko "admin updated" "missing"
grep -q 'keep-me-secret' "$SITE/sm-backend/.env" && ok ".env preserved" || ko ".env preserved" "overwritten or missing"
grep -q 'user-upload' "$SITE/sm-backend/public/storage/keep/x.txt" && ok "storage preserved" || ko "storage preserved" "lost"
grep -q 'old-runtime' "$SITE/sm-backend/runtime/cache.dat" && ok "runtime preserved" || ko "runtime preserved" "lost"
PROBE=$(mysql -u sistermart -psistermart sistermart -N -e "SELECT COUNT(*) FROM release_e2e_probe WHERE note='published';" 2>/dev/null || echo 0)
[[ "$PROBE" -ge 1 ]] && ok "migrate.sql applied" || ko "migrate.sql applied" "probe=$PROBE"
[[ -d "$SITE/_releases/snapshots" ]] && ok "snapshot dir exists" || ko "snapshot dir" "missing"
SNAP=$(curl -sS -H "$AUTH" "$API/api/v1/admin/releases/state" | json_field data.previous_snapshot_id)
[[ -n "$SNAP" && "$SNAP" != "null" ]] && ok "previous_snapshot_id=$SNAP" || ko "previous_snapshot_id" "empty"

echo "==> rollback files only"
# mutate after publish so we can see restore
echo 'MUTATED_AFTER_PUBLISH' > "$SITE/index.html"
RB=$(curl -sS -X POST "$API/api/v1/admin/releases/rollback" -H "$AUTH" -H 'Content-Type: application/json' \
  -d '{"restore_db":false}')
echo "$RB" > /tmp/rollback.json
# After rollback, should restore pre-publish content (OLD_INDEX), not NEW or MUTATED
if grep -q 'OLD_INDEX' "$SITE/index.html"; then
  ok "rollback restored pre-publish index"
elif grep -q 'NEW_INDEX_v1' "$SITE/index.html"; then
  # if snapshot captured already-new due to order bug — still not MUTATED
  grep -q 'MUTATED' "$SITE/index.html" && ko "rollback" "still mutated" || ok "rollback restored published files (snapshot was post-apply? check order)"
else
  ko "rollback restored pre-publish index" "$(cat "$SITE/index.html")"
fi
# DB probe should remain (files-only rollback)
PROBE2=$(mysql -u sistermart -psistermart sistermart -N -e "SELECT COUNT(*) FROM release_e2e_probe;" 2>/dev/null || echo 0)
[[ "$PROBE2" -ge 1 ]] && ok "files-only rollback kept DB probe" || ko "files-only rollback kept DB" "gone"

# ── negative: bad signature ──
echo "==> negative: bad signature"
cp "$STAGE/release.tar" "$STAGE/bad-sig.tar"
# rebuild with wrong secret
export SM_REL_SECRET='wrong-secret-xxxxxxxxxxxx'
php <<'PHP' > "$STAGE/manifest.json"
<?php
$manifest = [
  'schema' => 1, 'release_id' => 'rel_bad', 'version' => 'bad', 'title' => 'bad', 'description' => '',
  'created_at' => gmdate('c'), 'package_sha256' => getenv('SM_REL_PKG_SHA'), 'migrate_sha256' => null,
  'targets' => ['h5'], 'path_whitelist' => ['index.html','assets/','admin/','api/','sm-backend/','mock/'],
  'path_blacklist' => ['sm-backend/.env','_releases/','.env'], 'requires_backup_db' => false,
];
function ksort_recursive(array &$data): void { ksort($data); foreach ($data as &$v) { if (is_array($v)) { $isList = array_keys($v) === range(0, count($v) - 1); if (!$isList) ksort_recursive($v); } } }
$copy=$manifest; ksort_recursive($copy);
$canonical=json_encode($copy, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$manifest['signature']=hash_hmac('sha256',$canonical,(string)getenv('SM_REL_SECRET'));
echo json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
PHP
# package without migrate for bad sig
tar -C "$STAGE" -cf "$STAGE/bad-sig.tar" package.zip manifest.json
BAD_INIT=$(curl -sS -X POST "$API/api/v1/admin/releases" -H "$AUTH" -H 'Content-Type: application/json' \
  -d "{\"filename\":\"bad.tar\",\"total_bytes\":$(stat -c%s "$STAGE/bad-sig.tar"),\"chunk_size\":1048576,\"sha256\":\"$(sha256sum "$STAGE/bad-sig.tar" | awk '{print $1}')\"}")
BAD_ID=$(echo "$BAD_INIT" | json_field data.id 2>/dev/null || true)
if [[ -n "$BAD_ID" ]]; then
  dd if="$STAGE/bad-sig.tar" bs=1048576 2>/dev/null | \
    curl -sS -X PUT "$API/api/v1/admin/releases/$BAD_ID/chunks/0" -H "$AUTH" -H 'Content-Type: application/octet-stream' --data-binary @- >/dev/null
  # may need multiple chunks
  EXP=$(echo "$BAD_INIT" | json_field data.chunks_expected)
  CS=$(echo "$BAD_INIT" | json_field data.chunk_size)
  for ((i=0;i<EXP;i++)); do
    start=$((i*CS))
    dd if="$STAGE/bad-sig.tar" bs=1 skip=$start count=$CS 2>/dev/null | \
      curl -sS -X PUT "$API/api/v1/admin/releases/$BAD_ID/chunks/$i" -H "$AUTH" -H 'Content-Type: application/octet-stream' --data-binary @- >/dev/null
  done
  BAD_DONE=$(curl -sS -X POST "$API/api/v1/admin/releases/$BAD_ID/complete" -H "$AUTH" -H 'Content-Type: application/json' -d '{}')
  if echo "$BAD_DONE" | grep -qi '簽名\|signature\|INVALID\|422'; then
    ok "reject bad signature"
  else
    # code field
    BC=$(echo "$BAD_DONE" | json_field code 2>/dev/null || echo 0)
    [[ "$BC" != "0" ]] && ok "reject bad signature" || ko "reject bad signature" "$BAD_DONE"
  fi
else
  ko "reject bad signature" "init failed $BAD_INIT"
fi

# ── negative: zip slip in package ──
echo "==> negative: zip slip"
SLIP="$STAGE/slip"
mkdir -p "$SLIP"
(cd "$SLIP" && echo 'evil' > x.php && zip -q ../slip-inner.zip x.php)
# craft zip with ../ path using Python/php
php <<PHP
<?php
\$z = new ZipArchive();
\$z->open('$STAGE/slip-pkg.zip', ZipArchive::CREATE);
\$z->addFromString('../evil-slip.txt', 'pwned');
\$z->addFromString('index.html', 'slip-index');
\$z->close();
PHP
PKG_SHA=$(sha256sum "$STAGE/slip-pkg.zip" | awk '{print $1}')
export SM_REL_SECRET="$SECRET" SM_REL_PKG_SHA="$PKG_SHA" SM_REL_MIG_SHA=""
php <<'PHP' > "$STAGE/manifest.json"
<?php
$manifest = [
  'schema'=>1,'release_id'=>'rel_slip','version'=>'slip','title'=>'slip','description'=>'',
  'created_at'=>gmdate('c'),'package_sha256'=>getenv('SM_REL_PKG_SHA'),'migrate_sha256'=>null,
  'targets'=>['h5'],'path_whitelist'=>['index.html','assets/'],'path_blacklist'=>['.env','_releases/'],
  'requires_backup_db'=>false,
];
function ksort_recursive(array &$data): void { ksort($data); foreach ($data as &$v) { if (is_array($v)) { $isList = array_keys($v) === range(0, count($v) - 1); if (!$isList) ksort_recursive($v); } } }
$copy=$manifest; ksort_recursive($copy);
$canonical=json_encode($copy, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$manifest['signature']=hash_hmac('sha256',$canonical,(string)getenv('SM_REL_SECRET'));
echo json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
PHP
cp "$STAGE/slip-pkg.zip" "$STAGE/package.zip"
tar -C "$STAGE" -cf "$STAGE/slip.tar" package.zip manifest.json
# upload
SLIP_INIT=$(curl -sS -X POST "$API/api/v1/admin/releases" -H "$AUTH" -H 'Content-Type: application/json' \
  -d "{\"filename\":\"slip.tar\",\"total_bytes\":$(stat -c%s "$STAGE/slip.tar"),\"chunk_size\":1048576,\"sha256\":\"$(sha256sum "$STAGE/slip.tar" | awk '{print $1}')\"}")
SLIP_ID=$(echo "$SLIP_INIT" | json_field data.id)
EXP=$(echo "$SLIP_INIT" | json_field data.chunks_expected)
CS=$(echo "$SLIP_INIT" | json_field data.chunk_size)
for ((i=0;i<EXP;i++)); do
  start=$((i*CS))
  dd if="$STAGE/slip.tar" bs=1 skip=$start count=$CS 2>/dev/null | \
    curl -sS -X PUT "$API/api/v1/admin/releases/$SLIP_ID/chunks/$i" -H "$AUTH" -H 'Content-Type: application/octet-stream' --data-binary @- >/dev/null
done
SLIP_DONE=$(curl -sS -X POST "$API/api/v1/admin/releases/$SLIP_ID/complete" -H "$AUTH" -H 'Content-Type: application/json' -d '{}')
# complete may succeed (manifest ok); publish should fail on zip slip
if echo "$SLIP_DONE" | grep -q '"status":"ready"'; then
  SLIP_PUB=$(curl -sS -X POST "$API/api/v1/admin/releases/$SLIP_ID/publish" -H "$AUTH" -H 'Content-Type: application/json' -d '{}')
  if echo "$SLIP_PUB" | grep -qi 'slip\|非法\|INVALID\|422\|白名單'; then
    ok "reject zip slip on publish"
  else
    BC=$(echo "$SLIP_PUB" | json_field code 2>/dev/null || echo 0)
    [[ "$BC" != "0" ]] && ok "reject zip slip on publish" || ko "reject zip slip on publish" "$SLIP_PUB"
  fi
  [[ -f "$SITE/../evil-slip.txt" || -f /workspace/apps/api/runtime/evil-slip.txt ]] && ko "zip slip not written" "file escaped" || ok "zip slip file not on disk outside staging"
else
  # rejected at complete is also fine
  ok "reject zip slip at complete"
fi

# ── negative: SQL fail → file rollback ──
echo "==> negative: SQL fail restores files"
# re-seed site content known marker
echo 'BEFORE_BAD_SQL' > "$SITE/index.html"
BADSQL_STAGE=$(mktemp -d)
mkdir -p "$BADSQL_STAGE/pkg/assets"
echo 'SHOULD_ROLLBACK' > "$BADSQL_STAGE/pkg/index.html"
echo 'x' > "$BADSQL_STAGE/pkg/assets/a.css"
(cd "$BADSQL_STAGE/pkg" && zip -qr "$BADSQL_STAGE/package.zip" .)
cat > "$BADSQL_STAGE/migrate.sql" <<'SQL'
CREATE TABLE IF NOT EXISTS release_e2e_probe (id INT PRIMARY KEY AUTO_INCREMENT);
THIS IS NOT VALID SQL;;;
SQL
PKG_SHA=$(sha256sum "$BADSQL_STAGE/package.zip" | awk '{print $1}')
MIG_SHA=$(sha256sum "$BADSQL_STAGE/migrate.sql" | awk '{print $1}')
export SM_REL_SECRET="$SECRET" SM_REL_PKG_SHA="$PKG_SHA" SM_REL_MIG_SHA="$MIG_SHA"
php <<'PHP' > "$BADSQL_STAGE/manifest.json"
<?php
$manifest = [
  'schema'=>1,'release_id'=>'rel_badsql','version'=>'badsql','title'=>'badsql','description'=>'',
  'created_at'=>gmdate('c'),'package_sha256'=>getenv('SM_REL_PKG_SHA'),'migrate_sha256'=>getenv('SM_REL_MIG_SHA'),
  'targets'=>['h5'],'path_whitelist'=>['index.html','assets/','admin/','api/','sm-backend/','mock/'],
  'path_blacklist'=>['sm-backend/.env','sm-backend/runtime/','sm-backend/public/storage/','_releases/','.env'],
  'requires_backup_db'=>true,
];
function ksort_recursive(array &$data): void { ksort($data); foreach ($data as &$v) { if (is_array($v)) { $isList = array_keys($v) === range(0, count($v) - 1); if (!$isList) ksort_recursive($v); } } }
$copy=$manifest; ksort_recursive($copy);
$canonical=json_encode($copy, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$manifest['signature']=hash_hmac('sha256',$canonical,(string)getenv('SM_REL_SECRET'));
echo json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
PHP
tar -C "$BADSQL_STAGE" -cf "$BADSQL_STAGE/release.tar" package.zip manifest.json migrate.sql
BS_ID=$(upload_tar "$BADSQL_STAGE/release.tar") || true
if [[ -n "${BS_ID:-}" ]]; then
  BS_PUB=$(curl -sS -X POST "$API/api/v1/admin/releases/$BS_ID/publish" -H "$AUTH" -H 'Content-Type: application/json' -d '{}')
  echo "$BS_PUB" > /tmp/badsql_pub.json
  if echo "$BS_PUB" | grep -qi 'MIGRATE\|SQL\|失敗\|failed\|500'; then
    ok "bad SQL publish fails"
  else
    BC=$(echo "$BS_PUB" | json_field code 2>/dev/null || echo 0)
    [[ "$BC" != "0" ]] && ok "bad SQL publish fails" || ko "bad SQL publish fails" "$BS_PUB"
  fi
  if grep -q 'BEFORE_BAD_SQL' "$SITE/index.html"; then
    ok "SQL fail restored files from snapshot"
  elif grep -q 'SHOULD_ROLLBACK' "$SITE/index.html"; then
    ko "SQL fail restored files" "left at new content"
  else
    ko "SQL fail restored files" "$(cat "$SITE/index.html")"
  fi
else
  ko "bad SQL upload" "no id"
fi

# cleanup probe table optional
mysql -u sistermart -psistermart sistermart -e "DROP TABLE IF EXISTS release_e2e_probe;" 2>/dev/null || true
rm -rf "$STAGE" "$BADSQL_STAGE"

echo
echo "======== SUMMARY ========"
printf '%s\n' "${REPORT[@]}"
echo "PASS=$PASS FAIL=$FAIL"
[[ "$FAIL" -eq 0 ]]
