<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="inline-flex h-12 w-full items-center justify-center gap-2 px-5 text-xs font-semibold uppercase tracking-[0.2em] transition-all duration-300 disabled:cursor-not-allowed disabled:opacity-60"
    :class="variant === 'client'
      ? 'border border-dayz-gold bg-[#134233] text-dayz-gold hover:bg-dayz-gold hover:text-[#0f211b]'
      : 'border border-dayz-gold bg-dayz-emerald text-dayz-gold hover:-translate-y-px hover:bg-dayz-gold hover:text-dayz-deep'"
  >
    <svg v-if="showLock" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8">
      <path d="M7 11V8a5 5 0 0 1 10 0v3M6 11h12v9H6z" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <svg v-if="loading" viewBox="0 0 24 24" class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2">
      <path d="M12 3a9 9 0 1 0 9 9" stroke-linecap="round" />
    </svg>
    <span>{{ loading ? loadingText : text }}</span>
  </button>
</template>

<script setup>
defineProps({
  text: { type: String, required: true },
  loadingText: { type: String, default: 'Processing...' },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  showLock: { type: Boolean, default: false },
  type: { type: String, default: 'submit' },
  variant: { type: String, default: 'default' }
})
</script>
