<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { showToast } from 'vant'
import { initialCart, type CartItem } from '../mock/data'

const { t, locale } = useI18n()
const items = ref<CartItem[]>(initialCart.map((i) => ({ ...i })))
const managing = ref(false)

const checkedCount = computed(() => items.value.filter((i) => i.checked).length)
const allChecked = computed({
  get: () => items.value.length > 0 && items.value.every((i) => i.checked),
  set: (v: boolean) => {
    items.value.forEach((i) => {
      i.checked = v
    })
  },
})
const total = computed(() =>
  items.value.filter((i) => i.checked).reduce((sum, i) => sum + i.price * i.qty, 0),
)

function checkout() {
  showToast(`${t('cart.checkout')} HK$ ${total.value.toFixed(2)}`)
}

function removeChecked() {
  items.value = items.value.filter((i) => !i.checked)
  managing.value = false
}
</script>

<template>
  <div class="page">
    <div class="trust">
      <span>{{ t('cart.trust1') }}</span>
      <span>{{ t('cart.trust2') }}</span>
      <span>{{ t('cart.trust3') }}</span>
    </div>

    <div class="toolbar">
      <h1>{{ t('cart.items', { n: items.length }) }}</h1>
      <button type="button" class="manage" @click="managing = !managing">
        {{ managing ? t('cart.done') : t('cart.manage') }}
      </button>
    </div>

    <div class="list">
      <div v-for="item in items" :key="item.id" class="row">
        <van-checkbox v-model="item.checked" checked-color="var(--sm-primary)" />
        <img :src="item.image" alt="" />
        <div class="info">
          <h3>{{ item.name[locale as 'zh-HK' | 'en'] }}</h3>
          <p>{{ item.spec[locale as 'zh-HK' | 'en'] }}</p>
          <div class="bottom">
            <span class="price">HK$ {{ item.price.toFixed(2) }}</span>
            <van-stepper v-model="item.qty" min="1" theme="round" button-size="22" />
          </div>
        </div>
      </div>
    </div>

    <div class="submit">
      <van-checkbox v-model="allChecked" checked-color="var(--sm-primary)">
        {{ t('cart.selectAll', { n: checkedCount }) }}
      </van-checkbox>
      <div class="sum">
        <template v-if="!managing">
          <span>{{ t('cart.total') }}</span>
          <strong class="price">HK$ {{ total.toFixed(2) }}</strong>
        </template>
      </div>
      <van-button
        type="primary"
        round
        color="var(--sm-primary)"
        :disabled="checkedCount === 0"
        @click="managing ? removeChecked() : checkout()"
      >
        {{ managing ? t('cart.delete') : t('cart.checkout') }}
      </van-button>
    </div>
  </div>
</template>

<style scoped>
.trust {
  display: flex;
  justify-content: space-around;
  padding: 10px 8px;
  font-size: 11px;
  color: #999;
  background: #fafafa;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
}

.toolbar h1 {
  margin: 0;
  font-size: 16px;
}

.manage {
  border: 1px solid #ddd;
  background: #fff;
  border-radius: 999px;
  padding: 4px 14px;
  font-size: 12px;
  color: #333;
}

.list {
  padding: 0 12px 90px;
}

.row {
  display: flex;
  gap: 10px;
  align-items: center;
  padding: 12px 0;
  border-bottom: 1px solid #f2f2f2;
}

.row img {
  width: 88px;
  height: 88px;
  border-radius: 10px;
  object-fit: cover;
  background: #f5f5f5;
  flex: 0 0 auto;
}

.info {
  flex: 1;
  min-width: 0;
}

.info h3 {
  margin: 0;
  font-size: 14px;
}

.info p {
  margin: 6px 0 10px;
  font-size: 12px;
  color: #999;
}

.bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.price {
  color: var(--sm-primary);
  font-weight: 700;
}

.submit {
  position: fixed;
  left: 0;
  right: 0;
  bottom: calc(var(--sm-tabbar-h) + env(safe-area-inset-bottom, 0px));
  z-index: 15;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  background: #fff;
  border-top: 1px solid #eee;
}

.sum {
  flex: 1;
  text-align: right;
  font-size: 13px;
}

.sum strong {
  margin-left: 4px;
  font-size: 16px;
}

.submit :deep(.van-button) {
  min-width: 96px;
  height: 36px;
}
</style>
