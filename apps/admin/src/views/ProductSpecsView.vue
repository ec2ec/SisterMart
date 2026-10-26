<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

/** 規格改在商品編輯同頁保存；此路由僅作舊鏈接跳轉 */
const route = useRoute()
const router = useRouter()

onMounted(() => {
  const id = String(route.params.id || '')
  router.replace(id ? `/catalog/products/${id}` : '/catalog/products')
})
</script>

<template>
  <p style="padding: 1rem; color: #6d675f">正在跳轉到商品編輯…</p>
</template>
