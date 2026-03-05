<template>
  <AuthLayout :role="role" page="register">
    <EmeraldCard :variant="isClient ? 'client' : 'default'">
      <div class="mb-8 text-center" :class="isClient ? 'space-y-3' : ''">
        <template v-if="isClient">
          <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-dayz-gold/85">PRIVATE ACCESS</p>
          <h1 class="font-display text-4xl font-semibold text-slate-100">Apply for Portfolio Access</h1>
          <p class="mx-auto max-w-md text-sm leading-6 text-slate-300/85">
            Submit your investor profile to unlock curated opportunities.
          </p>
        </template>
        <template v-else>
          <h1 class="text-4xl font-semibold tracking-[0.2em] text-dayz-gold">DAYZ</h1>
          <p class="mt-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-dayz-gold/85">{{ roleLabel }} Membership Access</p>
        </template>
        <div class="mx-auto mt-4 h-px bg-dayz-gold/70" :class="isClient ? 'w-28' : 'w-20'"></div>
      </div>

      <form class="space-y-5" @submit.prevent="submitForm">
        <RoleToggle v-model="selectedRole" :variant="isClient ? 'client' : 'default'" />

        <div class="border px-3 py-2 text-center text-xs font-semibold uppercase tracking-[0.2em] text-dayz-gold" :class="isClient ? 'border-[#29463b] bg-[#12201c]' : 'border-dayz-border-muted bg-[#0f1916]'">
          {{ roleLabel }} Registration
        </div>

        <section class="space-y-4">
          <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-dayz-gold/85">{{ isClient ? 'Personal Details' : 'Account Details' }}</p>
          <LuxuryInput v-model="form.fullName" :variant="isClient ? 'client' : 'default'" label="Full Name" icon="user" placeholder="John Doe" required />
          <LuxuryInput v-model="form.email" :variant="isClient ? 'client' : 'default'" label="Email" icon="email" type="email" placeholder="you@email.com" required />
          <LuxuryInput
            v-if="role === 'agent'"
            v-model="form.phone"
            label="Phone Number"
            icon="phone"
            placeholder="+234 800 000 0000"
            required
          />
          <LuxuryInput
            v-model="form.password"
            :variant="isClient ? 'client' : 'default'"
            label="Password"
            icon="lock"
            type="password"
            :password-toggle="true"
            placeholder="Create password"
            required
          />
          <LuxuryInput
            v-model="form.confirmPassword"
            :variant="isClient ? 'client' : 'default'"
            label="Confirm Password"
            icon="lock"
            type="password"
            :password-toggle="true"
            placeholder="Confirm password"
            required
          />
        </section>

        <div class="h-px bg-dayz-gold/30"></div>

        <section class="space-y-4" :class="isClient ? 'pt-1' : ''">
          <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-dayz-gold/85">{{ isClient ? 'Investment Profile' : 'Role Specific' }}</p>
          <template v-if="role === 'agent'">
            <LuxuryInput v-model="form.agencyName" label="Agency Name" icon="building" placeholder="Dayz Realty Group" required />
            <LuxuryInput v-model="form.licenseNumber" label="License Number" icon="user" placeholder="LIC-00431" required />
            <LuxuryInput v-model="form.officeLocation" label="Office Location" icon="building" placeholder="Victoria Island, Lagos" required />
            <LuxuryInput v-model="form.yearsExperience" label="Years of Experience" icon="wallet" placeholder="8" required />
          </template>
          <template v-else>
            <label class="block">
              <span class="mb-2 block text-[10px] font-semibold uppercase tracking-[0.18em] text-dayz-gold/80">Investment Budget Range</span>
              <select
                v-model="form.budgetRange"
                required
                class="h-12 w-full border border-[#294037] bg-[#121C19] px-3 text-sm text-slate-100 outline-none transition-all duration-300 focus:border-dayz-gold focus:shadow-[0_0_0_1px_rgba(198,167,94,0.22),0_0_10px_rgba(29,82,62,0.24)]"
              >
                <option disabled value="">Select budget range</option>
                <option v-for="range in budgetRanges" :key="range" :value="range">{{ range }}</option>
              </select>
            </label>

            <label class="block">
              <span class="mb-2 block text-[10px] font-semibold uppercase tracking-[0.18em] text-dayz-gold/80">Preferred Locations</span>
              <select
                v-model="form.preferredLocationsList"
                multiple
                required
                class="min-h-[124px] w-full border border-[#294037] bg-[#121C19] px-3 py-2 text-sm text-slate-100 outline-none transition-all duration-300 focus:border-dayz-gold focus:shadow-[0_0_0_1px_rgba(198,167,94,0.22),0_0_10px_rgba(29,82,62,0.24)]"
              >
                <option v-for="location in preferredLocationOptions" :key="location" :value="location">{{ location }}</option>
              </select>
            </label>

            <label class="block">
              <span class="mb-2 block text-[10px] font-semibold uppercase tracking-[0.18em] text-dayz-gold/80">Property Type Interest</span>
              <select
                v-model="form.propertyInterestList"
                multiple
                required
                class="min-h-[124px] w-full border border-[#294037] bg-[#121C19] px-3 py-2 text-sm text-slate-100 outline-none transition-all duration-300 focus:border-dayz-gold focus:shadow-[0_0_0_1px_rgba(198,167,94,0.22),0_0_10px_rgba(29,82,62,0.24)]"
              >
                <option v-for="interest in propertyTypeOptions" :key="interest" :value="interest">{{ interest }}</option>
              </select>
            </label>

            <label class="block">
              <span class="mb-2 block text-[10px] font-semibold uppercase tracking-[0.18em] text-dayz-gold/80">Investment Goal</span>
              <select
                v-model="form.investmentGoal"
                required
                class="h-12 w-full border border-[#294037] bg-[#121C19] px-3 text-sm text-slate-100 outline-none transition-all duration-300 focus:border-dayz-gold focus:shadow-[0_0_0_1px_rgba(198,167,94,0.22),0_0_10px_rgba(29,82,62,0.24)]"
              >
                <option disabled value="">Select investment goal</option>
                <option value="Capital Growth">Capital Growth</option>
                <option value="Rental Yield">Rental Yield</option>
                <option value="Mixed">Mixed</option>
              </select>
            </label>

            <div class="space-y-2">
              <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-dayz-gold/80">Working with a private wealth advisor?</p>
              <div class="grid grid-cols-2 gap-2 rounded border border-[#294037] bg-[#121C19] p-1">
                <button
                  type="button"
                  class="h-10 text-xs font-semibold uppercase tracking-[0.16em] transition-all"
                  :class="form.hasWealthAdvisor === 'yes' ? 'border border-dayz-gold/80 bg-[#143229] text-dayz-gold' : 'border border-transparent text-slate-300 hover:text-dayz-gold/90'"
                  @click="form.hasWealthAdvisor = 'yes'"
                >
                  Yes
                </button>
                <button
                  type="button"
                  class="h-10 text-xs font-semibold uppercase tracking-[0.16em] transition-all"
                  :class="form.hasWealthAdvisor === 'no' ? 'border border-dayz-gold/80 bg-[#143229] text-dayz-gold' : 'border border-transparent text-slate-300 hover:text-dayz-gold/90'"
                  @click="form.hasWealthAdvisor = 'no'"
                >
                  No
                </button>
              </div>
            </div>
          </template>
        </section>

        <p v-if="error" class="text-sm text-red-300">{{ error }}</p>

        <LuxuryButton
          :text="isClient ? 'REQUEST ACCESS' : 'Create Membership'"
          loading-text="Creating Account..."
          :loading="loading"
          :show-lock="true"
          :variant="isClient ? 'client' : 'default'"
        />

        <p class="text-center text-xs text-slate-400">
          Already have access?
          <RouterLink :to="role === 'agent' ? '/login/agent' : '/login/client'" class="auth-link">Sign in</RouterLink>
        </p>
      </form>
    </EmeraldCard>
  </AuthLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
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
const role = computed(() => {
  const roleFromMeta = String(route.meta?.registerRole || '').toLowerCase()
  if (roleFromMeta === 'agent') return 'agent'
  if (roleFromMeta === 'user') return 'user'
  const roleFromQuery = String(route.query?.role || '').toLowerCase()
  return roleFromQuery === 'agent' ? 'agent' : 'user'
})
const roleLabel = computed(() => (role.value === 'agent' ? 'Agent' : 'Client'))
const isClient = computed(() => role.value === 'user')

const selectedRole = computed({
  get: () => role.value,
  set: async (nextRole) => {
    const safeRole = nextRole === 'agent' ? 'agent' : 'user'
    if (safeRole === role.value) return
    await router.push(safeRole === 'agent' ? '/register/agent' : '/register/client')
  }
})

const budgetRanges = [
  'Below N20M',
  'N20M - N50M',
  'N50M - N120M',
  'N120M - N250M',
  'Above N250M'
]

const preferredLocationOptions = [
  'Ikoyi',
  'Banana Island',
  'Victoria Island',
  'Lekki Phase 1',
  'Eko Atlantic',
  'Abuja - Maitama',
  'Abuja - Asokoro'
]

const propertyTypeOptions = [
  'Shortlet',
  'Apartment',
  'Penthouse',
  'Hotel',
  'Land',
  'Commercial'
]

const form = reactive({
  fullName: '',
  email: '',
  phone: '',
  password: '',
  confirmPassword: '',
  agencyName: '',
  licenseNumber: '',
  officeLocation: '',
  yearsExperience: '',
  budgetRange: '',
  preferredLocationsList: [],
  propertyInterestList: [],
  investmentGoal: '',
  hasWealthAdvisor: 'no'
})

function friendlyRegistrationError(text) {
  const raw = String(text || '').toLowerCase()
  if (raw.includes('data already created') || raw.includes('already created')) {
    return 'Email already used. Please sign in or use a different email.'
  }
  return text || 'Registration failed.'
}

function splitName(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return { firstName: '', lastName: '' }
  if (parts.length === 1) return { firstName: parts[0], lastName: parts[0] }
  return {
    firstName: parts[0],
    lastName: parts.slice(1).join(' ')
  }
}

async function submitUserRegistration() {
  const { firstName, lastName } = splitName(form.fullName)
  const payload = new FormData()
  payload.append('fname', firstName)
  payload.append('lname', lastName)
  payload.append('email', form.email)
  payload.append('password', form.password)
  payload.append('phoneno', '')
  payload.append('investment_budget_range', form.budgetRange)
  payload.append('preferred_locations', form.preferredLocationsList.join(', '))
  payload.append('property_interest', form.propertyInterestList.join(', '))
  payload.append('investment_goal', form.investmentGoal)
  payload.append('private_wealth_advisor', form.hasWealthAdvisor)

  const res = await api.post('/api/users/Auth/register.php', payload)
  if (res.data?.status) {
    const token = res.data?.data?.[0]?.access_token
    if (token) {
      localStorage.setItem('AUTH_TOKEN', token)
      localStorage.setItem('USER_ROLE', 'user')
    }
    await router.push('/verify-account?role=user')
    return
  }
  throw new Error(friendlyRegistrationError(res.data?.text))
}

async function submitAgentRegistration() {
  const payload = new FormData()
  payload.append('full_name', form.fullName)
  payload.append('agency_name', form.agencyName)
  payload.append('email', form.email)
  payload.append('password', form.password)
  payload.append('phoneno', form.phone)
  payload.append('business_address', form.officeLocation)
  payload.append('license_number', form.licenseNumber)
  payload.append('years_experience', form.yearsExperience)

  const res = await api.post('/api/agents/Auth/register.php', payload)
  if (res.data?.status) {
    const token = res.data?.data?.[0]?.access_token
    if (token) {
      localStorage.setItem('AUTH_TOKEN', token)
      localStorage.setItem('USER_ROLE', 'agent')
    }
    await router.push('/verify-account?role=agent')
    return
  }
  throw new Error(friendlyRegistrationError(res.data?.text))
}

async function submitForm() {
  error.value = ''
  if (!form.fullName || !form.email || !form.password || !form.confirmPassword) {
    error.value = 'Complete all account fields.'
    return
  }
  if (form.password !== form.confirmPassword) {
    error.value = 'Passwords do not match.'
    return
  }
  if (role.value === 'agent') {
    if (!form.phone || !form.agencyName || !form.licenseNumber || !form.officeLocation || !form.yearsExperience) {
      error.value = 'Complete all role-specific fields.'
      return
    }
  } else if (
    !form.budgetRange
    || !form.preferredLocationsList.length
    || !form.propertyInterestList.length
    || !form.investmentGoal
  ) {
    error.value = 'Complete all role-specific fields.'
    return
  }

  loading.value = true
  try {
    if (role.value === 'agent') {
      await submitAgentRegistration()
    } else {
      await submitUserRegistration()
    }
  } catch (err) {
    error.value = friendlyRegistrationError(err?.response?.data?.text || err?.message)
  } finally {
    loading.value = false
  }
}
</script>
