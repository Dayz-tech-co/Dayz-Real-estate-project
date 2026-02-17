<template>
  <div class="min-h-screen text-[#1a2e1a] relative flex flex-col overflow-x-hidden">
    <header class="w-full flex justify-between border-b border-emerald-900/10 bg-white/80 backdrop-blur-md sticky top-0 z-50 px-6 py-4 lg:px-10">
      <div class="flex items-center gap-3">
        <img
          src="/images/DayzLogo.svg"
          alt="Dayz"
          class="h-20 md:h-24 w-auto object-contain bg-black/60 p-2 rounded-sm ring-1 ring-white/10"
        />
      </div>
      <div class="flex flex-1 justify-end gap-10 items-center">
        <nav class="hidden md:flex items-center gap-10">
          <a class="text-emerald-900/70 text-sm font-semibold hover:text-primary transition-colors uppercase tracking-wider" href="#">Properties</a>
          <a class="text-emerald-900/70 text-sm font-semibold hover:text-primary transition-colors uppercase tracking-wider" href="#">Our Agency</a>
          <a class="text-emerald-900/70 text-sm font-semibold hover:text-primary transition-colors uppercase tracking-wider" href="#">Insights</a>
        </nav>
        <button class="flex min-w-[120px] items-center justify-center rounded bg-primary text-white text-xs font-bold uppercase tracking-widest h-10 px-6 hover:bg-emerald-900/90 transition-all shadow-md active:translate-y-px">
          Sign In
        </button>
      </div>
    </header>

    <main class="flex flex-1 justify-center py-16 px-4 w-full">
      <div class="layout-content-container flex flex-col max-w-[1000px] flex-1">
        <div class="text-center mb-12">
          <span class="text-gold-accent font-bold text-xs uppercase tracking-[0.3em] mb-4 block">Exclusive Membership</span>
          <h1 class="text-emerald-900 text-5xl font-display font-bold leading-tight mb-4">Partner with Excellence</h1>
          <p class="text-emerald-800/60 text-lg max-w-2xl mx-auto">
            Elevate your real estate practice with Dayz. Access our premium network of high-net-worth clients and bespoke marketing tools.
          </p>
        </div>

        <div class="premium-card rounded-sm shadow-2xl overflow-hidden">
          <div class="p-10">
            <form @submit.prevent="submitForm" class="space-y-12">
              <section>
                <div class="flex items-center gap-4 mb-8">
                  <div class="h-px bg-emerald-900/10 flex-grow"></div>
                  <h2 class="text-emerald-900 text-xs font-bold uppercase tracking-[0.2em] whitespace-nowrap">Agency Profile</h2>
                  <div class="h-px bg-emerald-900/10 flex-grow"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                  <div class="md:col-span-2">
                    <label class="block">
                      <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Legal Agency Name</span>
                      <input v-model="form.legalName" class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 text-emerald-950 placeholder-emerald-900/30 transition-all focus:ring-0 focus:bg-white" placeholder="e.g. Prestige International Realty" required type="text" />
                    </label>
                  </div>
                  <label class="block">
                    <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Professional Email</span>
                    <input v-model="form.email" class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 text-emerald-950 placeholder-emerald-900/30 transition-all focus:ring-0 focus:bg-white" placeholder="director@agency.com" required type="email" />
                  </label>
                  <label class="block">
                    <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Direct Phone Line</span>
                    <input v-model="form.phone" class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 text-emerald-950 placeholder-emerald-900/30 transition-all focus:ring-0 focus:bg-white" placeholder="+1 (212) 555-0198" required type="tel" />
                  </label>
                  <label class="block">
                    <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Account Password</span>
                    <div class="relative">
                      <input
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 pr-12 text-emerald-950 placeholder-emerald-900/30 transition-all focus:ring-0 focus:bg-white"
                        placeholder="********"
                        required
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
                        class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 pr-12 text-emerald-950 placeholder-emerald-900/30 transition-all focus:ring-0 focus:bg-white"
                        placeholder="********"
                        required
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
                </div>
              </section>

              <section>
                <div class="flex items-center gap-4 mb-8">
                  <div class="h-px bg-emerald-900/10 flex-grow"></div>
                  <h2 class="text-emerald-900 text-xs font-bold uppercase tracking-[0.2em] whitespace-nowrap">Business Headquarters</h2>
                  <div class="h-px bg-emerald-900/10 flex-grow"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-6 gap-x-6 gap-y-6">
                  <div class="md:col-span-4">
                    <label class="block">
                      <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Street Address</span>
                      <input v-model="form.street" class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 text-emerald-950 placeholder-emerald-900/30 transition-all focus:ring-0 focus:bg-white" placeholder="Fifth Avenue, Manhattan" required type="text" />
                    </label>
                  </div>
                  <div class="md:col-span-2">
                    <label class="block">
                      <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Suite / Floor</span>
                      <input v-model="form.suite" class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 text-emerald-950 placeholder-emerald-900/30 transition-all focus:ring-0 focus:bg-white" placeholder="Suite 40A" type="text" />
                    </label>
                  </div>
                  <div class="md:col-span-2">
                    <label class="block">
                      <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">City</span>
                      <input v-model="form.city" class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 text-emerald-950 placeholder-emerald-900/30 transition-all focus:ring-0 focus:bg-white" placeholder="New York" required type="text" />
                    </label>
                  </div>
                  <div class="md:col-span-2">
                    <label class="block">
                      <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">State / Province</span>
                      <input
                        v-model="form.state"
                        :list="stateListId"
                        class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 text-emerald-950 placeholder-emerald-900/30 transition-all focus:ring-0 focus:bg-white"
                        :placeholder="stateOptions.length ? 'Choose state' : 'Enter state/province'"
                        required
                        type="text"
                      />
                      <datalist :id="stateListId">
                        <option v-for="state in stateOptions" :key="state" :value="state" />
                      </datalist>
                    </label>
                  </div>
                  <div class="md:col-span-2">
                    <label class="block">
                      <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Postal Code</span>
                      <input v-model="form.postalCode" class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 text-emerald-950 placeholder-emerald-900/30 transition-all focus:ring-0 focus:bg-white" placeholder="10001" required type="text" />
                    </label>
                  </div>
                  <div class="md:col-span-6">
                    <label class="block">
                      <span class="block text-emerald-900 text-[10px] font-bold uppercase tracking-widest mb-2">Country</span>
                      <input
                        v-model="form.country"
                        list="agent-countries"
                        class="form-input block w-full border-emerald-900/10 bg-emerald-50/30 h-12 px-4 text-emerald-950 transition-all focus:ring-0 focus:bg-white"
                        placeholder="Search country"
                        type="text"
                      />
                      <datalist id="agent-countries">
                        <option v-for="country in countries" :key="country" :value="country" />
                      </datalist>
                    </label>
                  </div>
                </div>
              </section>

              <div class="pt-6 border-t border-emerald-900/10">
                <label class="flex items-start gap-4 cursor-pointer group mb-10">
                  <div class="relative flex items-center mt-1">
                    <input v-model="form.agree" type="checkbox" required class="size-5 border-emerald-900/20 text-primary rounded-sm focus:ring-0 focus:ring-offset-0 cursor-pointer" />
                  </div>
                  <span class="text-sm text-emerald-800/70 leading-relaxed group-hover:text-emerald-900 transition-colors">
                    By checking this box, I acknowledge that Dayz is an exclusive platform. I certify that our agency maintains a valid real estate license and agree to the
                    <a href="#" class="text-primary font-bold decoration-gold-accent underline underline-offset-4">Terms of Membership</a> and
                    <a href="#" class="text-primary font-bold decoration-gold-accent underline underline-offset-4">Data Protection Policy</a>.
                  </span>
                </label>

                <div v-if="error" class="text-sm text-red-600 mb-4">{{ error }}</div>

                <button :disabled="loading" type="submit" class="w-full flex items-center justify-center emerald-gradient-bg text-white h-16 text-sm font-bold uppercase tracking-[0.3em] hover:brightness-110 shadow-xl transition-all active:scale-[0.99] disabled:opacity-60">
                  {{ loading ? 'Submitting...' : 'Submit Membership Application' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>
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
  legalName: '',
  email: '',
  phone: '',
  password: '',
  confirmPassword: '',
  street: '',
  suite: '',
  city: '',
  state: '',
  postalCode: '',
  country: 'Nigeria',
  agree: false
})

const stateListId = 'agent-states-list'
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
    payload.append('agency_name', form.legalName)
    payload.append('email', form.email)
    payload.append('password', form.password)
    payload.append('phoneno', form.phone)

    const streetParts = [form.street, form.suite, form.city, form.state, form.postalCode]
      .filter(Boolean)
      .join(', ')

    payload.append('business_address', streetParts)
    payload.append('city', form.city)
    payload.append('state', form.state)
    payload.append('postal_code', form.postalCode)
    payload.append('streetname', form.street)
    payload.append('country', form.country)

    const res = await api.post('/api/agents/Auth/register.php', payload)
    if (res.data?.status) {
      const token = res.data?.data?.[0]?.access_token
      if (token) {
        localStorage.setItem('AUTH_TOKEN', token)
        localStorage.setItem('USER_ROLE', 'agent')
      }
      await router.push('/login?role=agent')
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
