<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { postWithdraw } from '../api/m7'
import { ApiError } from '../api/client'

const { t } = useI18n()
const channel = ref<'balance' | 'fps'>('balance')
const amount = ref(100)
const fpsName = ref('')
const fpsId = ref('')

async function submit() {
  try {
    const res = await postWithdraw({
      channel: channel.value,
      amount: amount.value,
      fps_name: fpsName.value,
      fps_id: fpsId.value,
    })
    showToast(res.status === 'done' ? t('m7.withdrawDone') : t('m7.withdrawPending'))
  } catch (e) {
    showToast(e instanceof ApiError ? e.message : t('m7.withdrawFail'))
  }
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('m7.withdraw')" />
    <van-radio-group v-model="channel" style="margin: 1rem">
      <van-cell clickable @click="channel = 'balance'">
        <template #title>{{ t('m7.toBalance') }}</template>
        <template #right-icon><van-radio name="balance" /></template>
      </van-cell>
      <van-cell clickable @click="channel = 'fps'">
        <template #title>{{ t('m7.toFps') }}</template>
        <template #right-icon><van-radio name="fps" /></template>
      </van-cell>
    </van-radio-group>
    <van-field v-model="amount" type="number" :label="t('m7.amount')" />
    <template v-if="channel === 'fps'">
      <van-field v-model="fpsName" :label="t('m7.fpsName')" />
      <van-field v-model="fpsId" :label="t('m7.fpsId')" />
    </template>
    <div style="padding: 1rem">
      <van-button block round color="var(--sm-primary)" @click="submit">{{ t('m7.submitWithdraw') }}</van-button>
    </div>
  </div>
</template>

<style scoped>
</style>
