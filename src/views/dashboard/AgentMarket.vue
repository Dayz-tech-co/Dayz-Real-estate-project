<template>
  <div class="min-h-screen bg-[radial-gradient(120%_140%_at_10%_0%,#1f2937_0%,#0f172a_55%,#020617_100%)] text-white">
    <section class="layout-content-container px-6 py-10 space-y-8">
      <div class="flex items-start gap-4">
        <button
          type="button"
          class="inline-flex items-center gap-2 border border-slate-300/30 bg-slate-800/60 px-4 py-2 text-xs uppercase tracking-widest text-slate-100 hover:bg-slate-700/70"
          @click="goBack"
        >
          <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor" aria-hidden="true">
            <path d="M14.7 5.3 8 12l6.7 6.7 1.4-1.4-5.3-5.3 5.3-5.3z" />
          </svg>
          Back
        </button>
        <div>
          <p class="text-xs uppercase tracking-[0.3em] text-amber-200/75">Market Intel</p>
          <h1 class="mt-2 font-display text-3xl text-slate-100">Agent Property Desk</h1>
          <p class="mt-2 text-sm text-slate-300/80">
            Browse your inventory and network listings with faster filtering and actions.
          </p>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-4">
        <div class="border border-slate-400/20 bg-slate-900/75 p-4">
          <p class="text-xs uppercase tracking-widest text-white/60">Total Listings</p>
          <p class="mt-1 font-display text-2xl">{{ filteredListings.length }}</p>
        </div>
        <div class="border border-slate-400/20 bg-slate-900/75 p-4">
          <p class="text-xs uppercase tracking-widest text-white/60">My Listings</p>
          <p class="mt-1 font-display text-2xl">{{ ownCount }}</p>
        </div>
        <div class="border border-slate-400/20 bg-slate-900/75 p-4">
          <p class="text-xs uppercase tracking-widest text-white/60">Pending Visits</p>
          <p class="mt-1 font-display text-2xl">{{ pendingBookingsCount }}</p>
        </div>
        <div class="border border-slate-400/20 bg-slate-900/75 p-4">
          <p class="text-xs uppercase tracking-widest text-white/60">Total Earnings</p>
          <p class="mt-1 font-display text-2xl">{{ formatMoney(earnings.total_earnings) }}</p>
        </div>
      </div>

      <div class="rounded-lg border border-slate-400/20 bg-slate-900/75 p-6 space-y-4">
        <div class="grid gap-4 lg:grid-cols-[1fr,220px,220px,220px]">
          <input
            v-model.trim="filters.query"
            type="text"
            placeholder="Search listings, city, state or agency"
            class="form-input h-11 border-slate-300/25 bg-slate-900/80 px-3 text-slate-100 placeholder:text-slate-500"
          />
          <select v-model="filters.type" class="form-select h-11 border-slate-300/25 bg-slate-900/80 px-3 text-slate-100">
            <option value="">All Types</option>
            <option value="shortlet">Shortlet</option>
            <option value="apartment">Apartment</option>
            <option value="hotel">Hotel</option>
            <option value="house">House</option>
            <option value="land">Land</option>
            <option value="office">Office</option>
          </select>
          <select v-model="filters.ownership" class="form-select h-11 border-slate-300/25 bg-slate-900/80 px-3 text-slate-100">
            <option value="all">All Listings</option>
            <option value="mine">My Listings</option>
            <option value="network">Other Agents</option>
          </select>
          <input
            v-model.number="filters.maxPrice"
            type="number"
            min="0"
            placeholder="Max price"
            class="form-input h-11 border-slate-300/25 bg-slate-900/80 px-3 text-slate-100 placeholder:text-slate-500"
          />
        </div>
      </div>

      <div v-if="loading" class="border border-slate-400/20 bg-slate-900/70 p-5 text-sm text-white/70">
        Loading marketplace listings...
      </div>
      <div v-else-if="error" class="border border-red-200/20 bg-red-900/40 p-5 text-sm text-red-100">
        {{ error }}
      </div>

      <div v-else class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="listing in filteredListings"
          :key="listing.clientKey"
          class="cursor-pointer overflow-hidden border border-slate-400/25 bg-slate-900/80 text-slate-100 shadow-lg transition-shadow hover:border-amber-500/40 hover:shadow-2xl"
          @click="openPropertyDetails(listing)"
        >
          <div>
            <img :src="listing.thumbnail" :alt="listing.title" class="h-48 w-full object-cover" />
          </div>
          <div class="space-y-3 p-5">
            <div class="flex items-center justify-between gap-3">
              <span
                class="border px-2 py-1 text-[10px] uppercase tracking-widest"
                :class="listing.isMine ? 'border-amber-400/80 text-amber-200' : 'border-slate-300/35 text-slate-300'"
              >
                {{ listing.isMine ? 'My Listing' : 'Network Listing' }}
              </span>
              <span class="text-xs uppercase tracking-widest text-slate-400">{{ listing.propertyType }}</span>
            </div>
            <p class="flex items-center gap-2 font-display text-2xl">
              <span>{{ formatMoney(listing.price) }}</span>
            </p>
            <p class="font-semibold">{{ listing.title || 'Property listing' }}</p>
            <p class="flex items-center gap-2 text-sm text-slate-300">
              <svg viewBox="0 0 24 24" class="h-4 w-4 text-slate-300" fill="currentColor" aria-hidden="true">
                <path d="M12 2a7 7 0 0 1 7 7c0 5.2-7 13-7 13S5 14.2 5 9a7 7 0 0 1 7-7Zm0 9.5A2.5 2.5 0 1 0 12 6a2.5 2.5 0 0 0 0 5.5Z"/>
              </svg>
              <span>{{ listing.location }}</span>
            </p>
            <p class="text-xs text-slate-400">Agency: {{ listing.agencyName || 'Unknown' }}</p>
            <div class="grid grid-cols-3 gap-2 text-[10px] uppercase tracking-widest text-slate-400">
              <div>Bed<br /><span class="text-slate-100">{{ listing.bed || '-' }}</span></div>
              <div>Bath<br /><span class="text-slate-100">{{ listing.bath || '-' }}</span></div>
              <div>Size<br /><span class="text-slate-100">{{ listing.asize || '-' }}</span></div>
            </div>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'

const loading = ref(false)
const error = ref('')
const listings = ref([])
const agentId = ref(null)
const router = useRouter()
const dashboardBookings = ref([])
const earnings = reactive({
  total_earnings: 0
})

const filters = reactive({
  query: '',
  type: '',
  ownership: 'all',
  maxPrice: null
})

function normalizeImage(path) {
  const raw = String(path || '').trim()
  if (!raw) return '/uploads/properties/1761862624_DJI_0253-2-scaled.webp'
  const cleaned = raw.replace(/\\/g, '/')
  if (cleaned.startsWith('http')) return cleaned
  if (cleaned.startsWith('/')) return cleaned
  return `/${cleaned}`
}

function toImageArray(value) {
  if (Array.isArray(value)) return value.filter(Boolean)
  if (typeof value !== 'string') return []
  const raw = value.trim()
  if (!raw) return []
  try {
    const parsed = JSON.parse(raw)
    if (Array.isArray(parsed)) return parsed.filter(Boolean)
  } catch (err) {
    // fallback for comma-separated values
  }
  return raw.split(',').map((entry) => entry.trim()).filter(Boolean)
}

function normalizeType(type) {
  return String(type || '').trim().toLowerCase()
}

function formatMoney(value) {
  const amount = Number(value || 0)
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(
    Number.isNaN(amount) ? 0 : amount
  )
}

function openPropertyDetails(listing) {
  if (!listing?.id) return
  router.push(`/property/${listing.id}`)
}

function goBack() {
  if (window.history.length > 1) {
    router.back()
    return
  }
  router.push('/dashboard/agent')
}

const ownCount = computed(() => listings.value.filter((item) => item.isMine).length)

const pendingBookingsCount = computed(() => {
  return dashboardBookings.value.filter((item) => String(item.status || '').toLowerCase() === 'pending').length
})

const filteredListings = computed(() => {
  const query = filters.query.toLowerCase()
  const max = Number(filters.maxPrice || 0)
  return listings.value.filter((item) => {
    const haystack = `${item.title} ${item.city} ${item.state} ${item.location} ${item.agencyName}`.toLowerCase()
    const matchQuery = query ? haystack.includes(query) : true
    const matchType = filters.type ? normalizeType(item.propertyType) === filters.type : true
    const matchOwnership =
      filters.ownership === 'mine' ? item.isMine : filters.ownership === 'network' ? !item.isMine : true
    const matchPrice = max > 0 ? Number(item.price || 0) <= max : true
    return matchQuery && matchType && matchOwnership && matchPrice
  })
})

async function loadAgentContext() {
  const [dashboardRes, earningsRes] = await Promise.all([
    api.post('/api/agents/Dashboard/Agent_dashboard.php'),
    api.post('/api/agents/Transactions/earnings.php')
  ])

  if (dashboardRes.data?.status) {
    agentId.value = dashboardRes.data?.data?.agent_info?.id || null
    dashboardBookings.value = dashboardRes.data?.data?.recent_bookings || []
  }
  if (earningsRes.data?.status) {
    Object.assign(earnings, earningsRes.data?.data || {})
  }
}

async function loadListings() {
  loading.value = true
  error.value = ''
  try {
    await loadAgentContext()
    const res = await api.post('/api/users/Properties/view_all_properties.php', { page: 1, limit: 120 })
    if (!res.data?.status) {
      error.value = res.data?.text || 'Unable to load listings.'
      listings.value = []
      return
    }

    const rows = res.data?.data?.properties || []
    listings.value = rows.map((item, index) => {
      const rawImages = toImageArray(item.images)
      return {
        clientKey: `${item.id}-${index}`,
        id: Number(item.id),
        agentId: Number(item.agent_id || 0),
        agencyName: item.agency_name || '',
        title: item.title || '',
        city: item.city || '',
        state: item.state || '',
        location: item.location || [item.city, item.state].filter(Boolean).join(', '),
        price: Number(item.price || 0),
        propertyType: item.property_type || '',
        bed: item.bed,
        bath: item.bath,
        asize: item.asize,
        thumbnail: normalizeImage(item.thumbnail || rawImages[0] || item.image),
        isMine: agentId.value && Number(item.agent_id || 0) === Number(agentId.value)
      }
    })
  } catch (err) {
    error.value = err?.response?.data?.text || 'Unable to load listings.'
  } finally {
    loading.value = false
  }
}

onMounted(loadListings)
</script>
