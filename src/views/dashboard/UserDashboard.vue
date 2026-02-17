<template>
  <div class="min-h-screen bg-theme text-white">
    <section class="layout-content-container px-6 py-12">
      <div class="grid gap-10 lg:grid-cols-[320px,1fr] items-start">
        <aside class="bg-emerald-950/80 border border-white/10 p-6 space-y-6">
          <div class="bg-black/40 border border-white/10 rounded-sm p-4">
            <div class="flex items-center gap-3">
              <img src="/images/DayzLogo.svg" alt="Dayz" class="h-10 w-auto object-contain" />
            </div>
            <div class="mt-4">
              <p class="text-[10px] uppercase tracking-widest text-white/60">Premium Member</p>
              <h2 class="font-display text-xl text-white">{{ dashboard.user_info.fullname || 'User' }}</h2>
              <p class="text-xs text-white/50 break-words mt-1">{{ dashboard.user_info.email || '' }}</p>
            </div>
          </div>

          <nav class="space-y-3 text-sm">
            <button
              type="button"
              class="block w-full text-left px-3 py-2 rounded-sm"
              :class="activeTab === 'overview' ? 'bg-white/10' : 'hover:bg-white/10'"
              @click="setTab('overview')"
            >
              Overview
            </button>
            <button
              type="button"
              class="block w-full text-left px-3 py-2 rounded-sm"
              :class="activeTab === 'saved' ? 'bg-white/10' : 'hover:bg-white/10'"
              @click="setTab('saved')"
            >
              Saved Properties
            </button>
            <button
              type="button"
              class="block w-full text-left px-3 py-2 rounded-sm"
              :class="activeTab === 'visits' ? 'bg-white/10' : 'hover:bg-white/10'"
              @click="setTab('visits')"
            >
              Recent Visits
            </button>
            <button
              type="button"
              class="block w-full text-left px-3 py-2 rounded-sm"
              :class="activeTab === 'kyc' ? 'bg-white/10' : 'hover:bg-white/10'"
              @click="setTab('kyc')"
            >
              KYC Status
            </button>
          </nav>

          <button
            type="button"
            class="border border-white/10 rounded-sm p-4 bg-emerald-950/90 w-full text-left hover:bg-emerald-900/70 transition-colors"
            @click="openKycTab"
          >
            <div class="flex items-center gap-2 mb-3 text-white/70">
              <span class="text-xs uppercase tracking-widest">Compliance</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-white">KYC Status</span>
              <span class="text-xs uppercase tracking-widest text-white/70">
                {{ dashboard.kyc_status.status || 'pending' }}
              </span>
            </div>
            <p class="text-xs text-white/50 mt-3 break-words">{{ dashboard.user_info.email || '' }}</p>
          </button>
        </aside>

        <section class="space-y-8">
          <div class="rounded-lg border border-white/10 bg-emerald-950/60 p-6">
            <div class="flex items-center gap-2 text-white/70 text-xs uppercase tracking-widest mb-4">
              <span>Compliance</span>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <button
                type="button"
                class="border border-white/10 rounded-sm p-4 bg-emerald-950/80 text-left hover:bg-emerald-900/70 transition-colors"
                @click="openKycTab"
              >
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-xs uppercase tracking-widest text-white/60">Government Issued ID</p>
                    <p class="text-sm text-white/90 mt-1">
                      {{ kycVerifiedLabel }}
                    </p>
                  </div>
                  <span class="text-xs uppercase tracking-widest text-white/60">
                    {{ kycVerifiedLabel }}
                  </span>
                </div>
              </button>
              <button
                type="button"
                class="border border-white/10 rounded-sm p-4 bg-emerald-950/80 text-left hover:bg-emerald-900/70 transition-colors"
                @click="openKycTab"
              >
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-xs uppercase tracking-widest text-white/60">Proof of Residence</p>
                    <p class="text-sm text-white/90 mt-1">{{ proofStatusLabel }}</p>
                  </div>
                  <span class="text-xs uppercase tracking-widest text-white/60">
                    {{ proofStatusLabel }}
                  </span>
                </div>
              </button>
            </div>
          </div>

          <div class="bg-emerald-950/90 border border-white/10 px-6 py-6 rounded-lg">
            <h1 class="font-display text-3xl text-white">Welcome back, {{ firstName || 'friend' }}</h1>
            <p class="text-white/80 mt-2">{{ subtitle }}</p>
          </div>

          <div v-if="activeTab === 'overview'" class="space-y-8">
            <div class="grid gap-6 md:grid-cols-3">
              <div class="bg-emerald-950/80 border border-white/10 p-5">
                <p class="text-xs uppercase tracking-widest text-white/60">Saved</p>
                <h3 class="text-2xl font-display text-white">{{ dashboard.stats.wishlist_count }} Listings</h3>
              </div>
              <div class="bg-emerald-950/80 border border-white/10 p-5">
                <p class="text-xs uppercase tracking-widest text-white/60">Visits</p>
                <h3 class="text-2xl font-display text-white">{{ dashboard.stats.booking_count }} Scheduled</h3>
              </div>
              <div class="bg-emerald-950/80 border border-white/10 p-5">
                <p class="text-xs uppercase tracking-widest text-white/60">KYC</p>
                <h3 class="text-2xl font-display text-white">{{ dashboard.kyc_status.status || 'pending' }}</h3>
              </div>
            </div>

            <div class="bg-white text-emerald-950 rounded-lg border border-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-black/10">
                <h3 class="font-display text-2xl">Recent Activity</h3>
              </div>
              <div class="divide-y divide-black/5">
                <div v-if="loading" class="px-6 py-6 text-sm text-emerald-900/60">
                  Loading activity...
                </div>
                <div v-else-if="error" class="px-6 py-6 text-sm text-red-600">
                  {{ error }}
                </div>
                <div v-else-if="recentActivity.length === 0" class="px-6 py-6 text-sm text-emerald-900/60">
                  No recent activity yet.
                </div>
                <div
                  v-else
                  v-for="item in recentActivity"
                  :key="item.key"
                  class="px-6 py-4 flex items-center justify-between"
                >
                  <div>
                    <p class="font-semibold">{{ item.title }}</p>
                    <p class="text-sm text-emerald-900/60">{{ item.meta }}</p>
                  </div>
                  <span v-if="item.amount" class="text-sm font-semibold">₦ {{ item.amount }}</span>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="activeTab === 'saved'" class="space-y-6">
            <div class="bg-white text-emerald-950 rounded-lg border border-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-black/10">
                <h3 class="font-display text-2xl">Saved Properties</h3>
              </div>
              <div class="divide-y divide-black/5">
                <div v-if="tabLoading.saved" class="px-6 py-6 text-sm text-emerald-900/60">Loading saved properties...</div>
                <div v-else-if="tabError.saved" class="px-6 py-6 text-sm text-red-600">{{ tabError.saved }}</div>
                <div v-else-if="wishlist.length === 0" class="px-6 py-6 text-sm text-emerald-900/60">No saved properties yet.</div>
                <button
                  v-else
                  v-for="item in wishlist"
                  :key="item.id"
                  type="button"
                  class="w-full px-6 py-4 flex items-center justify-between gap-4 text-left hover:bg-emerald-50/60 transition-colors"
                  @click="openSavedProperty(item)"
                >
                  <div class="flex items-center gap-4">
                    <div class="h-14 w-20 rounded-sm overflow-hidden bg-emerald-950/10 border border-black/5">
                      <img :src="wishlistImage(item)" :alt="item.title || 'Saved property'" class="h-full w-full object-cover" />
                    </div>
                    <div>
                      <p class="font-semibold">{{ item.title || 'Saved property' }}</p>
                      <p class="text-sm text-emerald-900/60">{{ [item.city, item.state].filter(Boolean).join(', ') }}</p>
                    </div>
                  </div>
                  <span class="text-sm font-semibold">{{ formatMoney(item.price) }}</span>
                </button>
              </div>
            </div>
          </div>

          <div v-else-if="activeTab === 'visits'" class="space-y-6">
            <div class="bg-white text-emerald-950 rounded-lg border border-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-black/10">
                <h3 class="font-display text-2xl">Recent Visits</h3>
              </div>
              <div class="divide-y divide-black/5">
                <div v-if="tabLoading.visits" class="px-6 py-6 text-sm text-emerald-900/60">Loading visits...</div>
                <div v-else-if="tabError.visits" class="px-6 py-6 text-sm text-red-600">{{ tabError.visits }}</div>
                <div v-else-if="bookings.length === 0" class="px-6 py-6 text-sm text-emerald-900/60">No visits yet.</div>
                <div v-else v-for="item in bookings" :key="item.booking_id" class="px-6 py-4 flex items-center justify-between">
                  <div>
                    <p class="font-semibold">{{ item.property_title || 'Visit' }}</p>
                    <p class="text-sm text-emerald-900/60">{{ item.booking_status || 'scheduled' }} · {{ formatDate(item.visit_date || item.created_at) }}</p>
                  </div>
                  <span class="text-sm font-semibold">{{ formatMoney(item.price) }}</span>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="activeTab === 'kyc'" ref="kycSectionRef" class="space-y-6">
            <div class="bg-white text-emerald-950 rounded-lg border border-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-black/10">
                <h3 class="font-display text-2xl">KYC Status</h3>
              </div>
              <div class="px-6 py-6 space-y-4">
                <div v-if="tabLoading.kyc && !dashboard.kyc_status.status" class="text-sm text-emerald-900/60">
                  Loading KYC status...
                </div>
                <div v-else-if="!kycDetails && !dashboard.kyc_status.status" class="text-sm text-emerald-900/60">
                  No KYC record found.
                </div>
                <div v-else-if="tabError.kyc && !kycDetails && !dashboard.kyc_status.status" class="text-sm text-red-600">
                  {{ tabError.kyc }}
                </div>
                <div v-else class="space-y-3">
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-emerald-900">Status</span>
                    <span class="text-xs uppercase tracking-widest text-emerald-900/70">
                      {{ kycDetails?.status || dashboard.kyc_status.status || 'pending' }}
                    </span>
                  </div>
                  <p v-if="kycDetails?.summary" class="text-sm text-emerald-900/80">{{ kycDetails.summary }}</p>
                  <p v-if="proofStatusLabel !== 'Approved'" class="text-sm text-emerald-900/70">
                    Proof of residence: {{ proofStatusLabel }}
                  </p>
                  <div v-if="kycDetails?.admin_comment" class="text-sm text-emerald-900/70">
                    Admin comment: {{ kycDetails?.admin_comment }}
                  </div>
                  <div class="grid gap-2 text-sm text-emerald-900/70">
                    <div>Document: {{ kycDetails?.government_id_type || '-' }} ({{ kycDetails?.government_id_number || '-' }})</div>
                    <div>Address: {{ [kycDetails?.address, kycDetails?.city, kycDetails?.state, kycDetails?.country].filter(Boolean).join(', ') || '-' }}</div>
                    <div>Submitted: {{ formatDate(kycDetails?.created_at) || '-' }}</div>
                    <div>Proof of residence: {{ proofStatusLabel }}</div>
                    <div v-if="kycDetails?.proof_of_address_admin_comment" class="text-emerald-900/70">
                      Proof comment: {{ kycDetails?.proof_of_address_admin_comment }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-white text-emerald-950 rounded-lg border border-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-black/10">
                <h3 class="font-display text-2xl">Submit Identity Documents</h3>
              </div>
              <form class="px-6 py-6 space-y-5" @submit.prevent="submitKycDocuments">
                <div v-if="isKycApproved" class="text-sm text-emerald-900/70">
                  Your identity verification is already approved. You cannot resubmit ID documents.
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                  <label class="space-y-2 text-sm">
                    <span class="text-emerald-900">ID Type</span>
                    <select v-model="kycForm.id_type" class="form-select w-full border-emerald-900/10 bg-emerald-50/40 h-11 px-3">
                      <option disabled value="">Select ID type</option>
                      <option value="NIN">NIN</option>
                      <option value="Driver's License">Driver's License</option>
                      <option value="International Passport">International Passport</option>
                      <option value="Voter's Card">Voter's Card</option>
                      <option value="National ID Card">National ID Card</option>
                    </select>
                  </label>
                  <label class="space-y-2 text-sm">
                    <span class="text-emerald-900">ID Number</span>
                    <input v-model="kycForm.id_number" type="text" class="form-input w-full border-emerald-900/10 bg-emerald-50/40 h-11 px-3" />
                  </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                  <label class="space-y-2 text-sm">
                    <span class="text-emerald-900">Address</span>
                    <input v-model="kycForm.address" type="text" class="form-input w-full border-emerald-900/10 bg-emerald-50/40 h-11 px-3" />
                  </label>
                  <label class="space-y-2 text-sm">
                    <span class="text-emerald-900">City</span>
                    <input v-model="kycForm.city" type="text" class="form-input w-full border-emerald-900/10 bg-emerald-50/40 h-11 px-3" />
                  </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                  <label class="space-y-2 text-sm">
                    <span class="text-emerald-900">State</span>
                    <input v-model="kycForm.state" type="text" class="form-input w-full border-emerald-900/10 bg-emerald-50/40 h-11 px-3" />
                  </label>
                  <label class="space-y-2 text-sm">
                    <span class="text-emerald-900">Country</span>
                    <input v-model="kycForm.country" type="text" class="form-input w-full border-emerald-900/10 bg-emerald-50/40 h-11 px-3" />
                  </label>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                  <label class="space-y-2 text-sm">
                    <span class="text-emerald-900">ID Front (Required)</span>
                    <input type="file" accept="image/*" class="block w-full text-sm" @change="(e) => handleFileChange('id_front', e)" />
                  </label>
                  <label class="space-y-2 text-sm">
                    <span class="text-emerald-900">ID Back (Required)</span>
                    <input type="file" accept="image/*" class="block w-full text-sm" @change="(e) => handleFileChange('id_back', e)" />
                  </label>
                  <label class="space-y-2 text-sm">
                    <span class="text-emerald-900">Proof of Residence (Optional)</span>
                    <input type="file" accept="image/*" class="block w-full text-sm" @change="(e) => handleFileChange('proof_of_address', e)" />
                  </label>
                </div>

                <p class="text-xs text-emerald-900/60">Max file size: 25MB per file. We upload over HTTPS using your JWT.</p>

                <div v-if="kycSubmitError" class="text-sm text-red-600">{{ kycSubmitError }}</div>
                <div v-if="kycSubmitSuccess" class="text-sm text-emerald-700">{{ kycSubmitSuccess }}</div>

                <button
                  type="submit"
                  class="emerald-gradient-bg text-white h-12 px-6 text-xs font-bold uppercase tracking-[0.3em] hover:brightness-110 shadow-lg transition-all disabled:opacity-60"
                  :disabled="kycSubmitting || isKycApproved"
                >
                  {{ kycSubmitting ? 'Submitting...' : 'Submit ID Documents' }}
                </button>
              </form>
            </div>

            <div class="bg-white text-emerald-950 rounded-lg border border-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-black/10">
                <h3 class="font-display text-2xl">Submit Proof of Residence</h3>
              </div>
              <form class="px-6 py-6 space-y-4" @submit.prevent="submitProofOfResidence">
                <div v-if="hasProofOfAddress" class="text-sm text-emerald-900/70">
                  Proof of residence already submitted.
                </div>
                <label class="space-y-2 text-sm block">
                  <span class="text-emerald-900">Proof of Residence File</span>
                  <input type="file" accept="image/*" class="block w-full text-sm" @change="handleProofFileChange" :disabled="hasProofOfAddress" />
                </label>
                <p class="text-xs text-emerald-900/60">Max file size: 25MB. Only images are supported.</p>

                <div v-if="proofSubmitError" class="text-sm text-red-600">{{ proofSubmitError }}</div>
                <div v-if="proofSubmitSuccess" class="text-sm text-emerald-700">{{ proofSubmitSuccess }}</div>

                <button
                  type="submit"
                  class="emerald-gradient-bg text-white h-12 px-6 text-xs font-bold uppercase tracking-[0.3em] hover:brightness-110 shadow-lg transition-all disabled:opacity-60"
                  :disabled="proofSubmitting || hasProofOfAddress"
                >
                  {{ proofSubmitting ? 'Submitting...' : 'Submit Proof' }}
                </button>
              </form>
            </div>
          </div>
        </section>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'

const loading = ref(false)
const error = ref('')
const activeTab = ref('overview')
const router = useRouter()

const tabLoading = reactive({
  saved: false,
  visits: false,
  kyc: false
})

const tabError = reactive({
  saved: '',
  visits: '',
  kyc: ''
})

const tabLoaded = reactive({
  saved: false,
  visits: false,
  kyc: false
})

const wishlist = ref([])
const bookings = ref([])
const kycDetails = ref(null)
const kycForm = reactive({
  id_type: '',
  id_number: '',
  address: '',
  city: '',
  state: '',
  country: '',
  id_front: null,
  id_back: null,
  proof_of_address: null
})
const kycSubmitting = ref(false)
const kycSubmitError = ref('')
const kycSubmitSuccess = ref('')
const proofFile = ref(null)
const proofSubmitting = ref(false)
const proofSubmitError = ref('')
const proofSubmitSuccess = ref('')
const kycSectionRef = ref(null)
const dashboard = reactive({
  user_info: {},
  stats: {
    wishlist_count: 0,
    booking_count: 0,
    transaction_count: 0,
    property_views: 0,
    unread_notifications: 0
  },
  kyc_status: {},
  recent_activity: {
    bookings: [],
    transactions: [],
    wishlist: []
  }
})

const firstName = computed(() => {
  const name = dashboard.user_info.fullname || ''
  return name.split(' ')[0] || ''
})

const subtitle = computed(() => {
  const city = dashboard.user_info.city || ''
  const state = dashboard.user_info.state || ''
  const parts = [city, state].filter(Boolean)
  const uniqueParts = parts.filter((item, index) => parts.indexOf(item) === index)
  if (uniqueParts.length > 0) {
    return `Your ${uniqueParts.join(', ')} shortlist is ready for review.`
  }
  return 'Your shortlisted properties are ready for review.'
})

const isKycApproved = computed(() => {
  const status = (kycDetails.value?.status || '').toLowerCase()
  return status === 'approved'
})

const hasProofOfAddress = computed(() => {
  return Boolean(kycDetails.value?.proof_of_address || kycDetails.value?.proof_of_address_document)
})

const proofStatusLabel = computed(() => {
  const status = (kycDetails.value?.proof_of_address_status || '').toLowerCase()
  if (!hasProofOfAddress.value) return 'Not submitted'
  if (status === 'approved') return 'Approved'
  if (status === 'rejected') return 'Rejected'
  return 'Pending approval'
})

const kycVerifiedLabel = computed(() => {
  const backendStatus = (dashboard.kyc_status.status || '').toLowerCase()
  const detailStatus = (kycDetails.value?.status || '').toLowerCase()
  const verifiedFlag = kycDetails.value?.verified

  if (backendStatus === 'verified' || verifiedFlag === 1 || detailStatus === 'approved') {
    return 'Verified'
  }
  if (detailStatus) return detailStatus.charAt(0).toUpperCase() + detailStatus.slice(1)
  if (backendStatus) return backendStatus.charAt(0).toUpperCase() + backendStatus.slice(1)
  return 'Pending'
})

const recentActivity = computed(() => {
  const items = []
  dashboard.recent_activity.wishlist.forEach((w, index) => {
    items.push({
      key: `wish-${index}-${w.property_id}`,
      title: w.title || 'Saved property',
      meta: `Saved · ${[w.city, w.state].filter(Boolean).join(', ')}`,
      amount: w.price ? Number(w.price).toLocaleString() : ''
    })
  })
  dashboard.recent_activity.bookings.forEach((b, index) => {
    items.push({
      key: `book-${index}-${b.booking_id}`,
      title: b.title || 'Booking',
      meta: `Visit scheduled · ${[b.city, b.state].filter(Boolean).join(', ')}`,
      amount: b.price ? Number(b.price).toLocaleString() : ''
    })
  })
  dashboard.recent_activity.transactions.forEach((t, index) => {
    items.push({
      key: `txn-${index}-${t.transaction_id}`,
      title: t.title || 'Transaction',
      meta: `${t.transaction_type || 'Payment'} · ${t.agency_name || ''}`.trim(),
      amount: t.amount ? Number(t.amount).toLocaleString() : ''
    })
  })
  return items.slice(0, 6)
})

function formatMoney(value) {
  if (value === null || value === undefined || value === '') return '₦ 0'
  const amount = Number(value)
  if (Number.isNaN(amount)) return '₦ 0'
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    maximumFractionDigits: 0
  }).format(amount)
}

function normalizeImage(path) {
  if (!path) return '/uploads/properties/1761862624_DJI_0253-2-scaled.webp'
  if (path.startsWith('http')) return path
  if (path.startsWith('/')) return path
  return `/${path}`
}

function wishlistImage(item) {
  if (!item) return normalizeImage('')
  if (Array.isArray(item.images)) {
    return normalizeImage(item.thumbnail || item.images[0])
  }
  if (typeof item.images === 'string') {
    try {
      const parsed = JSON.parse(item.images)
      if (Array.isArray(parsed)) return normalizeImage(item.thumbnail || parsed[0])
    } catch (err) {
      // fall through
    }
  }
  return normalizeImage(item.thumbnail)
}
function formatDate(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function validateFile(file) {
  const maxSize = 25 * 1024 * 1024
  if (!file) return 'File is required.'
  if (file.size > maxSize) return 'File exceeds 25MB limit.'
  return ''
}

function handleFileChange(field, event) {
  const file = event.target.files?.[0]
  if (!file) {
    kycForm[field] = null
    return
  }
  const errorMsg = validateFile(file)
  if (errorMsg) {
    kycSubmitError.value = errorMsg
    event.target.value = ''
    kycForm[field] = null
    return
  }
  kycForm[field] = file
}

function handleProofFileChange(event) {
  const file = event.target.files?.[0]
  if (!file) {
    proofFile.value = null
    return
  }
  const errorMsg = validateFile(file)
  if (errorMsg) {
    proofSubmitError.value = errorMsg
    event.target.value = ''
    proofFile.value = null
    return
  }
  proofFile.value = file
}

async function submitKycDocuments() {
  kycSubmitError.value = ''
  kycSubmitSuccess.value = ''

  if (!kycForm.id_type || !kycForm.id_number || !kycForm.address || !kycForm.city || !kycForm.state || !kycForm.country) {
    kycSubmitError.value = 'Please complete all required fields.'
    return
  }

  const requiredFiles = ['id_front', 'id_back']
  for (const key of requiredFiles) {
    const msg = validateFile(kycForm[key])
    if (msg) {
      kycSubmitError.value = `${key.replace('_', ' ')}: ${msg}`
      return
    }
  }

  const payload = new FormData()
  payload.append('id_type', kycForm.id_type)
  payload.append('id_number', kycForm.id_number)
  payload.append('address', kycForm.address)
  payload.append('city', kycForm.city)
  payload.append('state', kycForm.state)
  payload.append('country', kycForm.country)
  payload.append('submission_type', 'documents')
  payload.append('id_front', kycForm.id_front)
  payload.append('id_back', kycForm.id_back)
  if (kycForm.proof_of_address) {
    payload.append('proof_of_address', kycForm.proof_of_address)
  }

  kycSubmitting.value = true
  try {
    const res = await api.post('/api/users/KYC/kyc_submission.php', payload)
    if (res.data?.status) {
      kycSubmitSuccess.value = res.data?.text || 'KYC submitted successfully.'
      tabLoaded.kyc = false
      await loadTabData('kyc')
    } else {
      kycSubmitError.value = res.data?.text || 'KYC submission failed.'
    }
  } catch (err) {
    kycSubmitError.value = err?.response?.data?.text || 'KYC submission failed.'
  } finally {
    kycSubmitting.value = false
  }
}

async function submitProofOfResidence() {
  proofSubmitError.value = ''
  proofSubmitSuccess.value = ''

  const msg = validateFile(proofFile.value)
  if (msg) {
    proofSubmitError.value = msg
    return
  }

  const payload = new FormData()
  payload.append('submission_type', 'proof')
  payload.append('proof_of_address', proofFile.value)

  proofSubmitting.value = true
  try {
    const res = await api.post('/api/users/KYC/kyc_submission.php', payload)
    if (res.data?.status) {
      proofSubmitSuccess.value = res.data?.text || 'Proof submitted successfully.'
      tabLoaded.kyc = false
      await loadTabData('kyc')
    } else {
      proofSubmitError.value = res.data?.text || 'Proof submission failed.'
    }
  } catch (err) {
    proofSubmitError.value = err?.response?.data?.text || 'Proof submission failed.'
  } finally {
    proofSubmitting.value = false
  }
}
function setTab(tab) {
  activeTab.value = tab
  if (tab !== 'overview') loadTabData(tab)
}

function openSavedProperty(item) {
  const propertyId = Number(item?.id || item?.property_id)
  if (!propertyId) return
  router.push(`/property/${propertyId}`)
}

function openKycTab() {
  setTab('kyc')
  nextTick(() => {
    if (kycSectionRef.value?.scrollIntoView) {
      kycSectionRef.value.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  })
}

async function loadTabData(tab) {
  if (tabLoaded[tab]) return
  tabLoading[tab] = true
  tabError[tab] = ''

  try {
    if (tab === 'saved') {
      const res = await api.post('/api/users/Properties/wishlist_get.php')
      if (res.data?.status) {
        wishlist.value = res.data?.data || []
        tabLoaded.saved = true
      } else {
        wishlist.value = []
        tabError.saved = res.data?.text || 'Unable to load saved properties.'
      }
    }

    if (tab === 'visits') {
      const res = await api.post('/api/users/Bookings/my_bookings.php')
      if (res.data?.status) {
        bookings.value = res.data?.data || []
        tabLoaded.visits = true
      } else {
        bookings.value = []
        tabError.visits = res.data?.text || 'Unable to load bookings.'
      }
    }

    if (tab === 'kyc') {
      const res = await api.post('/api/users/KYC/view_kyc_status.php')
      if (res.data?.status) {
        kycDetails.value = res.data?.data || null
        tabLoaded.kyc = true
      } else {
        kycDetails.value = null
        tabError.kyc = res.data?.text || 'Unable to load KYC status.'
      }
    }
  } catch (err) {
    const message = err?.response?.data?.text || 'Unable to load data.'
    if (tab === 'saved') tabError.saved = message
    if (tab === 'visits') tabError.visits = message
    if (tab === 'kyc') tabError.kyc = message
  } finally {
    tabLoading[tab] = false
  }
}

async function loadDashboard() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.post('/api/users/Dashboard/index.php')
    if (res.data?.status) {
      Object.assign(dashboard, res.data?.data || {})
    } else {
      error.value = res.data?.text || 'Unable to load dashboard.'
    }
  } catch (err) {
    error.value = err?.response?.data?.text || 'Unable to load dashboard.'
  } finally {
    loading.value = false
  }
}

onMounted(loadDashboard)
</script>
