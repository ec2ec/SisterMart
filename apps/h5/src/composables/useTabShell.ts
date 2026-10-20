import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter, type RouteLocationNormalizedLoaded } from 'vue-router'

/** 與 router Tab 路由 name 對齊（避免與 router/index 循環依賴） */
export const TAB_ROUTE_NAMES = ['home', 'category', 'cart', 'account'] as const

const lastTabName = ref<(typeof TAB_ROUTE_NAMES)[number]>('home')
/** 進入疊層前記住的 Tab window.scrollY，返回時還原 */
const tabScrollY = ref(0)
let shellBound = false

export function isTabRouteName(name: unknown): name is (typeof TAB_ROUTE_NAMES)[number] {
  return typeof name === 'string' && (TAB_ROUTE_NAMES as readonly string[]).includes(name)
}

/** 由 router.beforeEach 在導航提交前呼叫，早於 scrollBehavior */
export function rememberTabScrollBeforeOverlay(
  toMetaOverlay: boolean,
  fromMetaOverlay: boolean,
  fromName: unknown,
) {
  if (toMetaOverlay && !fromMetaOverlay && isTabRouteName(fromName)) {
    tabScrollY.value = window.scrollY
    lastTabName.value = fromName
  }
}

function lockBodyScroll() {
  const y = tabScrollY.value
  document.body.style.position = 'fixed'
  document.body.style.top = `-${y}px`
  document.body.style.left = '0'
  document.body.style.right = '0'
  document.body.style.width = '100%'
  document.body.style.overflow = 'hidden'
}

function unlockBodyScroll() {
  document.body.style.position = ''
  document.body.style.top = ''
  document.body.style.left = ''
  document.body.style.right = ''
  document.body.style.width = ''
  document.body.style.overflow = ''
}

export function useTabShell() {
  const route = useRoute()
  const router = useRouter()

  if (!shellBound) {
    shellBound = true
    watch(
      () => route.name,
      (name) => {
        if (isTabRouteName(name)) lastTabName.value = name
      },
      { immediate: true },
    )

    watch(
      () => Boolean(route.meta.overlay),
      (open, wasOpen) => {
        if (open) {
          lockBodyScroll()
          return
        }
        unlockBodyScroll()
        if (wasOpen && isTabRouteName(route.name)) {
          const y = tabScrollY.value
          nextTick(() => window.scrollTo(0, y))
        }
      },
      { immediate: true },
    )
  }

  const isOverlay = computed(() => Boolean(route.meta.overlay))
  const isTab = computed(() => isTabRouteName(route.name))

  /** 底層 Tab：當前 Tab，或疊層打開時記住的上一 Tab（直達商詳則 home） */
  const backgroundTabName = computed<(typeof TAB_ROUTE_NAMES)[number] | null>(() => {
    if (isTab.value) return route.name as (typeof TAB_ROUTE_NAMES)[number]
    if (isOverlay.value) return lastTabName.value
    return null
  })

  const showTabbar = computed(() => isTab.value && !isOverlay.value)

  /** 關閉商詳疊層：有歷史則 back，否則回上一 Tab（直達深鏈） */
  function closeOverlay() {
    if (window.history.state.back != null) {
      router.back()
      return
    }
    router.replace({ name: lastTabName.value })
  }

  return {
    lastTabName,
    tabScrollY,
    isOverlay,
    isTab,
    backgroundTabName,
    showTabbar,
    closeOverlay,
  }
}

export function routeIsTab(r: RouteLocationNormalizedLoaded) {
  return isTabRouteName(r.name)
}
