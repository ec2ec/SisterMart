<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchShipment, type Shipment } from '../api/trade'

const { t } = useI18n()
const route = useRoute()
const data = ref<Shipment | null>(null)

onMounted(async () => {
  data.value = await fetchShipment(String(route.params.id))
})
</script>

<template>
  <div class="page" v-if="data">
    <SubPageNav :title="t('fulfill.logistics')" />
    <section class="card">
      <p>{{ data.company }}</p>
      <strong>{{ data.tracking_no }}</strong>
    </section>
    <section class="card">
      <div v-for="n in data.nodes" :key="n.id" class="node">
        <time>{{ n.occurred_at }}</time>
        <p>{{ n.note }}</p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.card {
  margin: 0.75rem;
  padding: 0.875rem;
  background: #fff;
  border-radius: 0.75rem;
}
.node {
  padding: 0.625rem 0;
  border-bottom: 0.0625rem solid #f0f0f0;
}
.node time {
  font-size: 0.75rem;
  color: #999;
}
.node p {
  margin: 0.25rem 0 0;
  font-size: 0.875rem;
}
</style>
