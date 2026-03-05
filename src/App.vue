<template>
  <div class="min-h-screen flex flex-col">
    <AppHeader v-if="showChrome" />
    <main :class="showChrome ? 'flex-1' : 'min-h-screen'">
      <RouterView />
    </main>
    <AppFooter v-if="showChrome" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import AppHeader from '@/components/layout/Header.vue'
import AppFooter from '@/components/layout/Footer.vue'

const route = useRoute()

const authPaths = new Set([
  '/login',
  '/login/client',
  '/login/agent',
  '/register',
  '/register/client',
  '/register/agent',
  '/verify-account',
  '/forgot-password',
  '/reset-password'
])

const showChrome = computed(() => !authPaths.has(route.path))
</script>
