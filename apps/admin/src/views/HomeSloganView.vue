<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { NButton, NCard, NFormItem, NInput, NSpace, useMessage } from 'naive-ui'
import { api } from '../api'

const message = useMessage()
const slogan = ref({ zh: '', en: '' })

async function load() {
  const ops = await api<{ footer_slogan: { 'zh-HK': string; en: string } }>('/api/v1/admin/home-ops', {}, true)
  slogan.value = {
    zh: ops.footer_slogan?.['zh-HK'] || '',
    en: ops.footer_slogan?.en || '',
  }
}

async function save() {
  await api(
    '/api/v1/admin/home-ops',
    {
      method: 'PUT',
      body: JSON.stringify({
        footer_slogan: {
          'zh-HK': slogan.value.zh,
          en: slogan.value.en,
        },
      }),
    },
    true,
  )
  message.success('頁腳標語已保存')
  await load()
}

onMounted(load)
</script>

<template>
  <n-card title="頁腳標語">
    <n-form-item label="繁中"><n-input v-model:value="slogan.zh" /></n-form-item>
    <n-form-item label="英文"><n-input v-model:value="slogan.en" /></n-form-item>
    <n-space>
      <n-button type="primary" @click="save">保存</n-button>
    </n-space>
  </n-card>
</template>
