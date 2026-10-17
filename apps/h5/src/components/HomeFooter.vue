<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const left = ['about', 'contact', 'service', 'whatsNew', 'terms'] as const
const right = ['privacy', 'payment', 'shipping', 'refund', 'return'] as const
</script>

<template>
  <footer class="footer">
    <div class="cols">
      <div>
        <a v-for="k in left" :key="k" href="#">{{ t(`footer.${k}`) }}</a>
      </div>
      <div>
        <a v-for="k in right" :key="k" href="#">{{ t(`footer.${k}`) }}</a>
      </div>
    </div>
    <p class="slogan">{{ t('home.slogan') }}</p>
  </footer>
</template>

<style scoped>
.footer {
  padding: 1.25rem 1.5rem 1.75rem;
  background: #fff;
}

.cols {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem 1.5rem;
}

.cols a {
  display: block;
  padding: 0.375rem 0;
  font-size: 0.8125rem;
  color: #666;
}

.slogan {
  margin: 1.125rem 0 0;
  text-align: center;
  font-size: 0.75rem;
  color: #aaa;
}
</style>
