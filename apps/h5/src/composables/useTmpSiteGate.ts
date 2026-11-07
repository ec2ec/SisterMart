/**
 * TMP_SITE_GATE — 臨時測試門禁（整站密碼遮罩）。
 * 正式發佈前請刪除：本檔、components/TmpSiteGate.vue、App.vue 掛載、i18n `tmpGate.*`。
 */
export const TMP_SITE_GATE_ENABLED = true
export const TMP_SITE_GATE_PASSWORD = '0424'
export const TMP_SITE_GATE_LS_KEY = 'sm_tmp_site_gate_ok'

export function readTmpSiteGatePassed(): boolean {
  if (!TMP_SITE_GATE_ENABLED) return true
  try {
    return localStorage.getItem(TMP_SITE_GATE_LS_KEY) === '1'
  } catch {
    return false
  }
}

export function markTmpSiteGatePassed(): void {
  try {
    localStorage.setItem(TMP_SITE_GATE_LS_KEY, '1')
  } catch {
    /* private mode：仍允許本次 session 用內存狀態通行 */
  }
}
