<template>
  <div class="min-h-screen flex items-center justify-center px-4 py-16">
    <div class="premium-card shadow-2xl rounded-sm w-full max-w-xl p-10">
      <div class="text-center mb-10">
        <img
          src="/images/DayzLogo.svg"
          alt="Dayz"
          class="h-32 md:h-36 w-auto object-contain mx-auto mb-4 bg-black/60 p-3 rounded-sm ring-1 ring-white/10"
        />
        <span class="text-gold-accent font-bold text-xs uppercase tracking-[0.3em] mb-4 block">Access</span>
        <h1 class="text-emerald-900 text-4xl font-display font-bold leading-tight mb-3">Sign In to Dayz</h1>
        <p class="text-emerald-800/60">Choose your role and continue.</p>
      </div>

      <form @submit.prevent="submitForm" class="grid gap-6">
        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Role</span>
          <select v-model="form.role" class="form-select block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4">
            <option value="agent">Agent</option>
            <option value="user">User</option>
          </select>
        </label>
        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Email</span>
          <input v-model="form.email" type="email" required class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="you@email.com" />
        </label>
        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Password</span>
          <div class="relative">
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              required
              class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 pr-12"
              placeholder="********"
            />
            <button
              type="button"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-emerald-900/70 hover:text-emerald-900"
              @click="showPassword = !showPassword"
              aria-label="Toggle password visibility"
            >
              <svg v-if="showPassword" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor">
                <path d="M3.3 2.3 2.3 3.3l3.2 3.2A11.1 11.1 0 0 0 1 12s3.7 7 11 7c2.3 0 4.2-.6 5.8-1.5l2.9 2.9 1-1-17.4-17.1Zm8.9 8.8 2.5 2.4a2.9 2.9 0 0 1-2.5.5 3 3 0 0 1-2.3-2.3 2.9 2.9 0 0 1 .5-2.5l1.8 1.9Zm8.8.9s-3.7-7-11-7c-1.3 0-2.4.2-3.5.6l2 1.9a4.8 4.8 0 0 1 7 6.7l1.5 1.5c2.5-1.8 4-4.6 4-4.6Z"/>
              </svg>
              <svg v-else viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor">
                <path d="M12 5C4.7 5 1 12 1 12s3.7 7 11 7 11-7 11-7-3.7-7-11-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/>
              </svg>
            </button>
          </div>
        </label>

        <div class="flex items-center justify-between text-xs uppercase tracking-widest">
          <RouterLink to="/forgot-password" class="text-emerald-900/70 hover:text-emerald-900">
            Forgot Password
          </RouterLink>
          <RouterLink v-if="form.role === 'user'" to="/verify-account" class="text-emerald-900/70 hover:text-emerald-900">
            Verify Account
          </RouterLink>
        </div>

        <div v-if="error" class="text-sm text-red-600">{{ error }}</div>

        <button :disabled="loading" type="submit" class="emerald-gradient-bg text-white h-14 text-xs font-bold uppercase tracking-[0.3em] hover:brightness-110 shadow-xl transition-all disabled:opacity-60">
          {{ loading ? 'Signing In...' : 'Sign In' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'

const router = useRouter()
const route = useRoute()
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)

const form = reactive({
  role: ['agent', 'user'].includes(route.query.role) ? route.query.role : 'user',
  email: '',
  password: ''
})

function resolveEndpoint(role) {
  if (role === 'agent') return '/api/agents/Auth/loginapp.php'
  return '/api/users/Auth/loginapp.php'
}

function isVerified(value) {
  const normalized = String(value || '').toLowerCase()
  return normalized === 'verified' || normalized === '1' || normalized === 'true'
}

async function submitForm() {
  error.value = ''
  loading.value = true
  try {
    const payload = new FormData()
    payload.append('email', form.email)
    payload.append('password', form.password)

    const res = await api.post(resolveEndpoint(form.role), payload)
    if (res.data?.status) {
      const userData = res.data?.data?.[0] || {}
      const token = userData?.access_token
      if (token) {
        localStorage.setItem('AUTH_TOKEN', token)
        localStorage.setItem('USER_ROLE', form.role)
      }

      const hasEmailVerification = isVerified(userData.email_verified)
      const hasPhoneVerification = isVerified(userData.phone_verified)
      if (form.role === 'user' && !hasEmailVerification && !hasPhoneVerification) {
        await router.push('/verify-account')
        return
      }

      await router.push(form.role === 'agent' ? '/dashboard/agent' : '/dashboard/user')
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
