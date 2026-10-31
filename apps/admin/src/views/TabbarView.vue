<script setup lang="ts">
import { showError } from '../feedback'
import { onMounted, ref } from 'vue'
import {
  NButton,
  NCard,
  NColorPicker,
  NFormItem,
  NInput,
  NSpace,
  useMessage,
} from 'naive-ui'
import { api, type TabbarConfig, type TabItem } from '../api'

const message = useMessage()
const loading = ref(false)
const barBg = ref('#ffffff')
const items = ref<TabItem[]>([])

const slotName: Record<TabItem['slot'], string> = {
  home: '首頁',
  category: '分類',
  cart: '購物車',
  account: '我的',
}

onMounted(async () => {
  const data = await api<TabbarConfig>('/api/v1/admin/tabbar', {}, true)
  barBg.value = data.bar_bg
  items.value = [...data.items].sort((a, b) => a.sort - b.sort)
})

async function save() {
  loading.value = true
  try {
    const data = await api<TabbarConfig>('/api/v1/admin/tabbar', {
      method: 'PUT',
      body: JSON.stringify({ bar_bg: barBg.value, items: items.value }),
    }, true)
    barBg.value = data.bar_bg
    items.value = [...data.items].sort((a, b) => a.sort - b.sort)
    message.success('底部導航已保存')
  } catch (error) {
    showError(error instanceof Error ? error.message : '保存失敗')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <n-space vertical :size="16">
    <n-card title="底欄背景">
      <n-form-item label="整條底欄的背景色">
        <n-color-picker v-model:value="barBg" :show-alpha="false" :modes="['hex']" />
      </n-form-item>
    </n-card>

    <n-card v-for="item in items" :key="item.slot" :title="slotName[item.slot]">
      <div class="grid">
        <n-form-item label="繁體文字">
          <n-input v-model:value="item.label_zh" maxlength="16" />
        </n-form-item>
        <n-form-item label="英文文字">
          <n-input v-model:value="item.label_en" maxlength="16" />
        </n-form-item>
        <n-form-item label="未選中圖標">
          <n-input v-model:value="item.icon" placeholder="home-o 或圖片地址" />
        </n-form-item>
        <n-form-item label="選中圖標">
          <n-input v-model:value="item.icon_active" placeholder="home-o 或圖片地址" />
        </n-form-item>
        <n-form-item label="未選中圖標底">
          <n-color-picker v-model:value="item.icon_bg" :show-alpha="false" :modes="['hex']" />
        </n-form-item>
        <n-form-item label="選中圖標底">
          <n-color-picker v-model:value="item.icon_bg_active" :show-alpha="false" :modes="['hex']" />
        </n-form-item>
        <n-form-item label="未選中文字">
          <n-color-picker v-model:value="item.text_color" :show-alpha="false" :modes="['hex']" />
        </n-form-item>
        <n-form-item label="選中文字">
          <n-color-picker v-model:value="item.text_color_active" :show-alpha="false" :modes="['hex']" />
        </n-form-item>
      </div>
    </n-card>

    <n-button type="primary" :loading="loading" @click="save">保存</n-button>
  </n-space>
</template>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
  gap: 0.5rem 1rem;
}
</style>
