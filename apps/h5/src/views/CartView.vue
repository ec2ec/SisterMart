<script setup lang="ts">
import { computed, onActivated, onDeactivated, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { ApiError } from '../api/client'
import {
  checkAllCart,
  deleteCartItems,
  fetchCart,
  fetchCartTrustTags,
  patchCartItem,
  type CartItem,
  type CartTrustTag,
} from '../api/trade'
import { refreshCartBadge } from '../composables/useCartBadge'
import { restoreRouteScroll, saveRouteScroll } from '../composables/useRouteScroll'
import { isLoggedIn, loggedIn, syncSessionFromStorage } from '../composables/useSession'
import { useLocaleText } from '../composables/useLocaleText'

defineOptions({ name: 'CartView' })

const { t } = useI18n()
const { text } = useLocaleText()
const router = useRouter()
const items = ref<CartItem[]>([])
const trustTags = ref<CartTrustTag[]>([])
const activeTrust = ref<CartTrustTag | null>(null)
const showTrustDetail = ref(false)
const managing = ref(false)
const loading = ref(false)
const SCROLL_KEY = 'cart'

/** API 失敗時回退本地文案（與種子一致） */
const fallbackTrustTags = computed<CartTrustTag[]>(() => [
  {
    id: 1,
    code: 'authentic',
    sort: 1,
    name: { 'zh-HK': t('cart.trust1'), en: t('cart.trust1') },
    detail: { 'zh-HK': t('cart.trust1Detail'), en: t('cart.trust1Detail') },
  },
  {
    id: 2,
    code: 'craft',
    sort: 2,
    name: { 'zh-HK': t('cart.trust2'), en: t('cart.trust2') },
    detail: { 'zh-HK': t('cart.trust2Detail'), en: t('cart.trust2Detail') },
  },
  {
    id: 3,
    code: 'aftersale',
    sort: 3,
    name: { 'zh-HK': t('cart.trust3'), en: t('cart.trust3') },
    detail: { 'zh-HK': t('cart.trust3Detail'), en: t('cart.trust3Detail') },
  },
])

const displayTrustTags = computed(() => (trustTags.value.length ? trustTags.value : fallbackTrustTags.value))

async function loadTrustTags() {
  try {
    const data = await fetchCartTrustTags()
    trustTags.value = data.items || []
  } catch {
    trustTags.value = []
  }
}

function openTrust(tag: CartTrustTag) {
  activeTrust.value = tag
  showTrustDetail.value = true
}

async function load() {
  syncSessionFromStorage()
  if (!isLoggedIn()) {
    items.value = []
    void refreshCartBadge()
    return
  }
  loading.value = true
  try {
    const data = await fetchCart()
    items.value = data.items
    void refreshCartBadge()
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      router.push({ name: 'login', query: { redirect: '/cart' } })
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
  void loadTrustTags()
})
onDeactivated(() => saveRouteScroll(SCROLL_KEY))
let cartFirstActivate = true
onActivated(() => {
  // keep-alive 首次會同時觸發 mounted+activated，跳過避免連打兩次
  if (cartFirstActivate) {
    cartFirstActivate = false
    return
  }
  // 購物車可能在其他頁被改過，回來刷新；再還原滾動
  void load().then(() => restoreRouteScroll(SCROLL_KEY))
})

const checkedCount = computed(() => items.value.filter((i) => i.checked && i.available).length)
const allChecked = computed({
  get: () => {
    const usable = items.value.filter((i) => i.available)
    return usable.length > 0 && usable.every((i) => i.checked)
  },
  set: async (v: boolean) => {
    if (!isLoggedIn()) return
    await checkAllCart(v)
    await load()
  },
})
const total = computed(() =>
  items.value.filter((i) => i.checked && i.available).reduce((sum, i) => sum + i.price * i.qty, 0),
)

async function onQty(item: CartItem, qty: number) {
  await patchCartItem(item.id, { qty })
  await load()
}

async function onCheck(item: CartItem, checked: boolean) {
  if (!item.available) return
  await patchCartItem(item.id, { checked })
  await load()
}

function checkout() {
  if (!isLoggedIn()) {
    router.push({ name: 'login', query: { redirect: '/cart' } })
    return
  }
  if (checkedCount.value === 0) {
    showToast(t('cart.checkout'))
    return
  }
  router.push({ name: 'checkout' })
}

async function removeChecked() {
  const ids = items.value.filter((i) => i.checked).map((i) => i.id)
  if (!ids.length) return
  await deleteCartItems(ids)
  managing.value = false
  await load()
}

function reasonText(item: CartItem) {
  if (item.invalid_reason === 'off_sale') return t('cart.invalidOff')
  if (item.invalid_reason === 'out_of_stock') return t('cart.invalidStock')
  if (item.invalid_reason === 'qty_exceed') return t('cart.invalidQty')
  return ''
}
</script>

<template>
  <div class="page">
    <div v-if="displayTrustTags.length" class="trust">
      <button
        v-for="tag in displayTrustTags"
        :key="tag.id"
        type="button"
        class="trust-tag"
        @click="openTrust(tag)"
      >
        {{ text(tag.name) }}
      </button>
    </div>

    <van-popup v-model:show="showTrustDetail" round position="bottom" :style="{ maxHeight: '70%' }">
      <div v-if="activeTrust" class="trust-sheet">
        <h3>{{ text(activeTrust.name) }}</h3>
        <p>{{ text(activeTrust.detail) || t('cart.trustNoDetail') }}</p>
        <van-button block round color="var(--sm-primary)" @click="showTrustDetail = false">
          {{ t('pages.back') }}
        </van-button>
      </div>
    </van-popup>

    <div class="toolbar">
      <h1>{{ t('cart.items', { n: items.length }) }}</h1>
      <button type="button" class="manage" @click="managing = !managing">
        {{ managing ? t('cart.done') : t('cart.manage') }}
      </button>
    </div>

    <p v-if="!loggedIn" class="empty">
      <button type="button" @click="router.push({ name: 'login', query: { redirect: '/cart' } })">{{ t('auth.login') }}</button>
    </p>
    <p v-else-if="!items.length && !loading" class="empty">{{ t('cart.empty') }}</p>

    <div v-else class="list">
      <div v-for="item in items" :key="item.id" class="row" :class="{ bad: !item.available }">
        <van-checkbox
          :model-value="item.checked"
          :disabled="!item.available"
          checked-color="var(--sm-primary)"
          @update:model-value="(v: boolean) => onCheck(item, v)"
        />
        <img :src="item.image" alt="" />
        <div class="info">
          <h3>{{ text(item.name) }}</h3>
          <p>{{ text(item.spec) }}</p>
          <p v-if="!item.available" class="warn">{{ reasonText(item) }}</p>
          <div class="bottom">
            <span class="price">HK$ {{ item.price.toFixed(2) }}</span>
            <van-stepper
              :model-value="item.qty"
              :max="Math.max(1, item.stock)"
              min="1"
              theme="round"
              button-size="1.375rem"
              @change="(v: number) => onQty(item, v)"
            />
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
        :disabled="managing ? checkedCount === 0 && !items.some((i) => i.checked) : checkedCount === 0"
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
  gap: 0.25rem;
  padding: 0.5rem 0.35rem;
  background: #fafafa;
}
.trust-tag {
  flex: 1;
  min-width: 0;
  border: 0;
  background: transparent;
  padding: 0.35rem 0.25rem;
  font-size: 0.6875rem;
  color: #666;
  text-align: center;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.trust-tag:active {
  color: var(--sm-primary);
}
.trust-sheet {
  padding: 1.25rem 1rem 1.5rem;
}
.trust-sheet h3 {
  margin: 0 0 0.75rem;
  font-size: 1rem;
  text-align: center;
}
.trust-sheet p {
  margin: 0 0 1.25rem;
  font-size: 0.875rem;
  line-height: 1.55;
  color: #555;
  white-space: pre-wrap;
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
}
.list {
  padding: 0 0.75rem 5.625rem;
}
.row {
  display: grid;
  grid-template-columns: auto 5rem 1fr;
  gap: 0.625rem;
  align-items: center;
  padding: 0.75rem 0;
  border-bottom: 0.0625rem solid #f0f0f0;
}
.row.bad {
  opacity: 0.7;
}
.row img {
  width: 5rem;
  height: 5rem;
  object-fit: cover;
  border-radius: 0.5rem;
  background: #f6f6f6;
}
.info h3 {
  margin: 0;
  font-size: 0.875rem;
}
.info p {
  margin: 0.25rem 0;
  font-size: 0.75rem;
  color: #888;
}
.warn {
  color: #c45c26 !important;
}
.bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
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
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: #fff;
  border-top: 0.0625rem solid #eee;
  z-index: 20;
}
.sum {
  flex: 1;
  text-align: right;
  font-size: 0.75rem;
}
.empty {
  text-align: center;
  padding: 3rem 1rem;
  color: #999;
}
.empty button {
  border: 0;
  background: var(--sm-primary);
  color: #fff;
  border-radius: 0.5rem;
  padding: 0.625rem 1.25rem;
}
</style>
