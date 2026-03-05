<template>
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div class="flex items-start gap-4">
      <button
        v-if="showBack"
        type="button"
        class="inline-flex items-center gap-2 border border-slate-300/30 bg-slate-800/60 px-4 py-2 text-xs uppercase tracking-widest text-slate-100 hover:bg-slate-700/70"
        @click="$emit('back')"
      >
        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor" aria-hidden="true">
          <path d="M14.7 5.3 8 12l6.7 6.7 1.4-1.4-5.3-5.3 5.3-5.3z" />
        </svg>
        Back
      </button>
      <div>
        <p class="text-xs uppercase tracking-[0.3em] text-amber-200/75">{{ eyebrow }}</p>
        <h1 class="mt-2 font-display text-3xl text-slate-100">{{ title }}</h1>
        <p v-if="subtitle" class="mt-2 text-sm text-slate-300/80">{{ subtitle }}</p>
      </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  eyebrow: {
    type: String,
    default: 'Property Intel'
  },
  title: {
    type: String,
    default: ''
  },
  subtitle: {
    type: String,
    default: ''
  },
  showBack: {
    type: Boolean,
    default: true
  }
})

defineEmits(['back'])
</script>
