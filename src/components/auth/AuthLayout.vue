<template>
  <div class="min-h-screen bg-dayz-deep text-dayz-text-soft">
    <div class="grid min-h-screen lg:grid-cols-[1.05fr,1fr]">
      <BrandPanel :role="role" :page="page" />
      <section class="relative flex items-center justify-center px-4 py-10 sm:px-8">
        <slot />
      </section>
    </div>
  </div>
</template>

<script setup>
import BrandPanel from '@/components/auth/BrandPanel.vue'

defineProps({
  role: {
    type: String,
    default: 'user'
  },
  page: {
    type: String,
    default: 'auth'
  }
})
</script>
