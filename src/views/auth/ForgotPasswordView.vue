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
        <h1 class="text-emerald-900 text-3xl font-display font-bold mb-3">Forgot Password</h1>
        <p class="text-emerald-800/60">Request an OTP to reset your password.</p>
      </div>

      <form @submit.prevent="submit" class="grid gap-6">
        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Recovery Method</span>
          <select v-model="form.type" class="form-select block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4">
            <option value="email">Email</option>
            <option value="phone">Phone</option>
          </select>
        </label>

        <label class="block" v-if="form.type === 'email'">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Email</span>
          <input v-model="form.email" type="email" class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="you@email.com" />
        </label>

        <label class="block" v-else>
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Phone</span>
          <input v-model="form.phoneno" type="text" class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="+1234567890" />
        </label>

        <div v-if="error" class="text-sm text-red-600">{{ error }}</div>
        <div v-if="message" class="text-sm text-emerald-700">{{ message }}</div>

        <button :disabled="loading" type="submit" class="emerald-gradient-bg text-white h-14 text-xs font-bold uppercase tracking-[0.3em] hover:brightness-110 shadow-xl transition-all disabled:opacity-60">
          {{ loading ? 'Sending...' : 'Send OTP' }}
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

    const res = await api.post('/api/users/Auth/forget_password.php', payload)
    if (res.data?.status) {
      message.value = res.data?.text || 'OTP sent.'
      setTimeout(() => router.push('/reset-password'), 400)
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
