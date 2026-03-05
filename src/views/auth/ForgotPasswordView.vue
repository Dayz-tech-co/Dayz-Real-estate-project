<template>
  <AuthLayout>
    <EmeraldCard>
      <div class="mb-7 text-center">
        <h1 class="text-3xl font-semibold tracking-[0.2em] text-dayz-gold">RECOVERY</h1>
        <p class="mt-2 text-[11px] uppercase tracking-[0.2em] text-dayz-gold/80">Reset Access</p>
      </div>

      <form class="space-y-4" @submit.prevent="submit">
        <RoleToggle v-model="role" />

        <div class="grid grid-cols-2 gap-2 rounded border border-dayz-border-muted bg-[#0d1714] p-1 text-xs uppercase tracking-[0.14em]">
          <button type="button" class="h-10 transition-colors" :class="form.type === 'email' ? 'border border-dayz-gold bg-dayz-emerald text-dayz-gold' : 'text-slate-300'" @click="form.type = 'email'">
            Email
          </button>
          <button type="button" class="h-10 transition-colors" :class="form.type === 'phone' ? 'border border-dayz-gold bg-dayz-emerald text-dayz-gold' : 'text-slate-300'" @click="form.type = 'phone'">
            Phone
          </button>
        </div>

        <LuxuryInput
          v-if="form.type === 'email'"
          v-model="form.email"
          label="Email"
          icon="email"
          type="email"
          placeholder="you@email.com"
          required
        />

        <LuxuryInput
          v-else
          v-model="form.phoneno"
          label="Phone"
          icon="phone"
          placeholder="+234 800 000 0000"
          required
        />

        <p v-if="error" class="text-sm text-red-300">{{ error }}</p>
        <p v-if="message" class="text-sm text-emerald-300">{{ message }}</p>

        <LuxuryButton text="Send OTP" loading-text="Sending..." :loading="loading" />
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
  type: 'email',
  email: '',
  phoneno: ''
})

async function submit() {
  error.value = ''
  message.value = ''

  if (form.type === 'email' && !form.email) {
    error.value = 'Email is required.'
    return
  }
  if (form.type === 'phone' && !form.phoneno) {
    error.value = 'Phone is required.'
    return
  }

  loading.value = true
  try {
    const payload = new FormData()
    payload.append('type', form.type)
    if (form.email) payload.append('email', form.email)
    if (form.phoneno) payload.append('phoneno', form.phoneno)

    const endpoint = role.value === 'agent' ? '/api/agents/Auth/forget_password.php' : '/api/users/Auth/forget_password.php'
    const res = await api.post(endpoint, payload)
    if (res.data?.status) {
      message.value = res.data?.text || 'OTP sent.'
      setTimeout(() => router.push(`/reset-password?role=${role.value}`), 400)
    } else {
      error.value = res.data?.text || 'Unable to send OTP.'
    }
  } catch (err) {
    error.value = err?.response?.data?.text || 'Unable to send OTP.'
  } finally {
    loading.value = false
  }
}
</script>
