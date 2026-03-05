<template>
  <aside
    class="relative hidden overflow-hidden border-r px-14 py-16 lg:flex"
    :class="isClient
      ? 'border-[#26443a] bg-[linear-gradient(145deg,#0b1210_0%,#10201a_45%,#0f1a17_100%)]'
      : 'border-dayz-border-muted bg-gradient-to-br from-[#0b1210] via-[#101a17] to-[#0c1512]'"
  >
    <div
      class="absolute inset-0"
      :class="isClient
        ? 'bg-[radial-gradient(circle_at_22%_18%,rgba(198,167,94,0.13),transparent_36%),radial-gradient(circle_at_78%_72%,rgba(16,64,49,0.4),transparent_55%)]'
        : 'bg-[radial-gradient(circle_at_30%_20%,rgba(198,167,94,0.12),transparent_45%),radial-gradient(circle_at_80%_70%,rgba(15,61,46,0.35),transparent_55%)]'"
    ></div>
    <div class="absolute inset-0 opacity-25">
      <div class="absolute left-10 top-20 h-[420px] w-[420px] rounded-full border border-dayz-gold/20"></div>
      <div class="absolute bottom-16 right-10 h-[380px] w-[380px] rounded-full border border-dayz-gold/15"></div>
      <div class="absolute inset-y-0 right-[22%] w-px bg-dayz-gold/10"></div>
    </div>
    <div v-if="isClient" class="absolute inset-0 bg-[linear-gradient(180deg,rgba(12,16,15,0.2)_0%,rgba(8,11,10,0.65)_75%)]"></div>

    <div class="relative z-10 my-auto max-w-xl">
      <div class="mb-7 inline-flex items-center justify-center border border-dayz-gold/40 bg-[#0f1916]/85 px-5 py-4">
        <img src="/images/DayzLogo.svg" alt="Dayz Logo" class="h-24 w-auto object-contain opacity-95" />
      </div>
      <p class="text-5xl font-semibold tracking-[0.2em] text-dayz-gold">DAYZ</p>
      <template v-if="isClient">
        <p class="mt-5 text-xl font-semibold uppercase tracking-[0.12em] text-slate-100">
          DAYZ PRIVATE CLIENT
        </p>
        <p class="mt-1 text-sm font-medium uppercase tracking-[0.18em] text-dayz-gold/90">
          Curated Property Investments
        </p>
        <div class="mt-5 h-px w-24 bg-dayz-gold/70"></div>
        <p class="mt-6 max-w-lg text-sm leading-7 text-slate-300/90">
          Access premium real estate opportunities tailored to your portfolio.
        </p>
      </template>
      <template v-else>
        <p class="mt-5 text-xl font-semibold uppercase tracking-[0.12em] text-slate-200">
          Private Real Estate Network
        </p>
        <p class="mt-6 max-w-lg text-sm leading-7 text-slate-300/90">
          Exclusive property transactions for serious investors.
        </p>
      </template>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  role: {
    type: String,
    default: 'user'
  },
  page: {
    type: String,
    default: 'auth'
  }
})

const isClient = computed(() => props.role === 'user')
</script>
