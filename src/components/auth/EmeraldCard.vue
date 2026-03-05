<template>
  <div
    class="auth-fade-in w-full"
    :class="variant === 'client'
      ? 'bg-[rgba(18,31,27,0.92)] border border-[rgba(198,167,94,0.56)] shadow-[0_20px_56px_rgba(10,32,26,0.5)] backdrop-blur-[12px] max-w-[520px] p-9 sm:p-11'
      : 'dayz-auth-card max-w-[460px] p-8 sm:p-10'"
  >
    <slot />
  </div>
</template>

<script setup>
defineProps({
  variant: {
    type: String,
    default: 'default'
  }
})
</script>
