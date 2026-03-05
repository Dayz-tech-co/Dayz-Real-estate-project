<template>
  <AuthLayout role="agent" page="login">
    <EmeraldCard>
      <div class="mb-8 text-center">
        <h1 class="text-4xl font-semibold tracking-[0.2em] text-dayz-gold">DAYZ</h1>
        <p class="mt-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-dayz-gold/85">Agent Access Portal</p>
        <div class="mx-auto mt-4 h-px w-20 bg-dayz-gold/70"></div>
      </div>

      <form class="space-y-4" @submit.prevent="submitForm">
        <div class="border border-dayz-border-muted bg-[#0f1916] px-3 py-2 text-center text-xs font-semibold uppercase tracking-[0.2em] text-dayz-gold">
          Agent Login
        </div>

        <LuxuryInput
          v-model="form.email"
          label="Email"
          icon="email"
          type="email"
          autocomplete="email"
          placeholder="you@email.com"
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
          required
        />

        <div class="flex items-center justify-between text-[11px] uppercase tracking-[0.12em]">
          <RouterLink to="/forgot-password?role=agent" class="auth-link">Forgot Password</RouterLink>
          <RouterLink to="/verify-account?role=agent" class="auth-link">Verify Account</RouterLink>
        </div>

        <p v-if="error" class="text-sm text-red-300">{{ error }}</p>

        <LuxuryButton
          text="Sign In Securely"
          loading-text="Signing In..."
          :loading="loading"
          :show-lock="true"
        />

        <p class="flex items-center justify-center gap-2 text-[11px] text-slate-400">
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
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api from '@/lib/api'
import AuthLayout from '@/components/auth/AuthLayout.vue'
import EmeraldCard from '@/components/auth/EmeraldCard.vue'
import LuxuryInput from '@/components/auth/LuxuryInput.vue'
import LuxuryButton from '@/components/auth/LuxuryButton.vue'

const router = useRouter()
const loading = ref(false)
const error = ref('')

const form = reactive({
  email: '',
  password: ''
})

async function submitForm() {
  error.value = ''
  loading.value = true
  try {
    const payload = new FormData()
    payload.append('email', form.email)
    payload.append('password', form.password)

    const res = await api.post('/api/agents/Auth/loginapp.php', payload)
    if (res.data?.status) {
      const userData = res.data?.data?.[0] || {}
      const token = userData?.access_token
      if (token) {
        localStorage.setItem('AUTH_TOKEN', token)
        localStorage.setItem('USER_ROLE', 'agent')
      }
      await router.push('/dashboard/agent')
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
