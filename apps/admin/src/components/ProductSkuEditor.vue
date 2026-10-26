<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import {
  NButton,
  NCard,
  NFormItem,
  NInput,
  NInputNumber,
  NSelect,
  NSpace,
  NSwitch,
  useMessage,
} from 'naive-ui'
import { api } from '../api'
import { ensureSpecCode, refsKey } from '../utils/specCode'

export type Localized = { 'zh-HK': string; en: string }
export type SpecOptionDraft = { code: string; name: Localized; image?: string | null }
export type SpecGroupDraft = { code: string; name: Localized; options: SpecOptionDraft[] }
export type SkuDraft = {
  code: string
  price: number
  compare_at: number
  agent_price: number
  cost_price: number
  stock: number
  image?: string | null
  status?: number
  option_refs: string[]
}

const MAX_GROUPS = 3
const MAX_SKUS = 50

const props = defineProps<{
  groups: SpecGroupDraft[]
  skus: SkuDraft[]
}>()

const emit = defineEmits<{
  'update:groups': [SpecGroupDraft[]]
  'update:skus': [SkuDraft[]]
}>()

const message = useMessage()
const templates = ref<
  Array<{
    id: number
    code: string
    name: Localized
    options: Array<{ code: string; name: Localized; status: number }>
  }>
>([])
const applyTemplateId = ref<number | null>(null)

const groupModel = computed({
  get: () => props.groups,
  set: (v) => emit('update:groups', v),
})
const skuModel = computed({
  get: () => props.skus,
  set: (v) => emit('update:skus', v),
})

const templateOptions = computed(() =>
  templates.value.map((t) => ({
    label: `${t.name['zh-HK']} (${t.code})`,
    value: t.id,
  })),
)

const comboCount = computed(() => {
  if (!groupModel.value.length) return 0
  return groupModel.value.reduce((n, g) => n * Math.max(1, g.options.length), 1)
})

onMounted(async () => {
  try {
    const data = await api<{ items: typeof templates.value }>(
      '/api/v1/admin/spec-templates?active_only=1',
      {},
      true,
    )
    templates.value = data.items || []
  } catch {
    templates.value = []
  }
})

function emptyOption(i = 1): SpecOptionDraft {
  return { code: `o${i}`, name: { 'zh-HK': '', en: '' }, image: null }
}

function emptyGroup(i = 1): SpecGroupDraft {
  return {
    code: `g${i}`,
    name: { 'zh-HK': '', en: '' },
    options: [emptyOption(1)],
  }
}

function ensureDefaults() {
  if (!groupModel.value.length) {
    groupModel.value = [
      {
        code: 'default',
        name: { 'zh-HK': '款式', en: 'Style' },
        options: [{ code: 'default', name: { 'zh-HK': '默認', en: 'Default' }, image: null }],
      },
    ]
  }
  if (!skuModel.value.length) {
    const g = groupModel.value[0]
    const o = g.options[0]
    skuModel.value = [
      {
        code: o.code || 'default',
        price: 0,
        compare_at: 0,
        agent_price: 0,
        cost_price: 0,
        stock: 0,
        image: null,
        status: 1,
        option_refs: [`${g.code}:${o.code}`],
      },
    ]
  }
}

watch(
  () => [props.groups.length, props.skus.length],
  () => ensureDefaults(),
  { immediate: true },
)

function addGroup() {
  if (groupModel.value.length >= MAX_GROUPS) {
    message.warning(`最多 ${MAX_GROUPS} 個規格組`)
    return
  }
  const next = groupModel.value.length + 1
  groupModel.value = [...groupModel.value, emptyGroup(next)]
}

function removeGroup(idx: number) {
  const next = groupModel.value.filter((_, i) => i !== idx)
  groupModel.value = next.length ? next : [emptyGroup(1)]
}

function addOption(gIdx: number) {
  const g = groupModel.value[gIdx]
  const n = g.options.length + 1
  const groups = groupModel.value.map((x, i) =>
    i === gIdx ? { ...x, options: [...x.options, emptyOption(n)] } : x,
  )
  groupModel.value = groups
}

function removeOption(gIdx: number, oIdx: number) {
  const groups = groupModel.value.map((x, i) => {
    if (i !== gIdx) return x
    const options = x.options.filter((_, j) => j !== oIdx)
    return { ...x, options: options.length ? options : [emptyOption(1)] }
  })
  groupModel.value = groups
}

function normalizeGroups(): SpecGroupDraft[] | null {
  const used = new Set<string>()
  const out: SpecGroupDraft[] = []
  for (let gi = 0; gi < groupModel.value.length; gi++) {
    const g = groupModel.value[gi]
    const gcode = ensureSpecCode(g.code, g.name['zh-HK'], g.name.en, `g${gi + 1}`)
    if (used.has(gcode)) {
      message.error(`規格組代碼重複：${gcode}`)
      return null
    }
    used.add(gcode)
    if (!g.name['zh-HK'].trim() || !g.name.en.trim()) {
      message.error('請填寫規格組中英文名稱')
      return null
    }
    const oUsed = new Set<string>()
    const options: SpecOptionDraft[] = []
    for (let oi = 0; oi < g.options.length; oi++) {
      const o = g.options[oi]
      const ocode = ensureSpecCode(o.code, o.name['zh-HK'], o.name.en, `o${oi + 1}`)
      if (oUsed.has(ocode)) {
        message.error(`選項代碼重複：${gcode}:${ocode}`)
        return null
      }
      oUsed.add(ocode)
      if (!o.name['zh-HK'].trim() || !o.name.en.trim()) {
        message.error('請填寫選項中英文名稱')
        return null
      }
      options.push({
        code: ocode,
        name: { 'zh-HK': o.name['zh-HK'].trim(), en: o.name.en.trim() },
        image: o.image || null,
      })
    }
    out.push({
      code: gcode,
      name: { 'zh-HK': g.name['zh-HK'].trim(), en: g.name.en.trim() },
      options,
    })
  }
  return out
}

function cartesian(groups: SpecGroupDraft[]): Array<{ refs: string[]; code: string; label: string }> {
  let acc: Array<{ refs: string[]; parts: string[]; labels: string[] }> = [
    { refs: [], parts: [], labels: [] },
  ]
  for (const g of groups) {
    const next: typeof acc = []
    for (const prev of acc) {
      for (const o of g.options) {
        next.push({
          refs: [...prev.refs, `${g.code}:${o.code}`],
          parts: [...prev.parts, o.code],
          labels: [...prev.labels, o.name['zh-HK'] || o.code],
        })
      }
    }
    acc = next
  }
  return acc.map((x) => ({
    refs: x.refs,
    code: x.parts.join('_').slice(0, 64),
    label: x.labels.join(' / '),
  }))
}

function generateSkus() {
  const groups = normalizeGroups()
  if (!groups) return
  if (groups.length > MAX_GROUPS) {
    message.warning(`最多 ${MAX_GROUPS} 個規格組`)
    return
  }
  const combos = cartesian(groups)
  if (combos.length > MAX_SKUS) {
    message.error(`組合共 ${combos.length} 個，超過上限 ${MAX_SKUS}，請減少選項`)
    return
  }
  groupModel.value = groups
  const prev = new Map(skuModel.value.map((s) => [refsKey(s.option_refs), s]))
  const next: SkuDraft[] = combos.map((c) => {
    const old = prev.get(refsKey(c.refs))
    if (old) {
      return {
        ...old,
        code: old.code || c.code,
        option_refs: c.refs,
        status: old.status ?? 1,
      }
    }
    return {
      code: c.code,
      price: 0,
      compare_at: 0,
      agent_price: 0,
      cost_price: 0,
      stock: 0,
      image: null,
      status: 1,
      option_refs: c.refs,
    }
  })
  skuModel.value = next
  message.success(`已生成 ${next.length} 個 SKU`)
}

function applyTemplate() {
  const id = applyTemplateId.value
  if (id == null) {
    message.warning('請選擇規格模板')
    return
  }
  if (groupModel.value.length >= MAX_GROUPS) {
    message.warning(`最多 ${MAX_GROUPS} 個規格組`)
    return
  }
  const tpl = templates.value.find((t) => t.id === id)
  if (!tpl) return
  if (groupModel.value.some((g) => g.code === tpl.code)) {
    message.warning(`已存在規格組 ${tpl.code}`)
    return
  }
  const options = (tpl.options || [])
    .filter((o) => o.status !== 0)
    .map((o) => ({
      code: o.code,
      name: { 'zh-HK': o.name['zh-HK'], en: o.name.en },
      image: null as string | null,
    }))
  if (!options.length) {
    message.warning('該模板沒有啟用選項')
    return
  }
  // 若仍是默認單檔占位，先替換
  const onlyDefault =
    groupModel.value.length === 1 &&
    groupModel.value[0].code === 'default' &&
    groupModel.value[0].options.length === 1 &&
    groupModel.value[0].options[0].code === 'default'
  const group: SpecGroupDraft = {
    code: tpl.code,
    name: { 'zh-HK': tpl.name['zh-HK'], en: tpl.name.en },
    options,
  }
  groupModel.value = onlyDefault ? [group] : [...groupModel.value, group]
  applyTemplateId.value = null
  message.success(`已套用模板「${tpl.name['zh-HK']}」，請點「生成 SKU」`)
}

function removeSku(idx: number) {
  if (skuModel.value.length <= 1) {
    message.warning('至少保留一個 SKU')
    return
  }
  skuModel.value = skuModel.value.filter((_, i) => i !== idx)
}

function skuLabel(s: SkuDraft) {
  return s.option_refs.map((r) => r.split(':')[1] || r).join(' / ') || s.code
}

/** 供父組件保存前校驗與歸一化 */
function prepareForSave(): { groups: SpecGroupDraft[]; skus: SkuDraft[] } | null {
  const groups = normalizeGroups()
  if (!groups) return null
  if (!skuModel.value.length) {
    message.error('請至少配置一個 SKU')
    return null
  }
  if (skuModel.value.length > MAX_SKUS) {
    message.error(`SKU 最多 ${MAX_SKUS} 個`)
    return null
  }
  const validRefs = new Set<string>()
  for (const g of groups) {
    for (const o of g.options) validRefs.add(`${g.code}:${o.code}`)
  }
  const skus: SkuDraft[] = []
  const codes = new Set<string>()
  for (const s of skuModel.value) {
    const code = ensureSpecCode(s.code, skuLabel(s), s.code, 'sku')
    if (codes.has(code)) {
      message.error(`SKU 代碼重複：${code}`)
      return null
    }
    codes.add(code)
    const refs = (s.option_refs || []).filter((r) => validRefs.has(r))
    if (refs.length !== groups.length) {
      message.error(`SKU「${code}」規格引用不完整，請重新生成 SKU`)
      return null
    }
    skus.push({
      code,
      price: Number(s.price || 0),
      compare_at: Number(s.compare_at || 0),
      agent_price: Number(s.agent_price || 0),
      cost_price: Number(s.cost_price || 0),
      stock: Math.max(0, Number(s.stock || 0)),
      image: s.image || null,
      status: s.status === 0 ? 0 : 1,
      option_refs: refs,
    })
  }
  groupModel.value = groups
  skuModel.value = skus
  return { groups, skus }
}

defineExpose({ prepareForSave, generateSkus, MAX_GROUPS, MAX_SKUS })
</script>

<template>
  <n-card size="small" title="銷售規格 / SKU" class="sku-editor">
    <p class="hint">
      最多 {{ MAX_GROUPS }} 組規格、{{ MAX_SKUS }} 個 SKU。可套用「規格模板」，改完選項後點「生成 SKU」。
      當前組合約 {{ comboCount }} 個。
    </p>

    <n-space style="margin-bottom: 0.75rem" align="center">
      <n-select
        v-model:value="applyTemplateId"
        clearable
        placeholder="選擇規格模板"
        :options="templateOptions"
        style="width: 14rem"
      />
      <n-button @click="applyTemplate">套用模板</n-button>
      <n-button type="primary" secondary @click="generateSkus">生成 SKU</n-button>
      <n-button @click="addGroup">加規格組</n-button>
    </n-space>

    <h4 class="sub">規格組（{{ groupModel.length }}）</h4>
    <div v-for="(g, gIdx) in groupModel" :key="gIdx" class="group-block">
      <div class="group-head">
        <strong>規格組 {{ gIdx + 1 }}</strong>
        <n-button size="tiny" @click="removeGroup(gIdx)">刪組</n-button>
      </div>
      <div class="table-wrap">
        <table class="sku-table group-meta-table">
          <thead>
            <tr>
              <th>組代碼</th>
              <th>組繁中</th>
              <th>組英文</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>
                <n-input v-model:value="g.code" placeholder="如 color" size="small" style="width: 8rem" />
              </td>
              <td>
                <n-input v-model:value="g.name['zh-HK']" placeholder="顏色" size="small" style="width: 8rem" />
              </td>
              <td>
                <n-input v-model:value="g.name.en" placeholder="Color" size="small" style="width: 8rem" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="table-wrap">
        <table class="sku-table">
          <thead>
            <tr>
              <th>#</th>
              <th>選項代碼</th>
              <th>繁中</th>
              <th>英文</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="(o, oIdx) in g.options" :key="oIdx">
              <td class="col-idx">{{ oIdx + 1 }}</td>
              <td>
                <n-input v-model:value="o.code" placeholder="選項代碼" size="small" style="width: 7.5rem" />
              </td>
              <td>
                <n-input v-model:value="o.name['zh-HK']" placeholder="繁中" size="small" style="width: 7.5rem" />
              </td>
              <td>
                <n-input v-model:value="o.name.en" placeholder="英文" size="small" style="width: 7.5rem" />
              </td>
              <td>
                <n-button size="tiny" @click="removeOption(gIdx, oIdx)">刪</n-button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <n-button size="tiny" style="margin-top: 0.5rem" @click="addOption(gIdx)">加選項</n-button>
    </div>

    <h4 class="sub">SKU 列表（{{ skuModel.length }}）</h4>
    <p class="hint sku-hint">
      每一行 = 一組規格選項的可賣組合；「規格組合」對應上方規格組選項，「規格引用」是保存用的組:選項代碼。
    </p>
    <div class="table-wrap">
      <table class="sku-table">
        <thead>
          <tr>
            <th class="col-combo">規格組合</th>
            <th class="col-refs">規格引用</th>
            <th>SKU 代碼</th>
            <th>售價</th>
            <th>劃線價</th>
            <th>代理價</th>
            <th>成本價</th>
            <th>庫存</th>
            <th>啟用</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="(s, sIdx) in skuModel" :key="sIdx">
            <td class="col-combo">
              <strong>{{ skuLabel(s) }}</strong>
            </td>
            <td class="col-refs">
              <code v-for="(ref, ri) in s.option_refs" :key="ri" class="ref-chip">{{ ref }}</code>
            </td>
            <td>
              <n-input v-model:value="s.code" placeholder="代碼" size="small" style="width: 7.5rem" />
            </td>
            <td>
              <n-input-number v-model:value="s.price" size="small" :show-button="false" style="width: 5.5rem" />
            </td>
            <td>
              <n-input-number v-model:value="s.compare_at" size="small" :show-button="false" style="width: 5.5rem" />
            </td>
            <td>
              <n-input-number
                v-model:value="s.agent_price"
                size="small"
                :min="0"
                :show-button="false"
                style="width: 5.5rem"
              />
            </td>
            <td>
              <n-input-number
                v-model:value="s.cost_price"
                size="small"
                :min="0"
                :show-button="false"
                style="width: 5.5rem"
              />
            </td>
            <td>
              <n-input-number
                v-model:value="s.stock"
                size="small"
                :min="0"
                :show-button="false"
                style="width: 4.5rem"
              />
            </td>
            <td>
              <n-switch
                :value="s.status !== 0"
                size="small"
                @update:value="(v) => (s.status = v ? 1 : 0)"
              />
            </td>
            <td>
              <n-button size="tiny" @click="removeSku(sIdx)">刪</n-button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </n-card>
</template>

<style scoped>
.hint {
  margin: 0 0 0.75rem;
  color: #6d675f;
  font-size: 0.8125rem;
}
.sku-hint {
  margin-top: -0.25rem;
}
.group-block {
  border: 1px solid #eee;
  border-radius: 0.375rem;
  padding: 0.75rem 1rem;
  margin-bottom: 0.75rem;
  background: #fafaf8;
}
.group-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}
.sub {
  margin: 0.5rem 0 0.75rem;
  font-size: 0.9375rem;
}
.group-meta-table {
  min-width: 28rem;
  margin-bottom: 0.5rem;
}
.col-idx {
  width: 2rem;
  color: #8a8378;
}
.table-wrap {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  margin-bottom: 0.25rem;
}
.sku-table {
  width: 100%;
  min-width: 52rem;
  border-collapse: collapse;
  background: #fff;
}
.sku-table th,
.sku-table td {
  border-bottom: 1px solid #eee;
  padding: 0.45rem 0.4rem;
  text-align: left;
  font-size: 0.8125rem;
  vertical-align: middle;
}
.sku-table th {
  color: #6d675f;
  font-weight: 600;
  white-space: nowrap;
  background: #fafaf8;
}
.col-combo {
  min-width: 6.5rem;
  white-space: nowrap;
}
.col-refs {
  min-width: 9rem;
}
.ref-chip {
  display: inline-block;
  margin: 0.1rem 0.25rem 0.1rem 0;
  padding: 0.1rem 0.35rem;
  border-radius: 0.25rem;
  background: #f3f0ea;
  color: #5a554c;
  font-size: 0.6875rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
</style>
