<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
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
const form = reactive({
  id: 0,
  receiver: '',
  phone: '',
  region: '九龍',
  district: '',
  detail: '',
  is_default: true,
})

async function load() {
  const data = await fetchAddresses()
  items.value = data.items
  regions.value = data.regions
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
          <select v-model="form.region">
            <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
          </select>
        </template>
      </van-field>
      <van-field v-model="form.district" :label="t('checkout.district')" />
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
}
</style>
