<template>
  <AuthLayout role="user" page="login">
    <EmeraldCard variant="client">
      <div class="mb-8 space-y-3 text-center">
        <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-dayz-gold/85">PRIVATE ACCESS</p>
        <h1 class="font-display text-4xl font-semibold text-slate-100">Sign In to Your Portfolio</h1>
        <p class="mx-auto max-w-md text-sm leading-6 text-slate-300/85">
          Manage your saved properties and investment activity.
        </p>
        <div class="mx-auto mt-4 h-px w-28 bg-dayz-gold/70"></div>
      </div>

      <form class="space-y-4" @submit.prevent="submitForm">
        <div class="border border-[#29463b] bg-[#12201c] px-3 py-2 text-center text-xs font-semibold uppercase tracking-[0.2em] text-dayz-gold">
          Client Login
        </div>

        <LuxuryInput
          v-model="form.email"
          label="Email"
          icon="email"
          type="email"
          autocomplete="email"
          placeholder="you@email.com"
          variant="client"
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
          variant="client"
          required
        />

        <div class="flex items-center justify-between text-[11px] uppercase tracking-[0.12em]">
          <RouterLink to="/forgot-password?role=user" class="auth-link">Forgot Password</RouterLink>
          <RouterLink to="/verify-account?role=user" class="auth-link">Verify Account</RouterLink>
        </div>

        <p v-if="error" class="text-sm text-red-300">{{ error }}</p>

        <LuxuryButton
          text="ACCESS PORTFOLIO"
          loading-text="Signing In..."
          :loading="loading"
          :show-lock="true"
          variant="client"
        />

        <div class="space-y-2 pt-1 text-xs text-dayz-gold/85">
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

    const res = await api.post('/api/users/Auth/loginapp.php', payload)
    if (res.data?.status) {
      const userData = res.data?.data?.[0] || {}
      const token = userData?.access_token
      if (token) {
        localStorage.setItem('AUTH_TOKEN', token)
        localStorage.setItem('USER_ROLE', 'user')
      }
      await router.push('/dashboard/user')
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
