<template>
  <div class="min-h-screen bg-theme text-white">
    <section class="layout-content-container px-6 py-10 space-y-8">
      <div class="rounded-lg border border-white/10 bg-cyan-950/60 p-6">
        <p class="text-xs uppercase tracking-[0.3em] text-cyan-100/70">Agent Marketplace</p>
        <h1 class="font-display text-3xl mt-2">Network Listings Command</h1>
        <p class="text-sm text-cyan-100/75 mt-2">
          Browse your inventory and other agent listings in one place, with booking and earnings tracking.
        </p>
      </div>

      <div class="grid gap-4 md:grid-cols-4">
        <div class="border border-white/10 bg-cyan-950/70 p-4">
          <p class="text-xs uppercase tracking-widest text-white/60">Total Listings</p>
          <p class="font-display text-2xl mt-1">{{ filteredListings.length }}</p>
        </div>
        <div class="border border-white/10 bg-cyan-950/70 p-4">
          <p class="text-xs uppercase tracking-widest text-white/60">My Listings</p>
          <p class="font-display text-2xl mt-1">{{ ownCount }}</p>
        </div>
        <div class="border border-white/10 bg-cyan-950/70 p-4">
          <p class="text-xs uppercase tracking-widest text-white/60">Pending Visits</p>
          <p class="font-display text-2xl mt-1">{{ pendingBookingsCount }}</p>
        </div>
        <div class="border border-white/10 bg-cyan-950/70 p-4">
          <p class="text-xs uppercase tracking-widest text-white/60">Total Earnings</p>
          <p class="font-display text-2xl mt-1">{{ formatMoney(earnings.total_earnings) }}</p>
        </div>
      </div>

      <div class="bg-emerald-950/80 border border-white/10 rounded-lg p-6 space-y-4">
        <div class="grid gap-4 lg:grid-cols-[1fr,220px,220px,220px]">
          <input
            v-model.trim="filters.query"
            type="text"
            placeholder="Search by title, city, state, agency"
            class="form-input h-11 px-3 bg-white/95 text-emerald-950"
          />
          <select v-model="filters.type" class="form-select h-11 px-3 bg-white/95 text-emerald-950">
            <option value="">All Types</option>
            <option value="shortlet">Shortlet</option>
            <option value="apartment">Apartment</option>
            <option value="hotel">Hotel</option>
            <option value="house">House</option>
            <option value="land">Land</option>
            <option value="office">Office</option>
          </select>
          <select v-model="filters.ownership" class="form-select h-11 px-3 bg-white/95 text-emerald-950">
            <option value="all">All Listings</option>
            <option value="mine">My Listings</option>
            <option value="network">Other Agents</option>
          </select>
          <input
            v-model.number="filters.maxPrice"
            type="number"
            min="0"
            placeholder="Max Price"
            class="form-input h-11 px-3 bg-white/95 text-emerald-950"
          />
        </div>
      </div>

      <div v-if="loading" class="bg-emerald-950/60 border border-white/10 p-5 text-sm text-white/70">
        Loading marketplace listings...
      </div>
      <div v-else-if="error" class="bg-red-900/40 border border-red-200/20 p-5 text-sm text-red-100">
        {{ error }}
      </div>

      <div v-else class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="listing in filteredListings"
          :key="listing.clientKey"
          class="bg-white text-emerald-950 border border-black/10 shadow-lg overflow-hidden"
        >
          <div>
            <img :src="listing.thumbnail" :alt="listing.title" class="h-48 w-full object-cover" />
          </div>
          <div class="p-5 space-y-3">
            <div class="flex items-center justify-between gap-3">
              <span
                class="text-[10px] uppercase tracking-widest px-2 py-1 border"
                :class="listing.isMine ? 'border-cyan-700 text-cyan-700' : 'border-emerald-900/30 text-emerald-900/70'"
              >
                {{ listing.isMine ? 'My Listing' : 'Network Listing' }}
              </span>
              <span class="text-xs uppercase tracking-widest text-emerald-900/50">{{ listing.propertyType }}</span>
            </div>
            <p class="font-display text-2xl">{{ formatMoney(listing.price) }}</p>
            <p class="font-semibold">{{ listing.title || 'Property listing' }}</p>
            <p class="text-sm text-emerald-900/70">{{ listing.location }}</p>
            <p class="text-xs text-emerald-900/60">Agency: {{ listing.agencyName || 'Unknown' }}</p>
            <div class="grid grid-cols-3 gap-2 text-[10px] uppercase tracking-widest text-emerald-900/60">
              <div>Bed<br /><span class="text-emerald-900">{{ listing.bed || '-' }}</span></div>
              <div>Bath<br /><span class="text-emerald-900">{{ listing.bath || '-' }}</span></div>
              <div>Size<br /><span class="text-emerald-900">{{ listing.asize || '-' }}</span></div>
            </div>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'

const loading = ref(false)
const error = ref('')
const listings = ref([])
const agentId = ref(null)
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
  if (!path) return '/uploads/properties/1761862624_DJI_0253-2-scaled.webp'
  if (String(path).startsWith('http')) return path
  if (String(path).startsWith('/')) return path
  return `/${path}`
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
      const rawImages = Array.isArray(item.images) ? item.images : []
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
        thumbnail: normalizeImage(item.thumbnail || rawImages[0]),
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
