<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showConfirmDialog, showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import {
  formatHkAddressLine,
  formatHkAreaLabel,
  formatHkDistrictLabel,
  HK_DISTRICT_GROUPS,
  type HkDistrictOption,
  type HkLocale,
} from '../data/hkDistricts'
import { ApiError } from '../api/client'
import { deleteAddress, fetchAddresses, saveAddress, type Address } from '../api/trade'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const items = ref<Address[]>([])
const loaded = ref(false)
const showForm = ref(false)
const showAreaPicker = ref(false)
/** district = 選十八區；locality = 選社區地點 */
const pickerStep = ref<'district' | 'locality'>('district')
const pickerDistrict = ref<HkDistrictOption | null>(null)
const form = reactive({
  id: 0,
  receiver: '',
  phone: '',
  region: '',
  district: '',
  area: '',
  detail: '',
  is_default: true,
})

const uiLocale = computed(() => (locale.value === 'en' ? 'en' : 'zh-HK') as HkLocale)

const areaDisplay = computed(() => {
  if (!form.district) return ''
  const districtLabel = formatHkDistrictLabel(form.district, form.region, uiLocale.value)
  if (!form.area) return districtLabel
  const areaLabel = formatHkAreaLabel(form.area, form.district, uiLocale.value)
  return `${districtLabel} · ${areaLabel}`
})

const pickerTitle = computed(() =>
  pickerStep.value === 'locality' ? t('checkout.pickLocalityTitle') : t('checkout.pickDistrictTitle'),
)

function addressLabel(row: Address) {
  return formatHkAddressLine(row.region, row.district, row.detail, uiLocale.value, row.area || '')
}

function reset() {
  Object.assign(form, {
    id: 0,
    receiver: '',
    phone: '',
    region: '',
    district: '',
    area: '',
    detail: '',
    is_default: items.value.length === 0,
  })
}

async function load() {
  const data = await fetchAddresses()
  items.value = data.items
  loaded.value = true
  if (items.value.length === 0) {
    reset()
    showForm.value = true
  }
}

onMounted(load)

function startAdd() {
  reset()
  showForm.value = true
}

function edit(row: Address) {
  Object.assign(form, {
    id: row.id,
    receiver: row.receiver,
    phone: row.phone,
    region: row.region,
    district: row.district,
    area: row.area || '',
    detail: row.detail,
    is_default: row.is_default,
  })
  showForm.value = true
}

function cancelForm() {
  reset()
  if (items.value.length === 0) {
    showForm.value = true
    return
  }
  showForm.value = false
}

function openAreaPicker() {
  pickerStep.value = 'district'
  pickerDistrict.value = null
  showAreaPicker.value = true
}

function pickDistrict(opt: HkDistrictOption) {
  pickerDistrict.value = opt
  pickerStep.value = 'locality'
}

function pickLocality(area: string) {
  const opt = pickerDistrict.value
  if (!opt) return
  form.region = opt.region
  form.district = opt.district
  form.area = area
  showAreaPicker.value = false
  pickerStep.value = 'district'
  pickerDistrict.value = null
}

function backToDistrictStep() {
  pickerStep.value = 'district'
  pickerDistrict.value = null
}

async function submit() {
  if (!form.region || !form.district) {
    showToast(t('checkout.pickDistrict'))
    return
  }
  if (!form.area) {
    showToast(t('checkout.pickLocality'))
    return
  }
  const editingId = form.id
  if (editingId && !items.value.some((a) => a.id === editingId)) {
    form.id = 0
  }
  try {
    await saveAddress(
      {
        receiver: form.receiver,
        phone: form.phone,
        region: form.region,
        district: form.district,
        area: form.area,
        detail: form.detail,
        is_default: form.is_default,
      },
      form.id || undefined,
    )
    showToast(t('checkout.saved'))
    await load()
    reset()
    showForm.value = items.value.length === 0
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : ''
    if (redirect) router.replace(redirect)
  } catch (error) {
    showToast(error instanceof ApiError && error.message ? error.message : error instanceof Error ? error.message : t('checkout.fail'))
  }
}

async function remove(id: number) {
  try {
    await showConfirmDialog({
      title: t('checkout.deleteAddrConfirm'),
      confirmButtonText: t('cart.delete'),
      confirmButtonColor: '#c45c26',
    })
  } catch {
    return
  }
  try {
    const wasEditing = form.id === id
    await deleteAddress(id)
    await load()
    if (wasEditing || items.value.length === 0) {
      reset()
      showForm.value = true
    }
    showToast(t('checkout.deleted'))
  } catch (error) {
    showToast(error instanceof ApiError && error.message ? error.message : t('checkout.fail'))
  }
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('checkout.addresses')" />

    <van-cell-group v-if="items.length" inset>
      <van-cell
        v-for="row in items"
        :key="row.id"
        :label="addressLabel(row)"
        is-link
        :class="{ editing: showForm && form.id === row.id }"
        @click="edit(row)"
      >
        <template #title>
          <div class="addr-title">
            <span class="who">{{ row.receiver }} {{ row.phone }}</span>
            <span v-if="row.is_default" class="default-tag">{{ t('checkout.defaultTag') }}</span>
          </div>
        </template>
        <template #value>
          <button type="button" class="del" @click.stop="remove(row.id)">{{ t('cart.delete') }}</button>
        </template>
      </van-cell>
    </van-cell-group>

    <div v-if="loaded && items.length && !showForm" class="add-wrap">
      <van-button block type="primary" color="var(--sm-primary)" round @click="startAdd">
        {{ t('checkout.addShippingAddress') }}
      </van-button>
    </div>

    <div v-if="showForm" class="form">
      <div class="form-hd">
        <h2>{{ form.id ? t('checkout.editAddress') : t('checkout.newAddress') }}</h2>
        <button v-if="items.length" type="button" class="cancel" @click="cancelForm">
          {{ t('checkout.cancelForm') }}
        </button>
      </div>
      <van-field
        v-model="form.receiver"
        :label="t('checkout.receiver')"
        :placeholder="t('checkout.receiverPh')"
      />
      <van-field
        v-model="form.phone"
        :label="t('checkout.phone')"
        :placeholder="t('checkout.phonePh')"
      />
      <van-field
        is-link
        readonly
        :label="t('checkout.area')"
        :model-value="areaDisplay"
        :placeholder="t('checkout.pickDistrict')"
        @click="openAreaPicker"
      />
      <van-field
        v-model="form.detail"
        :label="t('checkout.detail')"
        :placeholder="t('checkout.detailPh')"
        type="textarea"
      />
      <van-cell :title="t('checkout.defaultAddr')">
        <template #right-icon><van-switch v-model="form.is_default" size="1.125rem" /></template>
      </van-cell>
      <van-button block type="primary" color="var(--sm-primary)" round style="margin-top: 1rem" @click="submit">
        {{ t('checkout.saveAddress') }}
      </van-button>
    </div>

    <van-popup v-model:show="showAreaPicker" position="bottom" round :style="{ maxHeight: '75%' }">
      <div class="picker">
        <div class="picker-hd">
          <button
            v-if="pickerStep === 'locality'"
            type="button"
            class="picker-back"
            @click="backToDistrictStep"
          >
            ‹ {{ t('checkout.backToDistrict') }}
          </button>
          <span v-else class="picker-spacer" />
          <span class="picker-title">{{ pickerTitle }}</span>
          <button type="button" class="picker-close" @click="showAreaPicker = false">
            {{ t('checkout.closePicker') }}
          </button>
        </div>
        <div v-if="pickerStep === 'district'" class="picker-body">
          <section v-for="group in HK_DISTRICT_GROUPS" :key="group.region" class="group">
            <h3 class="group-title">
              {{ uiLocale === 'en' ? group.regionEn : group.region }}
            </h3>
            <button
              v-for="opt in group.items"
              :key="opt.district"
              type="button"
              class="opt"
              :class="{ on: form.district === opt.district }"
              @click="pickDistrict(opt)"
            >
              <span class="no">{{ opt.no }}.</span>
              <span class="name">{{ uiLocale === 'en' ? opt.districtEn : opt.district }}</span>
            </button>
          </section>
        </div>
        <div v-else-if="pickerDistrict" class="picker-body">
          <h3 class="group-title">
            {{ uiLocale === 'en' ? pickerDistrict.districtEn : pickerDistrict.district }}
          </h3>
          <button
            v-for="loc in pickerDistrict.areas"
            :key="loc.area"
            type="button"
            class="opt"
            :class="{ on: form.district === pickerDistrict.district && form.area === loc.area }"
            @click="pickLocality(loc.area)"
          >
            <span class="name">{{ uiLocale === 'en' ? loc.areaEn : loc.area }}</span>
          </button>
        </div>
      </div>
    </van-popup>
  </div>
</template>

<style scoped>
.addr-title {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  max-width: 100%;
}
.who {
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.default-tag {
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  padding: 0.05rem 0.4rem;
  border-radius: 0.25rem;
  font-size: 0.6875rem;
  font-weight: 700;
  line-height: 1.3;
  color: #fff;
  background: var(--sm-primary);
}
.editing {
  background: color-mix(in srgb, var(--sm-primary) 6%, #fff);
}
.add-wrap {
  margin: 1rem 0.75rem 2rem;
}
.form {
  margin: 1rem 0.75rem 2rem;
  padding: 0.75rem;
  background: #fff;
  border-radius: 0.75rem;
}
.form-hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.5rem;
}
.form h2 {
  margin: 0;
  font-size: 0.9375rem;
}
.cancel {
  border: 0;
  background: transparent;
  color: #666;
  font-size: 0.875rem;
  padding: 0.25rem 0;
}
.del {
  border: 0;
  background: transparent;
  color: #c45c26;
  font-size: 0.75rem;
}
.picker {
  display: flex;
  flex-direction: column;
  max-height: 75vh;
}
.picker-hd {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 0.5rem;
  padding: 0.875rem 1rem;
  font-weight: 600;
  font-size: 0.9375rem;
  border-bottom: 1px solid #eee;
}
.picker-title {
  text-align: center;
}
.picker-spacer {
  width: 1px;
}
.picker-back,
.picker-close {
  border: 0;
  background: transparent;
  color: #666;
  font-size: 0.875rem;
  font-weight: 500;
  padding: 0;
}
.picker-back {
  justify-self: start;
  color: var(--sm-primary);
}
.picker-close {
  justify-self: end;
}
.picker-body {
  overflow: auto;
  padding: 0.5rem 0 calc(3.5rem + env(safe-area-inset-bottom, 0px));
  -webkit-overflow-scrolling: touch;
}
.group-title {
  margin: 0.75rem 1rem 0.35rem;
  font-size: 0.8125rem;
  font-weight: 700;
  color: #111;
}
.opt {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  width: 100%;
  border: 0;
  background: transparent;
  text-align: left;
  padding: 0.65rem 1rem;
  font-size: 0.875rem;
  line-height: 1.35;
  color: #333;
}
.opt .no {
  flex: 0 0 1.75rem;
  color: #888;
  font-variant-numeric: tabular-nums;
}
.opt .name {
  flex: 1;
}
.opt.on {
  background: color-mix(in srgb, var(--sm-primary) 10%, #fff);
  color: var(--sm-primary);
  font-weight: 600;
}
.opt.on .no {
  color: var(--sm-primary);
}
</style>
