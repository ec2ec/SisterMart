<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SubPageNav from '../components/SubPageNav.vue'
import { api } from '../api/client'

type Row = {
  grant_id: number
  status: string
  expires_at: string | null
  coupon: {
    code: string
    name: { 'zh-HK': string; en: string }
    type: string
    threshold: number
    amount: number
  }
}

const { t, locale } = useI18n()
const tab = ref('unused')
const items = ref<Row[]>([])

async function load() {
  const data = await api<{ items: Row[] }>(`/api/v1/my-coupons?status=${tab.value}`, {}, 'member')
  items.value = data.items
}

onMounted(load)

function nameOf(row: Row) {
  return locale.value === 'en' ? row.coupon.name.en : row.coupon.name['zh-HK']
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('m6.myCoupons')" />
    <van-tabs v-model:active="tab" @change="load">
      <van-tab :title="t('m6.unused')" name="unused" />
      <van-tab :title="t('m6.used')" name="used" />
      <van-tab :title="t('m6.expired')" name="expired" />
    </van-tabs>
    <p v-if="!items.length" class="empty">{{ t('m6.noCoupons') }}</p>
    <van-cell-group v-else inset>
      <van-cell
        v-for="row in items"
        :key="row.grant_id"
        :title="nameOf(row)"
        :label="`${row.coupon.code} · ≥HK$${row.coupon.threshold}`"
        :value="row.status"
      />
    </van-cell-group>
  </div>
</template>

<style scoped>
.empty {
  text-align: center;
  color: #999;
  padding: 2rem;
}
</style>
