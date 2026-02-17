<template>
  <div class="min-h-screen flex items-center justify-center px-4 py-16">
    <div class="premium-card shadow-2xl rounded-sm w-full max-w-2xl p-10">
      <div class="text-center mb-10">
        <img
          src="/images/DayzLogo.svg"
          alt="Dayz"
          class="h-32 md:h-36 w-auto object-contain mx-auto mb-4 bg-black/60 p-3 rounded-sm ring-1 ring-white/10"
        />
        <span class="text-gold-accent font-bold text-xs uppercase tracking-[0.3em] mb-4 block">Administrator</span>
        <h1 class="text-emerald-900 text-4xl font-display font-bold leading-tight mb-3">Create an Admin Account</h1>
        <p class="text-emerald-800/60">Access Dayz operations and manage platform activity.</p>
      </div>

      <form @submit.prevent="submitForm" class="grid gap-6">
        <div class="grid md:grid-cols-2 gap-6">
          <label class="block">
            <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">First Name</span>
            <input v-model="form.firstName" type="text" required class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="First name" />
          </label>
          <label class="block">
            <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Last Name</span>
            <input v-model="form.lastName" type="text" required class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="Last name" />
          </label>
        </div>
        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Email</span>
          <input v-model="form.email" type="email" required class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="admin@dayz.com" />
        </label>
        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Phone</span>
          <input v-model="form.phone" type="tel" required class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="+1 212 555 0198" />
        </label>
        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Password</span>
          <input v-model="form.password" type="password" required class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="????????" />
        </label>
        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Confirm Password</span>
          <input v-model="form.confirmPassword" type="password" required class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="????????" />
        </label>

        <div v-if="error" class="text-sm text-red-600">{{ error }}</div>

        <button :disabled="loading" type="submit" class="emerald-gradient-bg text-white h-14 text-xs font-bold uppercase tracking-[0.3em] hover:brightness-110 shadow-xl transition-all disabled:opacity-60">
          {{ loading ? 'Creating...' : 'Create Admin' }}
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

const form = reactive({
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  password: '',
  confirmPassword: ''
})

async function submitForm() {
  error.value = ''
  if (form.password !== form.confirmPassword) {
    error.value = 'Passwords do not match.'
    return
  }

  loading.value = true
  try {
    const payload = new FormData()
    payload.append('fname', form.firstName)
    payload.append('lname', form.lastName)
    payload.append('email', form.email)
    payload.append('phoneno', form.phone)
    payload.append('password', form.password)

    const res = await api.post('/api/admin/Auth/create_admin.php', payload)
    if (res.data?.status) {
      const token = res.data?.data?.[0]?.access_token
      if (token) {
        localStorage.setItem('AUTH_TOKEN', token)
        localStorage.setItem('USER_ROLE', 'admin')
      }
      await router.push('/login?role=admin')
      return
    }

    error.value = res.data?.text || 'Registration failed.'
  } catch (err) {
    error.value = err?.response?.data?.text || 'Registration failed.'
  } finally {
    loading.value = false
  }
}
</script>
