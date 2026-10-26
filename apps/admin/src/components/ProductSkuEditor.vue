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

    <div v-for="(g, gIdx) in groupModel" :key="gIdx" class="group-block">
      <div class="group-head">
        <strong>規格組 {{ gIdx + 1 }}</strong>
        <n-button size="tiny" @click="removeGroup(gIdx)">刪組</n-button>
      </div>
      <n-space>
        <n-form-item label="組代碼"><n-input v-model:value="g.code" style="width: 8rem" /></n-form-item>
        <n-form-item label="組繁中"><n-input v-model:value="g.name['zh-HK']" style="width: 8rem" /></n-form-item>
        <n-form-item label="組英文"><n-input v-model:value="g.name.en" style="width: 8rem" /></n-form-item>
      </n-space>
      <div v-for="(o, oIdx) in g.options" :key="oIdx" class="opt-row">
        <n-input v-model:value="o.code" placeholder="選項代碼" style="width: 7rem" />
        <n-input v-model:value="o.name['zh-HK']" placeholder="繁中" style="width: 7rem" />
        <n-input v-model:value="o.name.en" placeholder="英文" style="width: 7rem" />
        <n-button size="tiny" @click="removeOption(gIdx, oIdx)">刪</n-button>
      </div>
      <n-button size="tiny" style="margin-top: 0.35rem" @click="addOption(gIdx)">加選項</n-button>
    </div>

    <h4 class="sub">SKU 列表（{{ skuModel.length }}）</h4>
    <div v-for="(s, sIdx) in skuModel" :key="sIdx" class="sku-row">
      <span class="sku-lab" :title="s.option_refs.join(', ')">{{ skuLabel(s) }}</span>
      <n-input v-model:value="s.code" placeholder="代碼" style="width: 7rem" />
      <n-input-number v-model:value="s.price" placeholder="售價" style="width: 6.5rem" />
      <n-input-number v-model:value="s.compare_at" placeholder="劃線" style="width: 6.5rem" />
      <n-input-number v-model:value="s.agent_price" placeholder="代理" :min="0" style="width: 6.5rem" />
      <n-input-number v-model:value="s.cost_price" placeholder="成本" :min="0" style="width: 6.5rem" />
      <n-input-number v-model:value="s.stock" placeholder="庫存" :min="0" style="width: 5.5rem" />
      <n-switch
        :value="s.status !== 0"
        size="small"
        @update:value="(v) => (s.status = v ? 1 : 0)"
      />
      <n-button size="tiny" @click="removeSku(sIdx)">刪</n-button>
    </div>
  </n-card>
</template>

<style scoped>
.hint {
  margin: 0 0 0.75rem;
  color: #6d675f;
  font-size: 0.8125rem;
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
.opt-row,
.sku-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 0.4rem;
}
.sku-lab {
  min-width: 6rem;
  font-size: 0.8125rem;
  color: #444;
}
.sub {
  margin: 0.5rem 0 0.75rem;
  font-size: 0.9375rem;
}
</style>
