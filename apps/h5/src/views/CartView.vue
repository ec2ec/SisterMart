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
            <van-stepper v-model="item.qty" min="1" theme="round" button-size="1.375rem" />
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
  padding: 0.625rem 0.5rem;
  font-size: 0.6875rem;
  color: #999;
  background: #fafafa;
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
}

.toolbar h1 {
  margin: 0;
  font-size: 1rem;
}

.manage {
  border: 0.0625rem solid #ddd;
  background: #fff;
  border-radius: 62.4375rem;
  padding: 0.25rem 0.875rem;
  font-size: 0.75rem;
  color: #333;
}

.list {
  padding: 0 0.75rem 5.625rem;
}

.row {
  display: flex;
  gap: 0.625rem;
  align-items: center;
  padding: 0.75rem 0;
  border-bottom: 0.0625rem solid #f2f2f2;
}

.row img {
  width: 5.5rem;
  height: 5.5rem;
  border-radius: 0.625rem;
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
  font-size: 0.875rem;
}

.info p {
  margin: 0.375rem 0 0.625rem;
  font-size: 0.75rem;
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
  bottom: calc(var(--sm-tabbar-h) + env(safe-area-inset-bottom, 0rem));
  z-index: 15;
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 0.75rem;
  background: #fff;
  border-top: 0.0625rem solid #eee;
}

.sum {
  flex: 1;
  text-align: right;
  font-size: 0.8125rem;
}

.sum strong {
  margin-left: 0.25rem;
  font-size: 1rem;
}

.submit :deep(.van-button) {
  min-width: 6rem;
  height: 2.25rem;
}
</style>
