<template>
  <header class="w-full border-b border-emerald-900/30 bg-emerald-950 text-white">
    <div class="layout-content-container px-6 py-4 flex items-center justify-between">
      <RouterLink to="/" class="flex items-center gap-3">
        <img
          src="/images/DayzLogo.svg"
          alt="Dayz"
          class="h-20 md:h-24 w-auto object-contain bg-black/60 p-2 rounded-sm ring-1 ring-white/10"
        />
      </RouterLink>
      <nav class="hidden md:flex items-center gap-6">
        <template v-if="showHomeIcons">
          <RouterLink
            :to="homeTarget"
            class="text-white/70 text-sm font-semibold hover:text-white transition-colors uppercase tracking-wider flex items-center gap-2"
          >
            <span class="h-6 w-6 border border-white/30 rounded-sm flex items-center justify-center">
              <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-white/70">
                <path fill="currentColor" d="M12 4.2 3.5 11h1.8v8.5h5.2v-5.3h2.9v5.3h5.2V11h1.9z"/>
              </svg>
            </span>
            Home
          </RouterLink>
          <RouterLink
            :to="searchTarget"
            class="text-white/70 text-sm font-semibold hover:text-white transition-colors uppercase tracking-wider flex items-center gap-2"
          >
            <span class="h-6 w-6 border border-white/30 rounded-sm flex items-center justify-center">
              <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-white/70">
                <path fill="currentColor" d="M10.5 4a6.5 6.5 0 1 0 3.9 11.7l4.1 4.1 1.1-1.1-4.1-4.1A6.5 6.5 0 0 0 10.5 4zm0 1.6a4.9 4.9 0 1 1 0 9.8 4.9 4.9 0 0 1 0-9.8z"/>
              </svg>
            </span>
            Search
          </RouterLink>
          <RouterLink
            :to="insightsTarget"
            class="text-white/70 text-sm font-semibold hover:text-white transition-colors uppercase tracking-wider flex items-center gap-2"
          >
            <span class="h-6 w-6 border border-white/30 rounded-sm flex items-center justify-center">
              <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-white/70">
                <path fill="currentColor" d="M6 4h12a2 2 0 0 1 2 2v12l-4-3H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm0 2v7h10.5l1.5 1.1V6z"/>
              </svg>
            </span>
            {{ insightsLabel }}
          </RouterLink>
          <RouterLink
            :to="settingsTarget"
            class="text-white/70 text-sm font-semibold hover:text-white transition-colors uppercase tracking-wider flex items-center gap-2"
          >
            <span class="h-6 w-6 border border-white/30 rounded-sm flex items-center justify-center">
              <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-white/70">
                <path fill="currentColor" d="M12 8.6a3.4 3.4 0 1 0 0 6.8 3.4 3.4 0 0 0 0-6.8zm8.2 3.4-.9-.5.1-1.1-.9-.9-1.1.1-.5-.9-1.2.1-.6-1-1.1.4-.8-.8-.9.7-1-.4-.4 1.1-1 .3-.3 1.1-1.1.3-.1 1.2-.9.5.1 1.1-.9.9 1.1.5-.1 1.2.9.9 1.1-.1.5.9 1.2-.1.6 1 1.1-.4.8.8.9-.7 1 .4.4-1.1 1-.3.3-1.1 1.1-.3.1-1.2.9-.5-.1-1.1.9-.9-1.1-.5z"/>
              </svg>
            </span>
            Settings
          </RouterLink>
        </template>
        <button
          v-else
          type="button"
          class="text-white/70 text-sm font-semibold hover:text-white transition-colors uppercase tracking-wider flex items-center gap-2"
          @click="goBack"
        >
          <span class="h-6 w-6 border border-white/30 rounded-sm flex items-center justify-center">
            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-white/70">
              <path fill="currentColor" d="M12.7 4.3 6 11l6.7 6.7 1.3-1.3-4.4-4.4h7.4V10h-7.4l4.4-4.4z"/>
            </svg>
          </span>
          Back
        </button>
      </nav>
      <div v-if="!isLoggedIn" class="flex items-center gap-3">
        <RouterLink to="/login" class="hidden md:inline-flex items-center justify-center rounded border border-white/30 text-white text-[11px] font-bold uppercase tracking-widest h-9 px-5 hover:bg-white/10 transition-all">
          Member Access
        </RouterLink>
        <RouterLink to="/register?role=user" class="flex min-w-[120px] items-center justify-center rounded bg-white/10 text-white text-[11px] font-bold uppercase tracking-widest h-9 px-5 hover:bg-white/20 transition-all shadow-md">
          Sign Up
        </RouterLink>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

const isLoggedIn = ref(false)
const route = useRoute()
const router = useRouter()

onMounted(() => {
  isLoggedIn.value = Boolean(localStorage.getItem('AUTH_TOKEN'))
})

const homeTarget = computed(() => {
  if (isLoggedIn.value) {
    const role = localStorage.getItem('USER_ROLE')
    return role === 'agent' ? '/dashboard/agent' : '/dashboard/user'
  }
  return '/'
})

const searchTarget = computed(() => {
  const role = localStorage.getItem('USER_ROLE')
  if (role === 'agent') return '/marketplace/agent'
  return route.path === '/blog' ? `${homeTarget.value}?search=1` : '/?search=1'
})

const settingsTarget = computed(() => {
  const role = localStorage.getItem('USER_ROLE')
  return role === 'agent' ? '/settings/agent' : '/settings/user'
})

const insightsTarget = computed(() => {
  const role = localStorage.getItem('USER_ROLE')
  return role === 'agent' ? '/intel' : '/blog'
})

const insightsLabel = computed(() => {
  const role = localStorage.getItem('USER_ROLE')
  return role === 'agent' ? 'Market Intel' : 'Insights'
})

const showHomeIcons = computed(() => {
  if (route.path === '/dashboard/user') return true
  if (route.path === '/dashboard/agent') return true
  if (route.path === '/marketplace/agent') return true
  return false
})

function goBack() {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push(homeTarget.value)
  }
}
</script>
