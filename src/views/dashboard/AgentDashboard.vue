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
              <p class="text-[10px] uppercase tracking-widest text-white/60">Agency Partner</p>
              <h2 class="font-display text-xl text-white">{{ dashboard.agent_info.agency_name || 'Agency' }}</h2>
              <p class="text-xs text-white/50 break-words mt-1">{{ dashboard.agent_info.email || '' }}</p>
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
              :class="activeTab === 'listings' ? 'bg-white/10' : 'hover:bg-white/10'"
              @click="setTab('listings')"
            >
              Listings
            </button>
            <button
              type="button"
              class="block w-full text-left px-3 py-2 rounded-sm"
              :class="activeTab === 'buyers' ? 'bg-white/10' : 'hover:bg-white/10'"
              @click="setTab('buyers')"
            >
              Buyers
            </button>
            <button
              type="button"
              class="block w-full text-left px-3 py-2 rounded-sm"
              :class="activeTab === 'earnings' ? 'bg-white/10' : 'hover:bg-white/10'"
              @click="setTab('earnings')"
            >
              Earnings
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
            @click="setTab('kyc')"
          >
            <div class="flex items-center gap-2 mb-3 text-white/70">
              <span class="text-xs uppercase tracking-widest">Compliance</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-white">KYC Status</span>
              <span class="text-xs uppercase tracking-widest text-white/70">
                {{ kycLabel }}
              </span>
            </div>
            <p class="text-xs text-white/50 mt-3 break-words">{{ dashboard.agent_info.email || '' }}</p>
          </button>
        </aside>

        <section class="space-y-8">
          <div class="rounded-lg border border-white/10 bg-emerald-950/60 p-6">
            <div class="flex items-center gap-2 text-white/70 text-xs uppercase tracking-widest mb-4">
              <span>Leasing Operations</span>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
              <button
                type="button"
                class="border border-white/10 rounded-sm p-4 bg-emerald-950/80 text-left hover:bg-emerald-900/70 transition-colors"
                @click="setTab('listings')"
              >
                <p class="text-xs uppercase tracking-widest text-white/60">Available Listings</p>
                <p class="text-2xl font-display text-white mt-1">{{ dashboard.summary.properties.available ?? 0 }}</p>
              </button>
              <button
                type="button"
                class="border border-white/10 rounded-sm p-4 bg-emerald-950/80 text-left hover:bg-emerald-900/70 transition-colors"
                @click="setTab('buyers')"
              >
                <p class="text-xs uppercase tracking-widest text-white/60">Connected Buyers</p>
                <p class="text-2xl font-display text-white mt-1">{{ dashboard.recent_bookings.length }}</p>
              </button>
              <button
                type="button"
                class="border border-white/10 rounded-sm p-4 bg-emerald-950/80 text-left hover:bg-emerald-900/70 transition-colors"
                @click="setTab('earnings')"
              >
                <p class="text-xs uppercase tracking-widest text-white/60">Total Earnings</p>
                <p class="text-2xl font-display text-white mt-1">{{ formatMoney(dashboard.summary.financials.total_agent_earnings) }}</p>
              </button>
            </div>
            <div class="mt-5 grid gap-3 md:grid-cols-2">
              <RouterLink
                to="/marketplace/agent"
                class="border border-cyan-300/20 rounded-sm p-3 bg-cyan-900/30 text-xs uppercase tracking-[0.2em] hover:bg-cyan-800/40"
              >
                Open Agent Marketplace
              </RouterLink>
              <RouterLink
                to="/intel"
                class="border border-cyan-300/20 rounded-sm p-3 bg-cyan-900/30 text-xs uppercase tracking-[0.2em] hover:bg-cyan-800/40"
              >
                Open Market Intel
              </RouterLink>
            </div>
          </div>

          <div class="bg-emerald-950/90 border border-white/10 px-6 py-6 rounded-lg">
            <h1 class="font-display text-3xl text-white">{{ headlineTitle }}</h1>
            <p class="text-white/80 mt-2">{{ headlineSubtitle }}</p>
          </div>

          <div v-if="activeTab === 'overview'" class="space-y-8">
            <div v-if="loading" class="bg-emerald-950/60 border border-white/10 p-5 text-sm text-white/70">
              Loading your dashboard...
            </div>
            <div v-else-if="error" class="bg-red-900/40 border border-red-200/20 p-5 text-sm text-red-100">
              {{ error }}
            </div>

            <div class="grid gap-6 md:grid-cols-5">
              <div class="bg-emerald-950/80 border border-white/10 p-5">
                <p class="text-xs uppercase tracking-widest text-white/60">Active Listings</p>
                <h3 class="text-2xl font-display text-white">{{ dashboard.summary.properties.available ?? 0 }}</h3>
              </div>
              <div class="bg-emerald-950/80 border border-white/10 p-5">
                <p class="text-xs uppercase tracking-widest text-white/60">Pending Approval</p>
                <h3 class="text-2xl font-display text-white">{{ dashboard.summary.properties.pending ?? 0 }}</h3>
              </div>
              <div class="bg-emerald-950/80 border border-white/10 p-5">
                <p class="text-xs uppercase tracking-widest text-white/60">Request Visits</p>
                <h3 class="text-2xl font-display text-white">{{ pendingBookings }}</h3>
              </div>
              <div class="bg-emerald-950/80 border border-white/10 p-5">
                <p class="text-xs uppercase tracking-widest text-white/60">Tracked Buyers</p>
                <h3 class="text-2xl font-display text-white">{{ dashboard.recent_bookings.length }}</h3>
              </div>
              <div class="bg-emerald-950/80 border border-white/10 p-5">
                <p class="text-xs uppercase tracking-widest text-white/60">KYC</p>
                <h3 class="text-2xl font-display text-white">{{ kycLabel }}</h3>
              </div>
            </div>

            <div class="bg-white text-emerald-950 rounded-lg border border-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-black/10">
                <h3 class="font-display text-2xl">Recent Bookings</h3>
              </div>
              <div class="divide-y divide-black/5">
                <div v-if="loading" class="px-6 py-6 text-sm text-emerald-900/60">Loading bookings...</div>
                <div v-else-if="error" class="px-6 py-6 text-sm text-red-600">{{ error }}</div>
                <div v-else-if="recentBookings.length === 0" class="px-6 py-6 text-sm text-emerald-900/60">
                  No bookings yet.
                </div>
                <div
                  v-else
                  v-for="item in recentBookings"
                  :key="item.key"
                  class="px-6 py-4 flex items-center justify-between"
                >
                  <div>
                    <p class="font-semibold">{{ item.title }}</p>
                    <p class="text-sm text-emerald-900/60">{{ item.meta }}</p>
                  </div>
                  <div class="text-right">
                    <p class="text-xs uppercase tracking-widest text-emerald-900/60">{{ item.status }}</p>
                    <span class="text-sm font-semibold">{{ item.date }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="activeTab === 'listings'" class="space-y-6">
            <div class="bg-white text-emerald-950 rounded-lg border border-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-black/10">
                <h3 class="font-display text-2xl">My Listings</h3>
              </div>
              <div class="divide-y divide-black/5">
                <div v-if="tabLoading.listings" class="px-6 py-6 text-sm text-emerald-900/60">Loading listings...</div>
                <div v-else-if="tabError.listings" class="px-6 py-6 text-sm text-red-600">{{ tabError.listings }}</div>
                <div v-else-if="listings.length === 0" class="px-6 py-6 text-sm text-emerald-900/60">No listings yet.</div>
                <div
                  v-else
                  v-for="item in listings"
                  :key="item.property_id"
                  class="px-6 py-4 flex items-center justify-between gap-4"
                >
                  <div>
                    <p class="font-semibold">{{ item.title || 'Listing' }}</p>
                    <p class="text-sm text-emerald-900/60">
                      {{ [item.city, item.state].filter(Boolean).join(', ') }} - {{ item.property_type || 'Property' }}
                    </p>
                  </div>
                  <div class="text-right">
                    <p class="text-sm font-semibold">{{ formatMoney(item.price) }}</p>
                    <p class="text-xs uppercase tracking-widest text-emerald-900/60">{{ item.status || 'pending' }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="activeTab === 'buyers'" class="space-y-6">
            <div class="bg-white text-emerald-950 rounded-lg border border-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-black/10">
                <h3 class="font-display text-2xl">Connected Buyers</h3>
              </div>
              <div class="divide-y divide-black/5">
                <div v-if="tabLoading.buyers" class="px-6 py-6 text-sm text-emerald-900/60">Loading buyers...</div>
                <div v-else-if="tabError.buyers" class="px-6 py-6 text-sm text-red-600">{{ tabError.buyers }}</div>
                <div v-else-if="buyers.length === 0" class="px-6 py-6 text-sm text-emerald-900/60">No buyer connections yet.</div>
                <div v-else v-for="buyer in buyers" :key="buyer.buyer_id" class="px-6 py-4 flex items-center justify-between gap-4">
                  <div>
                    <p class="font-semibold">{{ buyer.full_name || 'Buyer' }}</p>
                    <p class="text-sm text-emerald-900/60">{{ buyer.email || '-' }}</p>
                    <p class="text-xs text-emerald-900/60 mt-1">
                      {{ [buyer.city, buyer.state].filter(Boolean).join(', ') || 'Location not set' }}
                    </p>
                  </div>
                  <div class="text-right">
                    <p class="text-xs uppercase tracking-widest text-emerald-900/60">{{ buyer.kyc_verified || 'unknown' }}</p>
                    <p class="text-xs text-emerald-900/60 mt-1">{{ formatDate(buyer.joined_at) || '-' }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="activeTab === 'earnings'" class="space-y-6">
            <div class="grid gap-6 md:grid-cols-4">
              <div class="bg-emerald-950/80 border border-white/10 p-5">
                <p class="text-xs uppercase tracking-widest text-white/60">Total Earnings</p>
                <h3 class="text-xl font-display text-white">{{ formatMoney(earnings.total_earnings) }}</h3>
              </div>
              <div class="bg-emerald-950/80 border border-white/10 p-5">
                <p class="text-xs uppercase tracking-widest text-white/60">Pending Earnings</p>
                <h3 class="text-xl font-display text-white">{{ formatMoney(earnings.total_pending) }}</h3>
              </div>
              <div class="bg-emerald-950/80 border border-white/10 p-5">
                <p class="text-xs uppercase tracking-widest text-white/60">Completed Deals</p>
                <h3 class="text-xl font-display text-white">{{ earnings.completed_transactions || 0 }}</h3>
              </div>
              <div class="bg-emerald-950/80 border border-white/10 p-5">
                <p class="text-xs uppercase tracking-widest text-white/60">Pending Deals</p>
                <h3 class="text-xl font-display text-white">{{ earnings.pending_transactions || 0 }}</h3>
              </div>
            </div>

            <div class="bg-white text-emerald-950 rounded-lg border border-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-black/10">
                <h3 class="font-display text-2xl">Recent Sales</h3>
              </div>
              <div class="divide-y divide-black/5">
                <div v-if="tabLoading.earnings" class="px-6 py-6 text-sm text-emerald-900/60">Loading earnings...</div>
                <div v-else-if="tabError.earnings" class="px-6 py-6 text-sm text-red-600">{{ tabError.earnings }}</div>
                <div v-else-if="salesHistory.length === 0" class="px-6 py-6 text-sm text-emerald-900/60">No sales yet.</div>
                <div v-else v-for="item in salesHistory.slice(0, 8)" :key="item.transaction_id" class="px-6 py-4 flex items-center justify-between">
                  <div>
                    <p class="font-semibold">{{ item.user?.fullname || item.user?.email || 'Client' }}</p>
                    <p class="text-sm text-emerald-900/60">{{ item.transaction_type || 'deal' }} - {{ item.status || 'pending' }}</p>
                  </div>
                  <span class="text-sm font-semibold">{{ formatMoney(item.agent_share || item.amount) }}</span>
                </div>
              </div>
            </div>

            <div class="bg-white text-emerald-950 rounded-lg border border-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-black/10">
                <h3 class="font-display text-2xl">Commission Snapshot</h3>
              </div>
              <div class="divide-y divide-black/5">
                <div v-if="tabLoading.earnings" class="px-6 py-6 text-sm text-emerald-900/60">Loading commissions...</div>
                <div v-else-if="commissions.length === 0" class="px-6 py-6 text-sm text-emerald-900/60">No commissions yet.</div>
                <div v-else v-for="item in commissions.slice(0, 8)" :key="`${item.transaction_id}-commission`" class="px-6 py-4 flex items-center justify-between">
                  <div>
                    <p class="font-semibold">Txn {{ item.transaction_id }}</p>
                    <p class="text-sm text-emerald-900/60">{{ item.commission_percentage || 0 }}% commission</p>
                  </div>
                  <span class="text-sm font-semibold">{{ formatMoney(item.agent_share) }}</span>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="activeTab === 'kyc'" class="space-y-6">
            <div class="bg-white text-emerald-950 rounded-lg border border-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-black/10">
                <h3 class="font-display text-2xl">KYC Status</h3>
              </div>
              <div class="px-6 py-6 space-y-3">
                <div v-if="tabLoading.kyc" class="text-sm text-emerald-900/60">Loading KYC status...</div>
                <div v-else-if="tabError.kyc" class="text-sm text-red-600">{{ tabError.kyc }}</div>
                <div v-else-if="!kycDetails" class="text-sm text-emerald-900/60">No KYC record found.</div>
                <div v-else class="grid gap-2 text-sm text-emerald-900/80">
                  <div class="flex items-center justify-between">
                    <span>Status</span>
                    <span class="text-xs uppercase tracking-widest">{{ kycDetails.status || 'pending' }}</span>
                  </div>
                  <div>Business Reg No: {{ kycDetails.business_reg_no || '-' }}</div>
                  <div>Government ID: {{ kycDetails.government_id_type || '-' }} ({{ kycDetails.government_id_number || '-' }})</div>
                  <div>Address: {{ [kycDetails.address, kycDetails.city, kycDetails.state, kycDetails.country].filter(Boolean).join(', ') || '-' }}</div>
                  <div>Submitted: {{ formatDate(kycDetails.created_at) || '-' }}</div>
                  <div v-if="kycDetails.admin_comment">Admin comment: {{ kycDetails.admin_comment }}</div>
                  <p v-if="kycDetails.summary" class="text-emerald-900/70 mt-1">{{ kycDetails.summary }}</p>
                </div>
              </div>
            </div>

            <div class="bg-white text-emerald-950 rounded-lg border border-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-black/10">
                <h3 class="font-display text-2xl">Submit KYC Documents</h3>
              </div>
              <form class="px-6 py-6 space-y-5" @submit.prevent="submitKycDocuments">
                <div class="grid gap-4 md:grid-cols-2">
                  <label class="space-y-2 text-sm">
                    <span class="text-emerald-900">Business Name</span>
                    <input v-model="kycForm.business_name" type="text" class="form-input w-full border-emerald-900/10 bg-emerald-50/40 h-11 px-3" />
                  </label>
                  <label class="space-y-2 text-sm">
                    <span class="text-emerald-900">CAC Number</span>
                    <input v-model="kycForm.cac_number" type="text" class="form-input w-full border-emerald-900/10 bg-emerald-50/40 h-11 px-3" />
                  </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                  <label class="space-y-2 text-sm">
                    <span class="text-emerald-900">Business Address</span>
                    <input v-model="kycForm.business_address" type="text" class="form-input w-full border-emerald-900/10 bg-emerald-50/40 h-11 px-3" />
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
                    <span class="text-emerald-900">Document Front</span>
                    <input type="file" accept="image/*" class="block w-full text-sm" @change="(e) => handleFileChange('document_front', e)" />
                  </label>
                  <label class="space-y-2 text-sm">
                    <span class="text-emerald-900">Document Back</span>
                    <input type="file" accept="image/*" class="block w-full text-sm" @change="(e) => handleFileChange('document_back', e)" />
                  </label>
                  <label class="space-y-2 text-sm">
                    <span class="text-emerald-900">Support Document (Optional)</span>
                    <input type="file" accept="image/*" class="block w-full text-sm" @change="(e) => handleFileChange('support_doc', e)" />
                  </label>
                </div>

                <p class="text-xs text-emerald-900/60">Max file size: 25MB per file.</p>

                <div v-if="kycSubmitError" class="text-sm text-red-600">{{ kycSubmitError }}</div>
                <div v-if="kycSubmitSuccess" class="text-sm text-emerald-700">{{ kycSubmitSuccess }}</div>

                <button
                  type="submit"
                  class="emerald-gradient-bg text-white h-12 px-6 text-xs font-bold uppercase tracking-[0.3em] hover:brightness-110 shadow-lg transition-all disabled:opacity-60"
                  :disabled="kycSubmitting"
                >
                  {{ kycSubmitting ? 'Submitting...' : 'Submit KYC' }}
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
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'

const loading = ref(false)
const error = ref('')
const activeTab = ref('overview')

const tabLoading = reactive({
  listings: false,
  buyers: false,
  earnings: false,
  kyc: false
})

const tabError = reactive({
  listings: '',
  buyers: '',
  earnings: '',
  kyc: ''
})

const tabLoaded = reactive({
  listings: false,
  buyers: false,
  earnings: false,
  kyc: false
})

const listings = ref([])
const buyers = ref([])
const salesHistory = ref([])
const commissions = ref([])
const kycDetails = ref(null)

const earnings = reactive({
  total_earnings: 0,
  total_pending: 0,
  completed_transactions: 0,
  pending_transactions: 0
})

const kycForm = reactive({
  business_name: '',
  cac_number: '',
  business_address: '',
  city: '',
  state: '',
  country: '',
  document_front: null,
  document_back: null,
  support_doc: null
})

const kycSubmitting = ref(false)
const kycSubmitError = ref('')
const kycSubmitSuccess = ref('')

const dashboard = reactive({
  agent_info: {},
  summary: {
    properties: {
      total: 0,
      approved: 0,
      pending: 0,
      rejected: 0,
      flagged: 0,
      featured: 0,
      sold: 0,
      rented: 0,
      available: 0
    },
    financials: {
      total_sales: 0,
      total_rentals: 0,
      total_agent_earnings: 0
    },
    unread_notifications: 0
  },
  recent_bookings: []
})

const headlineTitle = computed(() => {
  const agency = dashboard.agent_info.agency_name
  return agency ? `${agency} Leasing Command Center` : 'Agency Leasing Command Center'
})

const headlineSubtitle = computed(() => {
  const total = dashboard.summary.properties.total ?? 0
  const available = dashboard.summary.properties.available ?? 0
  return `Tracking ${total} listings with ${available} ready for client leasing conversations.`
})

const kycLabel = computed(() => {
  const status = kycDetails.value?.status || dashboard.agent_info.kyc_verified || 'pending'
  const normalized = String(status).toLowerCase()
  return normalized.charAt(0).toUpperCase() + normalized.slice(1)
})

function toNumber(value) {
  if (value === null || value === undefined || value === '') return 0
  if (typeof value === 'string') {
    const normalized = value.replace(/,/g, '').trim()
    const amount = Number(normalized)
    return Number.isNaN(amount) ? 0 : amount
  }
  const amount = Number(value)
  return Number.isNaN(amount) ? 0 : amount
}

function formatMoney(value) {
  const amount = toNumber(value)
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    maximumFractionDigits: 0
  }).format(amount)
}

function formatDate(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

const recentBookings = computed(() => {
  return (dashboard.recent_bookings || []).map((b, index) => ({
    key: `booking-${index}-${b.id}`,
    title: b.property_title || 'Property visit',
    meta: `${b.fullname || 'Client'} - ${b.email || ''}`.trim(),
    date: formatDate(b.visit_date || b.created_at),
    status: b.status || 'pending'
  }))
})

const pendingBookings = computed(() => {
  return (dashboard.recent_bookings || []).filter((item) => String(item.status || '').toLowerCase() === 'pending').length
})

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
  const msg = validateFile(file)
  if (msg) {
    kycSubmitError.value = msg
    event.target.value = ''
    kycForm[field] = null
    return
  }
  kycForm[field] = file
}

function setTab(tab) {
  activeTab.value = tab
  if (tab !== 'overview') loadTabData(tab)
}

async function loadTabData(tab) {
  if (tabLoaded[tab]) return
  tabLoading[tab] = true
  tabError[tab] = ''

  try {
    if (tab === 'listings') {
      const form = new FormData()
      form.append('page', '1')
      form.append('limit', '20')
      const res = await api.post('/api/agents/Properties/view_my_properties.php', form)
      if (res.data?.status) {
        listings.value = res.data?.data?.properties || []
        tabLoaded.listings = true
      } else {
        listings.value = []
        tabError.listings = res.data?.text || 'Unable to load listings.'
      }
    }

    if (tab === 'buyers') {
      const form = new FormData()
      form.append('page', '1')
      form.append('limit', '20')
      const res = await api.post('/api/agents/Users/my_buyers.php', form)
      if (res.data?.status) {
        buyers.value = res.data?.data?.buyers || []
        tabLoaded.buyers = true
      } else {
        buyers.value = []
        tabError.buyers = res.data?.text || 'Unable to load buyers.'
      }
    }

    if (tab === 'earnings') {
      const [earningsRes, salesRes, commissionRes] = await Promise.all([
        api.post('/api/agents/Transactions/earnings.php'),
        api.post('/api/agents/Transactions/sales_history.php'),
        api.post('/api/agents/Transactions/commissions.php')
      ])

      if (earningsRes.data?.status) {
        Object.assign(earnings, earningsRes.data?.data || {})
      } else {
        Object.assign(earnings, {
          total_earnings: 0,
          total_pending: 0,
          completed_transactions: 0,
          pending_transactions: 0
        })
      }

      salesHistory.value = salesRes.data?.status ? salesRes.data?.data || [] : []
      commissions.value = commissionRes.data?.status ? commissionRes.data?.data || [] : []

      if (!earningsRes.data?.status && !salesRes.data?.status && !commissionRes.data?.status) {
        tabError.earnings = earningsRes.data?.text || salesRes.data?.text || commissionRes.data?.text || 'Unable to load earnings.'
      } else {
        tabLoaded.earnings = true
      }
    }

    if (tab === 'kyc') {
      const res = await api.post('/api/agents/KYC/view_kyc_status.php')
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
    if (tab === 'listings') tabError.listings = message
    if (tab === 'buyers') tabError.buyers = message
    if (tab === 'earnings') tabError.earnings = message
    if (tab === 'kyc') tabError.kyc = message
  } finally {
    tabLoading[tab] = false
  }
}

async function submitKycDocuments() {
  kycSubmitError.value = ''
  kycSubmitSuccess.value = ''

  if (!kycForm.business_name || !kycForm.cac_number || !kycForm.business_address || !kycForm.city || !kycForm.state || !kycForm.country) {
    kycSubmitError.value = 'Please complete all required fields.'
    return
  }

  const requiredFiles = ['document_front', 'document_back']
  for (const key of requiredFiles) {
    const msg = validateFile(kycForm[key])
    if (msg) {
      kycSubmitError.value = `${key.replace('_', ' ')}: ${msg}`
      return
    }
  }

  const payload = new FormData()
  payload.append('business_name', kycForm.business_name)
  payload.append('cac_number', kycForm.cac_number)
  payload.append('business_address', kycForm.business_address)
  payload.append('city', kycForm.city)
  payload.append('state', kycForm.state)
  payload.append('country', kycForm.country)
  payload.append('document_front', kycForm.document_front)
  payload.append('document_back', kycForm.document_back)
  if (kycForm.support_doc) payload.append('support_doc', kycForm.support_doc)

  kycSubmitting.value = true
  try {
    const res = await api.post('/api/agents/KYC/kyc_submission.php', payload)
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

async function loadDashboard() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.post('/api/agents/Dashboard/Agent_dashboard.php')
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
