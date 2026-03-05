<template>
  <AuthLayout>
    <EmeraldCard>
      <div class="mb-7 text-center">
        <h1 class="text-3xl font-semibold tracking-[0.2em] text-dayz-gold">RESET PASSWORD</h1>
        <p class="mt-2 text-[11px] uppercase tracking-[0.2em] text-dayz-gold/80">Recovery Portal</p>
      </div>

      <form class="space-y-4" @submit.prevent="submit">
        <RoleToggle v-model="role" />

        <LuxuryInput v-model="form.otp" label="OTP Code" icon="wallet" placeholder="6-digit code" required />
        <LuxuryInput
          v-model="form.password"
          label="New Password"
          icon="lock"
          type="password"
          :password-toggle="true"
          placeholder="New password"
          required
        />
        <LuxuryInput
          v-model="form.confirmPassword"
          label="Confirm Password"
          icon="lock"
          type="password"
          :password-toggle="true"
          placeholder="Confirm password"
          required
        />

        <p v-if="error" class="text-sm text-red-300">{{ error }}</p>
        <p v-if="message" class="text-sm text-emerald-300">{{ message }}</p>

        <LuxuryButton text="Reset Password" loading-text="Resetting..." :loading="loading" />
      </form>
    </EmeraldCard>
  </AuthLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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
const message = ref('')
const role = ref(['agent', 'user'].includes(String(route.query.role || '').toLowerCase()) ? String(route.query.role).toLowerCase() : 'user')

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
    const endpoint = role.value === 'agent' ? '/api/agents/Auth/reset_password.php' : '/api/users/Auth/reset_password.php'
    const res = await api.post(endpoint, payload)
    if (res.data?.status) {
      message.value = res.data?.text || 'Password reset successful.'
      setTimeout(() => router.push(`/login?role=${role.value}`), 500)
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
