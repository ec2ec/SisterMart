<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { initialCart } from '../mock/data'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const active = computed(() => String(route.name || 'home'))
const cartCount = initialCart.length

function onChange(name: string | number) {
  router.push({ name: String(name) })
}
</script>

<template>
  <van-tabbar :model-value="active" active-color="var(--sm-primary)" inactive-color="#666" @change="onChange">
    <van-tabbar-item name="home" icon="home-o">{{ t('tab.home') }}</van-tabbar-item>
    <van-tabbar-item name="category" icon="apps-o">{{ t('tab.category') }}</van-tabbar-item>
    <van-tabbar-item name="cart" icon="shopping-cart-o" :badge="cartCount || ''">{{ t('tab.cart') }}</van-tabbar-item>
    <van-tabbar-item name="account" icon="user-o">{{ t('tab.account') }}</van-tabbar-item>
  </van-tabbar>
</template>
