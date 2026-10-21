<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import SubPageNav from '../components/SubPageNav.vue'
import { api } from '../api/client'

type Row = {
  id: number
  delta: number
  balance_after: number
  type: string
  remark: string
  created_at: string
}

const BILL_TYPES = [
  'order_deduct',
  'order_release',
  'commission_to_balance',
  'aftersale_refund',
  'admin_adjust',
] as const

const { t } = useI18n()
const items = ref<Row[]>([])
const filter = ref('')
const loading = ref(false)

function typeLabel(type: string) {
  const key = `bill.types.${type}`
  const label = t(key)
  return label === key ? type : label
}

async function load() {
  loading.value = true
  try {
    const qs = new URLSearchParams()
    if (filter.value) qs.set('type', filter.value)
    const q = qs.toString() ? `?${qs}` : ''
    const data = await api<{ items: Row[] }>(`/api/v1/balance-logs${q}`, {}, 'member')
    items.value = data.items
  } catch {
    items.value = []
  } finally {
    loading.value = false
  }
}

watch(filter, () => {
  void load()
})

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('bill.title')" />
    <div class="filters">
      <button type="button" :class="{ on: filter === '' }" @click="filter = ''">
        {{ t('bill.filterAll') }}
      </button>
      <button
        v-for="ty in BILL_TYPES"
        :key="ty"
        type="button"
        :class="{ on: filter === ty }"
        @click="filter = ty"
      >
        {{ typeLabel(ty) }}
      </button>
    </div>
    <p v-if="loading" class="empty">{{ t('m7.loading') }}</p>
    <p v-else-if="!items.length" class="empty">{{ t('bill.empty') }}</p>
    <van-cell-group v-else inset>
      <van-cell
        v-for="row in items"
        :key="row.id"
        :title="row.remark || typeLabel(row.type)"
        :label="`${typeLabel(row.type)} · ${row.created_at}`"
        :value="(row.delta > 0 ? '+' : '') + Number(row.delta).toFixed(2)"
      />
    </van-cell-group>
  </div>
</template>

<style scoped>
.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  padding: 0.75rem 1rem 0.25rem;
}
.filters button {
  border: 1px solid #e8e4dc;
  background: #fff;
  color: #6d675f;
  font-size: 0.75rem;
  padding: 0.3rem 0.55rem;
  border-radius: 999px;
}
.filters button.on {
  border-color: var(--sm-primary);
  color: var(--sm-primary);
  background: color-mix(in srgb, var(--sm-primary) 8%, #fff);
}
.empty {
  text-align: center;
  color: #999;
  padding: 2rem;
}
</style>
