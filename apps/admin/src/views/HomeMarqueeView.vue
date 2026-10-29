<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { NButton, NCard, NFormItem, NInput, NSelect, NSpace, NSwitch, useMessage } from 'naive-ui'
import { api } from '../api'

type Marquee = {
  text: { 'zh-HK': string; en: string }
  link_type: string
  link_value: string
  status: number
}

const message = useMessage()
const marquee = ref({
  text_zh: '',
  text_en: '',
  link_type: 'none',
  link_value: '',
  status: true,
})

const linkTypes = [
  { label: '無跳轉', value: 'none' },
  { label: '商品 code', value: 'product' },
  { label: '分類 code', value: 'category' },
  { label: '文章 code', value: 'article' },
  { label: 'URL / 路徑', value: 'url' },
]

async function load() {
  const ops = await api<{ marquee: Marquee }>('/api/v1/admin/home-ops', {}, true)
  marquee.value = {
    text_zh: ops.marquee?.text?.['zh-HK'] || '',
    text_en: ops.marquee?.text?.en || '',
    link_type: ops.marquee?.link_type || 'none',
    link_value: ops.marquee?.link_value || '',
    status: (ops.marquee?.status ?? 1) === 1,
  }
}

async function save() {
  await api(
    '/api/v1/admin/home-ops',
    {
      method: 'PUT',
      body: JSON.stringify({
        marquee: {
          text_zh: marquee.value.text_zh,
          text_en: marquee.value.text_en,
          link_type: marquee.value.link_type,
          link_value: marquee.value.link_value,
          status: marquee.value.status ? 1 : 0,
        },
      }),
    },
    true,
  )
  message.success('廣告字幕已保存')
  await load()
}

onMounted(load)
</script>

<template>
  <n-card title="廣告字幕（一條）">
    <n-form-item label="繁中文案"><n-input v-model:value="marquee.text_zh" /></n-form-item>
    <n-form-item label="英文文案"><n-input v-model:value="marquee.text_en" /></n-form-item>
    <n-form-item label="跳轉類型"><n-select v-model:value="marquee.link_type" :options="linkTypes" /></n-form-item>
    <n-form-item v-if="marquee.link_type !== 'none'" label="跳轉目標">
      <n-input v-model:value="marquee.link_value" placeholder="商品/分類/文章 code 或 URL" />
    </n-form-item>
    <n-form-item label="啟用"><n-switch v-model:value="marquee.status" /></n-form-item>
    <n-space>
      <n-button type="primary" @click="save">保存</n-button>
    </n-space>
  </n-card>
</template>
