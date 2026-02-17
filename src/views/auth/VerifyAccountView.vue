<template>
  <div class="min-h-screen flex items-center justify-center px-4 py-16">
    <div class="premium-card shadow-2xl rounded-sm w-full max-w-2xl p-10">
      <div class="text-center mb-8">
        <img
          src="/images/DayzLogo.svg"
          alt="Dayz"
          class="h-28 w-auto object-contain mx-auto mb-4 bg-black/60 p-3 rounded-sm ring-1 ring-white/10"
        />
        <span class="text-gold-accent font-bold text-xs uppercase tracking-[0.3em] mb-4 block">Verification</span>
        <h1 class="text-emerald-900 text-4xl font-display font-bold leading-tight mb-3">Verify Your Account</h1>
        <p class="text-emerald-800/60">Send and confirm OTP for email or phone verification.</p>
      </div>

      <div class="grid gap-6">
        <div class="grid grid-cols-2 gap-3">
          <button
            type="button"
            class="h-11 border text-xs uppercase tracking-widest"
            :class="type === 'email' ? 'border-emerald-900 bg-emerald-50/30 text-emerald-900' : 'border-emerald-900/20 text-emerald-900/70'"
            @click="setType('email')"
          >
            Email
          </button>
          <button
            type="button"
            class="h-11 border text-xs uppercase tracking-widest"
            :class="type === 'phone' ? 'border-emerald-900 bg-emerald-50/30 text-emerald-900' : 'border-emerald-900/20 text-emerald-900/70'"
            @click="setType('phone')"
          >
            Phone
          </button>
        </div>

        <div class="rounded border border-emerald-900/10 bg-emerald-50/20 p-4 text-sm text-emerald-900/80">
          <p>Email Status: <strong>{{ profile.email_verified || 'unknown' }}</strong></p>
          <p>Phone Status: <strong>{{ profile.phone_verified || 'unknown' }}</strong></p>
        </div>

        <button
          type="button"
          class="emerald-gradient-bg text-white h-12 text-xs font-bold uppercase tracking-[0.3em] hover:brightness-110 disabled:opacity-60"
          :disabled="sending || resendCountdown > 0"
          @click="sendOtp"
        >
          {{ sending ? 'Sending...' : resendCountdown > 0 ? `Resend in ${resendCountdown}s` : `Send ${type} OTP` }}
        </button>

        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Verification Code</span>
          <input v-model="code" type="text" class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="Enter 6-digit code" />
        </label>

        <button
          type="button"
          class="border border-emerald-900/20 text-emerald-900 h-12 text-xs font-bold uppercase tracking-[0.3em] hover:bg-emerald-50/30 disabled:opacity-60"
          :disabled="verifying || !code"
          @click="verifyOtp"
        >
          {{ verifying ? 'Verifying...' : `Verify ${type}` }}
        </button>

        <div v-if="message" class="text-sm text-emerald-700">{{ message }}</div>
        <div v-if="error" class="text-sm text-red-600">{{ error }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'

const router = useRouter()
const type = ref('email')
const code = ref('')
const sending = ref(false)
const verifying = ref(false)
const error = ref('')
const message = ref('')
const resendCountdown = ref(0)
let resendTimer = null

const profile = reactive({
  email_verified: '',
  phone_verified: ''
})

function setType(nextType) {
  type.value = nextType
  error.value = ''
  message.value = ''
}

function isVerified(value) {
  const normalized = String(value || '').toLowerCase()
  return normalized === 'verified' || normalized === '1' || normalized === 'true'
}

async function loadProfile() {
  try {
    const res = await api.post('/api/users/Profile/view_profile.php')
    if (res.data?.status) {
      Object.assign(profile, res.data?.data || {})
      if (isVerified(profile.email_verified) && isVerified(profile.phone_verified)) {
        localStorage.removeItem('AUTH_TOKEN')
        localStorage.removeItem('USER_ROLE')
        router.push('/login?role=user')
      }
    }
  } catch (err) {
    // no-op
  }
}

function startResendCountdown(seconds = 60) {
  resendCountdown.value = seconds
  if (resendTimer) clearInterval(resendTimer)
  resendTimer = setInterval(() => {
    if (resendCountdown.value <= 1) {
      clearInterval(resendTimer)
      resendTimer = null
      resendCountdown.value = 0
      return
    }
    resendCountdown.value -= 1
  }, 1000)
}

async function sendOtp() {
  error.value = ''
  message.value = ''
  sending.value = true
  try {
    const payload = new FormData()
    payload.append('type', type.value)
    const res = await api.post('/api/users/Auth/send_verification.php', payload)
    if (res.data?.status) {
      message.value = res.data?.text || 'Verification code sent.'
      startResendCountdown(60)
    } else {
      error.value = res.data?.text || 'Unable to send verification code.'
    }
  } catch (err) {
    error.value = err?.response?.data?.text || 'Unable to send verification code.'
  } finally {
    sending.value = false
  }
}

async function verifyOtp() {
  error.value = ''
  message.value = ''
  verifying.value = true
  try {
    const payload = new FormData()
    payload.append('type', type.value)
    payload.append('code', code.value)
    const res = await api.post('/api/users/Auth/verify_code.php', payload)
    if (res.data?.status) {
      message.value = res.data?.text || 'Verification successful.'
      code.value = ''
      localStorage.removeItem('AUTH_TOKEN')
      localStorage.removeItem('USER_ROLE')
      setTimeout(() => {
        router.push('/login?role=user')
      }, 300)
    } else {
      error.value = res.data?.text || 'Verification failed.'
    }
  } catch (err) {
    error.value = err?.response?.data?.text || 'Verification failed.'
  } finally {
    verifying.value = false
  }
}

onMounted(() => {
  if (!localStorage.getItem('AUTH_TOKEN')) {
    router.push('/login?role=user')
    return
  }
  loadProfile()
})

onBeforeUnmount(() => {
  if (resendTimer) {
    clearInterval(resendTimer)
    resendTimer = null
  }
})
</script>
