<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppHeader from '../components/AppHeader.vue'
import { categories } from '../mock/data'

const { locale } = useI18n()
const active = ref(1)

const current = computed(() => categories[active.value] || categories[0])

const sections = computed(() => {
  const main = current.value
  const related = categories.find((c) => c.id === 'bracelets' && main.id === 'necklaces')
  if (main.id === 'necklaces' && related) {
    return [
      { title: main.name, items: main.children || [] },
      { title: related.name, items: related.children || [] },
    ]
  }
  return [{ title: main.name, items: main.children || [] }]
})
</script>

<template>
  <div class="page page--soft category-page">
    <AppHeader />
    <div class="body">
      <van-sidebar v-model="active" class="side">
        <van-sidebar-item
          v-for="c in categories"
          :key="c.id"
          :title="c.name[locale as 'zh-HK' | 'en']"
        />
      </van-sidebar>
      <div class="content">
        <section v-for="(sec, idx) in sections" :key="idx" class="sec">
          <div class="sec-title">
            <span />
            <em>{{ sec.title[locale as 'zh-HK' | 'en'] }}</em>
            <span />
          </div>
          <div class="grid">
            <button v-for="item in sec.items" :key="item.id" type="button" class="item">
              <img :src="item.image" :alt="item.name[locale as 'zh-HK' | 'en']" />
              <span>{{ item.name[locale as 'zh-HK' | 'en'] }}</span>
            </button>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.category-page {
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
  padding-bottom: calc(var(--sm-tabbar-h) + env(safe-area-inset-bottom, 0px));
}

.body {
  flex: 1;
  display: flex;
  min-height: 0;
  background: #fff;
}

.side {
  width: 88px;
  flex: 0 0 88px;
  background: var(--sm-bg-soft);
  overflow-y: auto;
}

.side :deep(.van-sidebar-item--select) {
  color: var(--sm-primary);
  font-weight: 600;
}

.content {
  flex: 1;
  overflow-y: auto;
  padding: 8px 10px 20px;
  background: #fff;
}

.sec + .sec {
  margin-top: 10px;
}

.sec-title {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  gap: 10px;
  margin: 10px 0 14px;
  color: #333;
  font-size: 13px;
}

.sec-title em {
  font-style: normal;
  font-weight: 600;
}

.sec-title span {
  height: 1px;
  background: #e8e8e8;
}

.grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px 8px;
}

.item {
  border: 0;
  background: transparent;
  padding: 0;
  text-align: center;
}

.item img {
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
  border-radius: 10px;
  background: #f5f5f5;
  display: block;
}

.item span {
  display: block;
  margin-top: 6px;
  font-size: 11px;
  color: #333;
  line-height: 1.3;
}
</style>
