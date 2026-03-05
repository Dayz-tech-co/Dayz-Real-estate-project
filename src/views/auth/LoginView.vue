<template>
  <AuthLayout :role="role" page="login">
    <EmeraldCard :variant="isClient ? 'client' : 'default'">
      <div class="mb-8 text-center" :class="isClient ? 'space-y-3' : ''">
        <template v-if="isClient">
          <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-dayz-gold/85">PRIVATE ACCESS</p>
          <h1 class="font-display text-4xl font-semibold text-slate-100">Sign In to Your Portfolio</h1>
          <p class="mx-auto max-w-md text-sm leading-6 text-slate-300/85">
            Manage your saved properties and investment activity.
          </p>
        </template>
        <template v-else>
          <h1 class="text-4xl font-semibold tracking-[0.2em] text-dayz-gold">DAYZ</h1>
          <p class="mt-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-dayz-gold/85">{{ portalLabel }}</p>
        </template>
        <div class="mx-auto mt-4 h-px bg-dayz-gold/70" :class="isClient ? 'w-28' : 'w-20'"></div>
      </div>

      <form class="space-y-4" @submit.prevent="submitForm">
        <RoleToggle v-model="selectedRole" :variant="isClient ? 'client' : 'default'" />

        <div class="border px-3 py-2 text-center text-xs font-semibold uppercase tracking-[0.2em] text-dayz-gold" :class="isClient ? 'border-[#29463b] bg-[#12201c]' : 'border-dayz-border-muted bg-[#0f1916]'">
          {{ roleLabel }} Login
        </div>

        <LuxuryInput
          v-model="form.email"
          label="Email"
          icon="email"
          type="email"
          autocomplete="email"
          placeholder="you@email.com"
          :variant="isClient ? 'client' : 'default'"
          required
        />

        <LuxuryInput
          v-model="form.password"
          label="Password"
          icon="lock"
          type="password"
          autocomplete="current-password"
          placeholder="Enter password"
          :password-toggle="true"
          :variant="isClient ? 'client' : 'default'"
          required
        />

        <div class="flex items-center justify-between text-[11px] uppercase tracking-[0.12em]">
          <RouterLink :to="`/forgot-password?role=${role}`" class="auth-link">Forgot Password</RouterLink>
          <RouterLink :to="`/verify-account?role=${role}`" class="auth-link">Verify Account</RouterLink>
        </div>

        <p v-if="error" class="text-sm text-red-300">{{ error }}</p>

        <LuxuryButton
          :text="isClient ? 'ACCESS PORTFOLIO' : 'Sign In Securely'"
          loading-text="Signing In..."
          :loading="loading"
          :show-lock="true"
          :variant="isClient ? 'client' : 'default'"
        />

        <div v-if="isClient" class="space-y-2 pt-1 text-xs text-dayz-gold/85">
          <p class="flex items-center justify-center gap-2">
            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M7 11V8a5 5 0 0 1 10 0v3M6 11h12v9H6z" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Secured Property Transactions
          </p>
          <p class="flex items-center justify-center gap-2">
            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M12 3 4 7v5c0 5 3.4 8 8 9 4.6-1 8-4 8-9V7l-8-4Z" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Private Client Confidentiality
          </p>
        </div>
        <p v-else class="flex items-center justify-center gap-2 text-[11px] text-slate-400">
          <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-dayz-gold/80" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M7 11V8a5 5 0 0 1 10 0v3M6 11h12v9H6z" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          Secured with end-to-end encryption.
        </p>
      </form>
    </EmeraldCard>
  </AuthLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'
import AuthLayout from '@/components/auth/AuthLayout.vue'
import EmeraldCard from '@/components/auth/EmeraldCard.vue'
import RoleToggle from '@/components/auth/RoleToggle.vue'
import LuxuryInput from '@/components/auth/LuxuryInput.vue'
import LuxuryButton from '@/components/auth/LuxuryButton.vue'

const router = useRouter()
const route = useRoute()
const loading = ref(false)
const error = ref('')

const role = computed(() => {
  const roleFromMeta = String(route.meta?.loginRole || '').toLowerCase()
  if (roleFromMeta === 'agent') return 'agent'
  if (roleFromMeta === 'user') return 'user'
  const roleFromQuery = String(route.query?.role || '').toLowerCase()
  return roleFromQuery === 'agent' ? 'agent' : 'user'
})

const roleLabel = computed(() => (role.value === 'agent' ? 'Agent' : 'Client'))
const portalLabel = computed(() => (role.value === 'agent' ? 'Agent Access Portal' : 'Client Access Portal'))
const isClient = computed(() => role.value === 'user')

const selectedRole = computed({
  get: () => role.value,
  set: async (nextRole) => {
    const safeRole = nextRole === 'agent' ? 'agent' : 'user'
    if (safeRole === role.value) return
    await router.push(safeRole === 'agent' ? '/login/agent' : '/login/client')
  }
})

const form = reactive({
  email: '',
  password: ''
})

function resolveEndpoint(role) {
  if (role === 'agent') return '/api/agents/Auth/loginapp.php'
  return '/api/users/Auth/loginapp.php'
}

async function submitForm() {
  error.value = ''
  loading.value = true
  try {
    const payload = new FormData()
    payload.append('email', form.email)
    payload.append('password', form.password)

    const res = await api.post(resolveEndpoint(role.value), payload)
    if (res.data?.status) {
      const userData = res.data?.data?.[0] || {}
      const token = userData?.access_token
      if (token) {
        localStorage.setItem('AUTH_TOKEN', token)
        localStorage.setItem('USER_ROLE', role.value)
      }

      await router.push(role.value === 'agent' ? '/dashboard/agent' : '/dashboard/user')
      return
    }

    error.value = res.data?.text || 'Login failed.'
  } catch (err) {
    error.value = err?.response?.data?.text || 'Login failed.'
  } finally {
    loading.value = false
  }
}
</script>
