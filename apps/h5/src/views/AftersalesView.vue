<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchAftersales, type Aftersale } from '../api/trade'

const { t } = useI18n()
const router = useRouter()
const items = ref<Aftersale[]>([])

onMounted(async () => {
  items.value = (await fetchAftersales()).items
})
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('fulfill.list')" />
    <p v-if="!items.length" class="empty">{{ t('fulfill.empty') }}</p>
    <van-cell-group v-else inset>
      <van-cell
        v-for="row in items"
        :key="row.id"
        :title="row.aftersale_no"
        :value="row.status"
        :label="`HK$ ${row.amount.toFixed(2)}`"
        is-link
        @click="router.push({ name: 'aftersale-detail', params: { id: row.id } })"
      />
    </van-cell-group>
  </div>
</template>

<style scoped>
.empty {
  text-align: center;
  color: #999;
  padding: 2rem;
}
</style>
