<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { deleteAddress, fetchAddresses, saveAddress, type Address } from '../api/trade'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const items = ref<Address[]>([])
const regions = ref<string[]>([])
const districtsByRegion = ref<Record<string, string[]>>({})
const form = reactive({
  id: 0,
  receiver: '',
  phone: '',
  region: '九龍',
  district: '',
  detail: '',
  is_default: true,
})

const districtOptions = computed(() => {
  const list = districtsByRegion.value[form.region] || []
  // 編輯舊數據：若當前分區不在清單內，暫列一項以免空白
  if (form.district && !list.includes(form.district)) {
    return [form.district, ...list]
  }
  return list
})

watch(
  () => form.region,
  (region, prev) => {
    if (prev === undefined || region === prev) return
    const list = districtsByRegion.value[region] || []
    if (!list.includes(form.district)) {
      form.district = ''
    }
  },
)

async function load() {
  const data = await fetchAddresses()
  items.value = data.items
  regions.value = data.regions?.length ? data.regions : ['香港島', '九龍', '新界']
  districtsByRegion.value = data.districts_by_region || {}
  if (!form.district && districtOptions.value.length) {
    // keep empty until user picks
  }
}

onMounted(load)

function edit(row: Address) {
  Object.assign(form, { ...row, id: row.id })
}

function reset() {
  Object.assign(form, {
    id: 0,
    receiver: '',
    phone: '',
    region: '九龍',
    district: '',
    detail: '',
    is_default: true,
  })
}

async function submit() {
  if (!form.district) {
    showToast(t('checkout.pickDistrict'))
    return
  }
  try {
    await saveAddress(
      {
        receiver: form.receiver,
        phone: form.phone,
        region: form.region,
        district: form.district,
        detail: form.detail,
        is_default: form.is_default,
      },
      form.id || undefined,
    )
    showToast(t('checkout.saved'))
    reset()
    await load()
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : ''
    if (redirect) router.replace(redirect)
  } catch (error) {
    showToast(error instanceof Error ? error.message : t('checkout.fail'))
  }
}

async function remove(id: number) {
  await deleteAddress(id)
  await load()
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('checkout.addresses')" />
    <van-cell-group inset>
      <van-cell
        v-for="row in items"
        :key="row.id"
        :title="`${row.receiver} ${row.phone}`"
        :label="`${row.region} ${row.district} ${row.detail}`"
        is-link
        @click="edit(row)"
      >
        <template #value>
          <button type="button" class="del" @click.stop="remove(row.id)">{{ t('cart.delete') }}</button>
        </template>
      </van-cell>
    </van-cell-group>

    <div class="form">
      <h2>{{ form.id ? t('checkout.editAddress') : t('checkout.newAddress') }}</h2>
      <van-field v-model="form.receiver" :label="t('checkout.receiver')" />
      <van-field v-model="form.phone" :label="t('checkout.phone')" />
      <van-field :label="t('checkout.region')">
        <template #input>
          <div class="region-chips">
            <button
              v-for="r in regions"
              :key="r"
              type="button"
              class="chip"
              :class="{ on: form.region === r }"
              @click="form.region = r"
            >
              {{ r }}
            </button>
          </div>
        </template>
      </van-field>
      <van-field :label="t('checkout.district')">
        <template #input>
          <select v-model="form.district">
            <option value="" disabled>{{ t('checkout.pickDistrict') }}</option>
            <option v-for="d in districtOptions" :key="d" :value="d">{{ d }}</option>
          </select>
        </template>
      </van-field>
      <van-field v-model="form.detail" :label="t('checkout.detail')" type="textarea" />
      <van-cell :title="t('checkout.defaultAddr')">
        <template #right-icon><van-switch v-model="form.is_default" size="1.125rem" /></template>
      </van-cell>
      <van-button block type="primary" color="var(--sm-primary)" round style="margin-top: 1rem" @click="submit">
        {{ t('checkout.saveAddress') }}
      </van-button>
    </div>
  </div>
</template>

<style scoped>
.form {
  margin: 1rem 0.75rem 2rem;
  padding: 0.75rem;
  background: #fff;
  border-radius: 0.75rem;
}
.form h2 {
  margin: 0 0 0.5rem;
  font-size: 0.9375rem;
}
.del {
  border: 0;
  background: transparent;
  color: #c45c26;
  font-size: 0.75rem;
}
select {
  border: 0;
  width: 100%;
  background: transparent;
  font: inherit;
  color: inherit;
}
.region-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  width: 100%;
}
.chip {
  border: 1px solid #e5e5e5;
  background: #f7f7f7;
  color: #333;
  border-radius: 0.375rem;
  padding: 0.35rem 0.75rem;
  font-size: 0.8125rem;
  line-height: 1.2;
}
.chip.on {
  border-color: var(--sm-primary);
  background: color-mix(in srgb, var(--sm-primary) 12%, #fff);
  color: var(--sm-primary);
  font-weight: 600;
}
</style>
