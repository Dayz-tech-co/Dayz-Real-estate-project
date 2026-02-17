<template>
  <div class="min-h-screen flex items-center justify-center px-4 py-16">
    <div class="premium-card shadow-2xl rounded-sm w-full max-w-3xl p-10">
      <div class="text-center mb-10">
        <img
          src="/images/DayzLogo.svg"
          alt="Dayz"
          class="h-32 md:h-36 w-auto object-contain mx-auto mb-4 bg-black/60 p-3 rounded-sm ring-1 ring-white/10"
        />
        <span class="text-gold-accent font-bold text-xs uppercase tracking-[0.3em] mb-4 block">Buyer Account</span>
        <h1 class="text-emerald-900 text-4xl font-display font-bold leading-tight mb-3">Start Your Dayz Journey</h1>
        <p class="text-emerald-800/60">Create your account to save properties and request visits.</p>
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
          <input v-model="form.email" type="email" required class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="you@email.com" />
        </label>
        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Phone Number</span>
          <input v-model="form.phone" type="tel" required class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="+1 234 567 890" />
        </label>

        <div class="grid md:grid-cols-2 gap-6">
          <label class="block">
            <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">City</span>
            <input v-model="form.city" type="text" class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="City" />
          </label>
          <label class="block">
            <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">State</span>
            <input
              v-model="form.state"
              :list="stateListId"
              type="text"
              class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4"
              :placeholder="stateOptions.length ? 'Choose state' : 'Enter state'"
            />
            <datalist :id="stateListId">
              <option v-for="state in stateOptions" :key="state" :value="state" />
            </datalist>
          </label>
        </div>
        <div class="grid md:grid-cols-2 gap-6">
          <label class="block">
            <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Postal Code</span>
            <input v-model="form.postalCode" type="text" class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="Postal code" />
          </label>
          <label class="block">
            <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Country</span>
            <input
              v-model="form.country"
              list="user-countries"
              type="text"
              class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4"
              placeholder="Search country"
            />
            <datalist id="user-countries">
              <option v-for="country in countries" :key="country" :value="country" />
            </datalist>
          </label>
        </div>
        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Street Address</span>
          <input v-model="form.street" type="text" class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4" placeholder="Street address" />
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
        <label class="block">
          <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Confirm Password</span>
          <div class="relative">
            <input
              v-model="form.confirmPassword"
              :type="showConfirmPassword ? 'text' : 'password'"
              required
              class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 pr-12"
              placeholder="********"
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

        <button :disabled="loading" type="submit" class="emerald-gradient-bg text-white h-14 text-xs font-bold uppercase tracking-[0.3em] hover:brightness-110 shadow-xl transition-all disabled:opacity-60">
          {{ loading ? 'Creating...' : 'Create Account' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import { countries } from '@/lib/countries'
import { getStatesForCountry } from '@/lib/states'

const router = useRouter()
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)
const showConfirmPassword = ref(false)

const form = reactive({
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  password: '',
  confirmPassword: '',
  country: '',
  city: '',
  state: '',
  postalCode: '',
  street: ''
})

const stateListId = 'user-states-list'
const stateOptions = computed(() => getStatesForCountry(form.country))

watch(
  () => form.country,
  () => {
    form.state = ''
  }
)

function friendlyRegistrationError(text) {
  const raw = String(text || '').toLowerCase()
  if (raw.includes('data already created') || raw.includes('already created')) {
    return 'Email already used. Please sign in or use a different email.'
  }
  return text || 'Registration failed.'
}

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
    payload.append('password', form.password)
    payload.append('phoneno', form.phone)
    payload.append('country', form.country)
    payload.append('city', form.city)
    payload.append('state', form.state)
    payload.append('postal_code', form.postalCode)
    payload.append('streetname', form.street)

    const res = await api.post('/api/users/Auth/register.php', payload)
    if (res.data?.status) {
      const token = res.data?.data?.[0]?.access_token
      if (token) {
        localStorage.setItem('AUTH_TOKEN', token)
        localStorage.setItem('USER_ROLE', 'user')
      }
      await router.push('/verify-account')
      return
    }

    error.value = friendlyRegistrationError(res.data?.text)
  } catch (err) {
    error.value = friendlyRegistrationError(err?.response?.data?.text)
  } finally {
    loading.value = false
  }
}
</script>
