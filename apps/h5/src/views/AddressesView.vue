<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { showConfirmDialog, showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import {
  formatHkAddressLine,
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
const showAreaPicker = ref(false)
const form = reactive({
  id: 0,
  receiver: '',
  phone: '',
  region: '',
  district: '',
  detail: '',
  is_default: true,
})

const uiLocale = computed(() => (locale.value === 'en' ? 'en' : 'zh-HK') as HkLocale)

const areaDisplay = computed(() => {
  if (!form.district) return ''
  return formatHkDistrictLabel(form.district, form.region, uiLocale.value)
})

function addressLabel(row: Address) {
  return formatHkAddressLine(row.region, row.district, row.detail, uiLocale.value)
}

async function load() {
  const data = await fetchAddresses()
  items.value = data.items
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
    region: '',
    district: '',
    detail: '',
    is_default: items.value.length === 0,
  })
}

function pickArea(opt: HkDistrictOption) {
  form.region = opt.region
  form.district = opt.district
  showAreaPicker.value = false
}

async function submit() {
  if (!form.region || !form.district) {
    showToast(t('checkout.pickDistrict'))
    return
  }
  // 編輯目標已被刪除：改為新增，避免 PUT 已不存在的 id
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
    if (wasEditing) reset()
    showToast(t('checkout.deleted'))
  } catch (error) {
    showToast(error instanceof ApiError && error.message ? error.message : t('checkout.fail'))
  }
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('checkout.addresses')" />
    <van-cell-group inset>
      <van-cell
        v-for="row in items"
        :key="row.id"
        :label="addressLabel(row)"
        is-link
        :class="{ editing: form.id === row.id }"
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

    <div class="form">
      <h2>{{ form.id ? t('checkout.editAddress') : t('checkout.newAddress') }}</h2>
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
        @click="showAreaPicker = true"
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
          <span>{{ t('checkout.pickDistrict') }}</span>
          <button type="button" class="picker-close" @click="showAreaPicker = false">{{ t('checkout.closePicker') }}</button>
        </div>
        <div class="picker-body">
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
              @click="pickArea(opt)"
            >
              <span class="no">{{ opt.no }}.</span>
              <span class="name">{{ uiLocale === 'en' ? opt.districtEn : opt.district }}</span>
            </button>
          </section>
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
.picker {
  display: flex;
  flex-direction: column;
  max-height: 75vh;
}
.picker-hd {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.875rem 1rem;
  font-weight: 600;
  font-size: 0.9375rem;
  border-bottom: 1px solid #eee;
}
.picker-close {
  border: 0;
  background: transparent;
  color: #666;
  font-size: 0.875rem;
}
.picker-body {
  overflow: auto;
  /* 底部多留空間，避免最後一項（18 離島區）被遮擋／滑不到 */
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
