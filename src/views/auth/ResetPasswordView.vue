<template>
  <div class="min-h-screen flex items-center justify-center px-4 py-16">
    <div class="premium-card shadow-2xl rounded-sm w-full max-w-xl p-10">
      <div class="text-center mb-8">
        <img
          src="/images/DayzLogo.svg"
          alt="Dayz"
          class="h-24 w-auto object-contain mx-auto mb-4 bg-black/60 p-3 rounded-sm ring-1 ring-white/10"
        />
        <span class="text-gold-accent font-bold text-xs uppercase tracking-[0.3em] mb-4 block">Recovery</span>
        <h1 class="text-emerald-900 text-3xl font-display font-bold mb-3">Reset Password</h1>
        <p class="text-emerald-800/60">Enter your OTP and set a new password.</p>
      </div>

      <form @submit.prevent="submit" class="grid gap-6">
        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">OTP</span>
          <input v-model="form.otp" type="text" class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="6-digit code" />
        </label>

        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">New Password</span>
          <div class="relative">
            <input
              v-model="form.password"
              :type="showNewPassword ? 'text' : 'password'"
              class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 pr-12"
              placeholder="New password"
            />
            <button
              type="button"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-emerald-900/70 hover:text-emerald-900"
              @click="showNewPassword = !showNewPassword"
              aria-label="Toggle new password visibility"
            >
              <svg v-if="showNewPassword" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor">
                <path d="M3.3 2.3 2.3 3.3l3.2 3.2A11.1 11.1 0 0 0 1 12s3.7 7 11 7c2.3 0 4.2-.6 5.8-1.5l2.9 2.9 1-1-17.4-17.1Zm8.9 8.8 2.5 2.4a2.9 2.9 0 0 1-2.5.5 3 3 0 0 1-2.3-2.3 2.9 2.9 0 0 1 .5-2.5l1.8 1.9Zm8.8.9s-3.7-7-11-7c-1.3 0-2.4.2-3.5.6l2 1.9a4.8 4.8 0 0 1 7 6.7l1.5 1.5c2.5-1.8 4-4.6 4-4.6Z"/>
              </svg>
              <svg v-else viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor">
                <path d="M12 5C4.7 5 1 12 1 12s3.7 7 11 7 11-7 11-7-3.7-7-11-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/>
              </svg>
            </button>
          </div>
        </label>

        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Confirm Password</span>
          <div class="relative">
            <input
              v-model="form.confirmPassword"
              :type="showConfirmPassword ? 'text' : 'password'"
              class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 pr-12"
              placeholder="Confirm password"
            />
            <button
              type="button"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-emerald-900/70 hover:text-emerald-900"
              @click="showConfirmPassword = !showConfirmPassword"
              aria-label="Toggle confirm password visibility"
            >
              <svg v-if="showConfirmPassword" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor">
                <path d="M3.3 2.3 2.3 3.3l3.2 3.2A11.1 11.1 0 0 0 1 12s3.7 7 11 7c2.3 0 4.2-.6 5.8-1.5l2.9 2.9 1-1-17.4-17.1Zm8.9 8.8 2.5 2.4a2.9 2.9 0 0 1-2.5.5 3 3 0 0 1-2.3-2.3 2.9 2.9 0 0 1 .5-2.5l1.8 1.9Zm8.8.9s-3.7-7-11-7c-1.3 0-2.4.2-3.5.6l2 1.9a4.8 4.8 0 0 1 7 6.7l1.5 1.5c2.5-1.8 4-4.6 4-4.6Z"/>
              </svg>
              <svg v-else viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor">
                <path d="M12 5C4.7 5 1 12 1 12s3.7 7 11 7 11-7 11-7-3.7-7-11-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/>
              </svg>
            </button>
          </div>
        </label>

        <div v-if="error" class="text-sm text-red-600">{{ error }}</div>
        <div v-if="message" class="text-sm text-emerald-700">{{ message }}</div>

        <button :disabled="loading" type="submit" class="emerald-gradient-bg text-white h-14 text-xs font-bold uppercase tracking-[0.3em] hover:brightness-110 shadow-xl transition-all disabled:opacity-60">
          {{ loading ? 'Resetting...' : 'Reset Password' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'

const router = useRouter()
const loading = ref(false)
const error = ref('')
const message = ref('')
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)

const form = reactive({
  otp: '',
  password: '',
  confirmPassword: ''
})

async function submit() {
  error.value = ''
  message.value = ''
  if (!form.otp || !form.password || !form.confirmPassword) {
    error.value = 'All fields are required.'
    return
  }
  if (form.password !== form.confirmPassword) {
    error.value = 'Passwords do not match.'
    return
  }

  loading.value = true
  try {
    const payload = new FormData()
    payload.append('otp', form.otp)
    payload.append('newpassword', form.password)
    const res = await api.post('/api/users/Auth/reset_password.php', payload)
    if (res.data?.status) {
      message.value = res.data?.text || 'Password reset successful.'
      setTimeout(() => router.push('/login?role=user'), 500)
    } else {
      error.value = res.data?.text || 'Unable to reset password.'
    }
  } catch (err) {
    error.value = err?.response?.data?.text || 'Unable to reset password.'
  } finally {
    loading.value = false
  }
}
</script>
