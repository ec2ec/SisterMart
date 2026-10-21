<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { showToast } from 'vant'
import SubPageNav from '../components/SubPageNav.vue'
import { fetchCheckinCalendar, postCheckin } from '../api/m7'
import { ApiError } from '../api/client'
import { memberState } from '../composables/useSession'

const { t } = useI18n()
const cal = ref<Awaited<ReturnType<typeof fetchCheckinCalendar>> | null>(null)

async function load() {
  cal.value = await fetchCheckinCalendar()
}

onMounted(load)

async function sign() {
  try {
    const res = await postCheckin()
    showToast(t('m7.checkinOk', { n: res.reward }))
    if (memberState.value) memberState.value = { ...memberState.value, points: res.points }
    await load()
  } catch (e) {
    showToast(e instanceof ApiError ? e.message : t('m7.checkinFail'))
  }
}
</script>

<template>
  <div class="page">
    <SubPageNav :title="t('account.dailyCheckin')" />
    <section class="hero" v-if="cal">
      <p>{{ t('account.checkinSub', { n: cal.streak }) }}</p>
      <strong>+{{ cal.reward_today }}</strong>
      <van-button
        round
        class="sign-btn"
        :class="{ 'is-signed': cal.signed_today }"
        :plain="cal.signed_today"
        :color="cal.signed_today ? '#9aa0a6' : '#f0c9a0'"
        :disabled="cal.signed_today"
        @click="sign"
      >
        {{ cal.signed_today ? t('m7.signed') : t('account.checkinBtn') }}
      </van-button>
    </section>
    <div class="cal" v-if="cal">
      <h2>{{ cal.year }}-{{ String(cal.month).padStart(2, '0') }}</h2>
      <div class="days">
        <span
          v-for="d in cal.days"
          :key="d.day"
          :class="{ signed: d.signed, today: d.today }"
        >
          {{ d.day }}
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.hero {
  margin: 1rem;
  padding: 1.25rem;
  border-radius: 1rem;
  background: linear-gradient(160deg, #2a2118, #5a4634);
  color: #f7efe4;
  text-align: center;
}
.hero strong {
  display: block;
  font-size: 2rem;
  margin: 0.5rem 0 1rem;
}
.sign-btn {
  min-width: 8.5rem;
  font-weight: 600;
}
.sign-btn:not(.is-signed) {
  color: #2a2118 !important;
}
.sign-btn.is-signed {
  opacity: 0.9;
}
.cal {
  padding: 0 1rem 2rem;
}
.cal h2 {
  font-size: 0.9375rem;
}
.days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 0.35rem;
}
.days span {
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.35rem;
  background: #f5f5f5;
  font-size: 0.75rem;
}
.days .signed {
  background: #5b8c5a;
  color: #fff;
}
.days .today:not(.signed) {
  background: #ffe8dc;
  color: #c45c26;
  font-weight: 700;
}
.days .today.signed {
  outline: 2px solid #3f6b3f;
}
</style>
