<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SubPageNav from '../components/SubPageNav.vue'
import { api } from '../api/client'

type Row = {
  id: number
  delta: number
  balance_after: number
  type: string
  remark: string
  created_at: string
}

const { t } = useI18n()
const items = ref<Row[]>([])

onMounted(async () => {
  const data = await api<{ items: Row[] }>('/api/v1/point-logs', {}, 'member')
  items.value = data.items
})
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('m6.pointLogs')" />
    <p v-if="!items.length" class="empty">{{ t('m6.noLogs') }}</p>
    <van-cell-group v-else inset>
      <van-cell
        v-for="row in items"
        :key="row.id"
        :title="row.remark || row.type"
        :label="row.created_at"
        :value="(row.delta > 0 ? '+' : '') + row.delta"
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
