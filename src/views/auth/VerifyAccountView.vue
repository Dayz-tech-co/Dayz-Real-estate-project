<template>
  <AuthLayout>
    <EmeraldCard>
      <div class="mb-7 text-center">
        <h1 class="text-3xl font-semibold tracking-[0.2em] text-dayz-gold">VERIFY ACCOUNT</h1>
        <p class="mt-2 text-[11px] uppercase tracking-[0.2em] text-dayz-gold/80">Trust & Security</p>
      </div>

      <div class="space-y-4">
        <div class="border border-dayz-border-muted bg-[#0f1916] px-3 py-2 text-center text-xs font-semibold uppercase tracking-[0.2em] text-dayz-gold">
          {{ roleLabel }} Verification
        </div>

        <div class="grid grid-cols-2 gap-2 rounded border border-dayz-border-muted bg-[#0d1714] p-1 text-xs uppercase tracking-[0.14em]">
          <button type="button" class="h-10 transition-colors" :class="type === 'email' ? 'border border-dayz-gold bg-dayz-emerald text-dayz-gold' : 'text-slate-300'" @click="setType('email')">
            Email
          </button>
          <button type="button" class="h-10 transition-colors" :class="type === 'phone' ? 'border border-dayz-gold bg-dayz-emerald text-dayz-gold' : 'text-slate-300'" @click="setType('phone')">
            Phone
          </button>
        </div>

        <div class="border border-dayz-border-muted bg-[#0f1916] p-3 text-sm text-slate-300">
          <p>Email Status: <strong class="text-dayz-gold">{{ formatVerificationStatus(profile.email_verified) }}</strong></p>
          <p>Phone Status: <strong class="text-dayz-gold">{{ formatVerificationStatus(profile.phone_verified) }}</strong></p>
        </div>

        <LuxuryButton
          text="Send Verification OTP"
          loading-text="Sending..."
          :disabled="resendCountdown > 0"
          :loading="sending"
          type="button"
          @click="sendOtp"
        />

        <p v-if="resendCountdown > 0" class="text-xs text-slate-400">Resend available in {{ resendCountdown }}s</p>

        <LuxuryInput v-model="code" label="Verification Code" icon="wallet" placeholder="Enter 6-digit code" />

        <LuxuryButton
          text="Verify"
          loading-text="Verifying..."
          :loading="verifying"
          :disabled="!code"
          type="button"
          @click="verifyOtp"
        />

        <div v-if="message" class="text-sm text-emerald-300">{{ message }}</div>
        <div v-if="error" class="text-sm text-red-300">{{ error }}</div>
      </div>
    </EmeraldCard>
  </AuthLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'
import AuthLayout from '@/components/auth/AuthLayout.vue'
import EmeraldCard from '@/components/auth/EmeraldCard.vue'
import LuxuryInput from '@/components/auth/LuxuryInput.vue'
import LuxuryButton from '@/components/auth/LuxuryButton.vue'

const router = useRouter()
const route = useRoute()
const type = ref('email')
const code = ref('')
const sending = ref(false)
const verifying = ref(false)
const error = ref('')
const message = ref('')
const resendCountdown = ref(0)
const autoSendTriggered = ref(false)
let resendTimer = null

const profile = reactive({
  email_verified: '',
  phone_verified: ''
})

const role = computed(() => {
  const storedRole = String(localStorage.getItem('USER_ROLE') || '').toLowerCase()
  const queryRole = String(route.query.role || '').toLowerCase()
  if (['agent', 'user'].includes(storedRole)) return storedRole
  if (['agent', 'user'].includes(queryRole)) return queryRole
  return 'user'
})

const roleLabel = computed(() => (role.value === 'agent' ? 'Agent' : 'Client'))

function formatVerificationStatus(value) {
  const normalized = String(value ?? '').toLowerCase()
  if (normalized === '1' || normalized === 'verified' || normalized === 'true') return 'verified'
  if (normalized === '0' || normalized === 'not_verified' || normalized === 'false') return 'not verified'
  if (!normalized) return 'unknown'
  return normalized
}

function resolveSendEndpoint() {
  return role.value === 'agent' ? '/api/agents/Auth/send_verification.php' : '/api/users/Auth/send_verification.php'
}

function resolveVerifyEndpoint() {
  return role.value === 'agent' ? '/api/agents/Auth/verify_code.php' : '/api/users/Auth/verify_code.php'
}

function setType(nextType) {
  type.value = nextType
  error.value = ''
  message.value = ''
}

function detectPreferredType() {
  const requestedType = String(route.query.type || '').toLowerCase()
  if (requestedType === 'email' || requestedType === 'phone') return requestedType
  if (!isVerified(profile.email_verified)) return 'email'
  if (!isVerified(profile.phone_verified)) return 'phone'
  return 'email'
}

function isVerified(value) {
  const normalized = String(value || '').toLowerCase()
  return normalized === 'verified' || normalized === '1' || normalized === 'true'
}

async function loadProfile() {
  try {
    if (role.value === 'agent') {
      const profileRes = await api.post('/api/agents/profile/view_profile.php')

      if (profileRes.data?.status) {
        const raw = profileRes.data?.data
        const data = Array.isArray(raw) ? (raw[0] || {}) : (raw || {})
        profile.email_verified = data.emailverified
        profile.phone_verified = data.phoneverified
      }
    } else {
      const res = await api.post('/api/users/Profile/view_profile.php')
      if (res.data?.status) {
        const raw = res.data?.data
        const data = Array.isArray(raw) ? (raw[0] || {}) : (raw || {})
        Object.assign(profile, data)
      }
    }

    if (isVerified(profile.email_verified) && isVerified(profile.phone_verified)) {
      localStorage.removeItem('AUTH_TOKEN')
      localStorage.removeItem('USER_ROLE')
      router.push(`/login?role=${role.value}`)
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
    const res = await api.post(resolveSendEndpoint(), payload)
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
    const res = await api.post(resolveVerifyEndpoint(), payload)
    if (res.data?.status) {
      message.value = res.data?.text || 'Verification successful.'
      code.value = ''
      localStorage.removeItem('AUTH_TOKEN')
      localStorage.removeItem('USER_ROLE')
      setTimeout(() => {
        router.push(`/login?role=${role.value}`)
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
    router.push(`/login?role=${role.value}`)
    return
  }
  localStorage.setItem('USER_ROLE', role.value)
  loadProfile().then(async () => {
    const preferredType = detectPreferredType()
    if (type.value !== preferredType) type.value = preferredType

    const shouldAutoSend = String(route.query.auto_send || '') === '1'
    if (!shouldAutoSend || autoSendTriggered.value) return

    const selectedVerified = type.value === 'email'
      ? isVerified(profile.email_verified)
      : isVerified(profile.phone_verified)
    if (selectedVerified) return

    autoSendTriggered.value = true
    await sendOtp()
  })
})

onBeforeUnmount(() => {
  if (resendTimer) {
    clearInterval(resendTimer)
    resendTimer = null
  }
})
</script>
