<template>
  <div class="min-h-screen bg-[radial-gradient(120%_140%_at_10%_0%,#1f2937_0%,#0f172a_55%,#020617_100%)] text-slate-100">
    <section class="layout-content-container px-6 py-12">
      <div class="mb-8 flex items-start gap-4">
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
          <p class="text-xs uppercase tracking-[0.3em] text-amber-200/75">{{ isAgent ? 'Market Intel' : 'Property Intel' }}</p>
          <h1 class="mt-2 font-display text-3xl">{{ isAgent ? 'Agent Property Brief' : 'Property Brief' }}</h1>
          <p class="mt-2 text-sm text-slate-300/80">
            {{ isAgent ? 'Listing overview with pricing, location, and agent-owned context.' : 'Detailed listing information and booking actions.' }}
          </p>
        </div>
      </div>

      <div v-if="loading" class="text-slate-300/80">Loading property details...</div>
      <div v-else-if="error" class="text-red-400">{{ error }}</div>
      <div v-else-if="!property" class="text-slate-300/80">Property not found.</div>

      <div v-else class="grid gap-8 lg:grid-cols-[1.25fr,0.75fr]">
        <div class="space-y-4">
          <div class="border border-slate-400/20 bg-slate-900/70">
            <img :src="mainImage" :alt="property.title || 'Property'" class="h-[420px] w-full object-cover" />
          </div>
          <div v-if="images.length > 1" class="grid grid-cols-4 gap-3">
            <button
              v-for="(img, index) in images"
              :key="`${img}-${index}`"
              type="button"
              class="border border-slate-400/20 bg-slate-900/70"
              @click="activeImage = img"
            >
              <img :src="img" :alt="`Property image ${index + 1}`" class="h-24 w-full object-cover" />
            </button>
          </div>
        </div>

        <aside class="space-y-4 border border-slate-400/20 bg-slate-900/75 p-6">
          <div class="flex items-center gap-2 text-xs uppercase tracking-widest text-slate-300/70">
            <span>{{ property.property_type || 'Property' }}</span>
            <span class="text-white/40">|</span>
            <span>{{ bookingTypeLabel }}</span>
          </div>
          <h1 class="font-display text-3xl">{{ property.title || 'Property details' }}</h1>
          <p class="font-display text-2xl">{{ formatMoney(property.price) }}</p>
          <p class="text-sm text-slate-300/80">{{ property.location || [property.city, property.state].filter(Boolean).join(', ') }}</p>

          <div v-if="isUser" class="space-y-3 rounded border border-slate-400/20 bg-slate-800/55 p-4">
            <p class="text-xs uppercase tracking-widest text-slate-300/70">Property Action</p>
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="space-y-1 text-xs text-slate-300/80">
                <span>Visit/Start Date</span>
                <input v-model="actionForm.visit_date" :min="todayDate" type="date" class="h-10 w-full border border-slate-300/25 bg-slate-900/70 px-3 text-white" />
              </label>
              <label v-if="canBookTerm && !isLease" class="space-y-1 text-xs text-slate-300/80">
                <span>End Date</span>
                <input v-model="actionForm.end_date" :min="minimumEndDate" type="date" class="h-10 w-full border border-slate-300/25 bg-slate-900/70 px-3 text-white" />
              </label>
              <label v-if="isLease" class="space-y-1 text-xs text-slate-300/80">
                <span>End Date (Auto)</span>
                <input :value="leaseEndDate" readonly type="date" class="h-10 w-full border border-slate-300/25 bg-slate-900/50 px-3 text-white" />
              </label>
              <label v-if="isLease" class="space-y-1 text-xs text-slate-300/80">
                <span>Extra Months</span>
                <input v-model.number="actionForm.additional_months" min="0" step="1" type="number" class="h-10 w-full border border-slate-300/25 bg-slate-900/70 px-3 text-white" />
              </label>
              <label v-if="canRequestVisit" class="space-y-1 text-xs text-slate-300/80">
                <span>Preferred Time (optional)</span>
                <input v-model="actionForm.scheduled_time" type="time" class="h-10 w-full border border-slate-300/25 bg-slate-900/70 px-3 text-white" />
              </label>
            </div>
            <label v-if="canBookTerm" class="block space-y-1 text-xs text-slate-300/80">
              <span>Note (optional)</span>
              <textarea v-model="actionForm.notes" rows="2" class="w-full border border-slate-300/25 bg-slate-900/70 px-3 py-2 text-white"></textarea>
            </label>
            <div v-if="canBookTerm && !isLease" class="grid grid-cols-3 gap-2 rounded border border-emerald-400/20 bg-emerald-950/20 p-3 text-[11px] uppercase tracking-widest text-emerald-100/90">
              <div>Daily Rate<br /><span class="text-emerald-200">{{ formatMoney(dailyRate) }}</span></div>
              <div>Days<br /><span class="text-emerald-200">{{ rentalDays || 0 }}</span></div>
              <div>Total<br /><span class="text-emerald-200">{{ formatMoney(estimatedRentalTotal) }}</span></div>
            </div>
            <div v-if="isLease" class="grid grid-cols-2 gap-2 rounded border border-emerald-400/20 bg-emerald-950/20 p-3 text-[11px] uppercase tracking-widest text-emerald-100/90">
              <div>Base (3 Months)<br /><span class="text-emerald-200">{{ formatMoney(dailyRate) }}</span></div>
              <div>Monthly Rate<br /><span class="text-emerald-200">{{ formatMoney(leaseMonthlyRate) }}</span></div>
              <div>Total Months<br /><span class="text-emerald-200">{{ totalLeaseMonths }}</span></div>
              <div>Total<br /><span class="text-emerald-200">{{ formatMoney(estimatedLeaseTotal) }}</span></div>
            </div>
            <p v-if="isLease" class="text-[11px] text-amber-200/90">
              Lease starts from 3 months. Add extra months for longer-term stays.
            </p>

            <button
              v-if="canRequestVisit"
              type="button"
              class="inline-flex items-center gap-2 border border-cyan-300/30 bg-cyan-900/25 px-4 py-2 text-xs uppercase tracking-widest text-cyan-100 hover:bg-cyan-800/35 disabled:opacity-60"
              :disabled="actionLoading"
              @click="requestVisit"
            >
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true">
                <path d="M7 2h2v2h6V2h2v2h3v18H4V4h3V2Zm11 8H6v10h12V10Zm-6 2 3 3-1.4 1.4-1.6-1.6-1.6 1.6L9 15l3-3Z"/>
              </svg>
              <span>{{ actionLoading ? 'Submitting...' : 'Request Visit' }}</span>
            </button>

            <button
              v-if="canBookTerm"
              type="button"
              class="inline-flex items-center gap-2 border border-emerald-300/30 bg-emerald-900/25 px-4 py-2 text-xs uppercase tracking-widest text-emerald-100 hover:bg-emerald-800/35 disabled:opacity-60"
              :disabled="actionLoading || !isTermBookingValid"
              @click="bookApartment"
            >
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true">
                <path d="M3 5h18v14H3V5Zm2 2v10h14V7H5Zm2 2h10v2H7V9Zm0 4h6v2H7v-2Z"/>
              </svg>
              <span>{{ actionLoading ? 'Submitting...' : bookingActionLabel }}</span>
            </button>

            <p v-if="!canRequestVisit && !canBookTerm" class="text-xs text-slate-300/80">
              This listing is not available for booking/visit request from this page.
            </p>
            <p v-if="actionError" class="text-xs text-red-300">{{ actionError }}</p>
            <p v-if="actionSuccess" class="text-xs text-emerald-200">{{ actionSuccess }}</p>
          </div>

          <div class="grid grid-cols-3 gap-3 pt-3 text-[11px] uppercase tracking-widest text-slate-300/80">
            <div>Beds<br /><span class="text-white">{{ property.bed || '-' }}</span></div>
            <div>Baths<br /><span class="text-white">{{ property.bath || '-' }}</span></div>
            <div>Size<br /><span class="text-white">{{ property.asize || '-' }}</span></div>
          </div>

          <div class="border-t border-slate-400/20 pt-3">
            <p class="mb-1 text-xs uppercase tracking-widest text-slate-300/70">Category</p>
            <p class="text-sm text-white">{{ bookingTypeLabel }}</p>
          </div>

          <div class="border-t border-slate-400/20 pt-3">
            <p class="mb-2 text-xs uppercase tracking-widest text-slate-300/70">Description</p>
            <p class="whitespace-pre-line text-sm text-slate-200/90">{{ property.description || 'No description provided.' }}</p>
          </div>

          <div class="border-t border-slate-400/20 pt-3">
            <p class="mb-2 text-xs uppercase tracking-widest text-slate-300/70">Agent</p>
            <p class="text-sm text-white">{{ agent?.agency_name || '-' }}</p>
            <p class="text-sm text-slate-300/80">{{ agent?.email || '-' }}</p>
            <p class="text-sm text-slate-300/80">{{ agent?.phoneno || '-' }}</p>
          </div>
        </aside>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const error = ref('')
const property = ref(null)
const agent = ref(null)
const activeImage = ref('')
const actionLoading = ref(false)
const actionError = ref('')
const actionSuccess = ref('')
const actionForm = ref({
  visit_date: '',
  end_date: '',
  additional_months: 0,
  scheduled_time: '',
  notes: ''
})
const role = ref(String(localStorage.getItem('USER_ROLE') || '').toLowerCase())

const images = computed(() => {
  const list = []
  if (property.value?.thumbnail) list.push(property.value.thumbnail)
  if (Array.isArray(property.value?.images)) list.push(...property.value.images)

  const normalized = [...new Set(list.filter(Boolean))].map((img) => {
    if (img.startsWith('http')) return img
    if (img.startsWith('/')) return img
    return `/${img}`
  })
  return normalized.length > 0 ? normalized : ['/uploads/properties/1761862624_DJI_0253-2-scaled.webp']
})

const mainImage = computed(() => activeImage.value || images.value[0])
const isUser = computed(() => role.value === 'user')
const isAgent = computed(() => role.value === 'agent')
const normalizedType = computed(() => String(property.value?.property_type || '').toLowerCase())
const normalizedCategory = computed(() => {
  const primary = String(property.value?.property_category || '').toLowerCase()
  if (primary) return primary
  return String(property.value?.sale_type || '').toLowerCase()
})
const bookingType = computed(() => {
  if (normalizedCategory.value === 'sale') return 'sale'
  if (normalizedCategory.value === 'lease') return 'lease'
  if (['rent', 'rental'].includes(normalizedCategory.value)) return 'rental'
  return normalizedType.value === 'apartment' ? 'rental' : 'sale'
})
const bookingTypeLabel = computed(() => bookingType.value)
const isLease = computed(() => bookingType.value === 'lease')
const canBookTerm = computed(() => isUser.value && ['rental', 'lease'].includes(bookingType.value))
const canRequestVisit = computed(() => isUser.value && bookingType.value === 'sale')
const bookingActionLabel = computed(() => (isLease.value ? 'Book Lease' : 'Book Rental'))
const dailyRate = computed(() => Number(property.value?.price || 0))

function toLocalDateInputValue(date = new Date()) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function addDays(dateStr, days) {
  const date = new Date(`${dateStr}T00:00:00`)
  if (Number.isNaN(date.getTime())) return ''
  date.setDate(date.getDate() + days)
  return toLocalDateInputValue(date)
}

function addMonths(dateStr, months) {
  const date = new Date(`${dateStr}T00:00:00`)
  if (Number.isNaN(date.getTime())) return ''
  date.setMonth(date.getMonth() + months)
  return toLocalDateInputValue(date)
}

function calculateDaySpan(startDate, endDate) {
  if (!startDate || !endDate) return 0
  const start = new Date(`${startDate}T00:00:00`)
  const end = new Date(`${endDate}T00:00:00`)
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return 0
  const diff = end.getTime() - start.getTime()
  return diff > 0 ? Math.floor(diff / 86400000) : 0
}

const todayDate = computed(() => toLocalDateInputValue())
const minimumEndDate = computed(() => {
  const start = actionForm.value.visit_date || todayDate.value
  return addDays(start, 1)
})
const leaseBaseMonths = 3
const leaseExtraMonths = computed(() => {
  const raw = Number(actionForm.value.additional_months || 0)
  if (!Number.isFinite(raw) || raw < 0) return 0
  return Math.floor(raw)
})
const totalLeaseMonths = computed(() => leaseBaseMonths + leaseExtraMonths.value)
const leaseMonthlyRate = computed(() => dailyRate.value / leaseBaseMonths)
const leaseEndDate = computed(() => {
  const start = actionForm.value.visit_date || todayDate.value
  return addMonths(start, totalLeaseMonths.value)
})
const rentalDays = computed(() => calculateDaySpan(actionForm.value.visit_date, actionForm.value.end_date))
const estimatedRentalTotal = computed(() => dailyRate.value * rentalDays.value)
const estimatedLeaseTotal = computed(() => leaseMonthlyRate.value * totalLeaseMonths.value)
const isTermBookingValid = computed(() => {
  if (!canBookTerm.value || !actionForm.value.visit_date) return false
  if (actionForm.value.visit_date < todayDate.value) return false
  if (isLease.value) return totalLeaseMonths.value >= leaseBaseMonths
  if (!actionForm.value.end_date) return false
  return rentalDays.value > 0
})

function seedDefaultRentalDates() {
  if (!actionForm.value.visit_date || actionForm.value.visit_date < todayDate.value) {
    actionForm.value.visit_date = todayDate.value
  }
  if (!canBookTerm.value) return
  if (isLease.value) {
    actionForm.value.end_date = leaseEndDate.value
    return
  }
  if (!actionForm.value.end_date || actionForm.value.end_date <= actionForm.value.visit_date) {
    actionForm.value.end_date = addDays(actionForm.value.visit_date, 1)
  }
}

function formatMoney(value) {
  const amount = Number(value || 0)
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    maximumFractionDigits: 0
  }).format(amount)
}

function extractApiError(payload, fallbackText) {
  const genericText = String(payload?.text || '')
  const technicalText = String(payload?.error?.text || '')
  if (technicalText && genericText.toLowerCase().includes('oops')) {
    return technicalText
  }
  return genericText || technicalText || fallbackText
}

function goBack() {
  if (window.history.length > 1) {
    router.back()
  } else {
    const currentRole = localStorage.getItem('USER_ROLE')
    router.push(currentRole === 'agent' ? '/marketplace/agent' : '/dashboard/user')
  }
}

async function loadPropertyDetails() {
  const id = Number(route.params.id)
  if (!id) {
    error.value = 'Invalid property id.'
    return
  }

  loading.value = true
  error.value = ''
  property.value = null
  agent.value = null

  try {
    const payload = new FormData()
    payload.append('property_id', String(id))
    const res = await api.post('/api/users/Properties/view_property.php', payload)
    if (res.data?.status) {
      property.value = res.data?.data?.property || null
      agent.value = res.data?.data?.agent || null
      activeImage.value = ''
      seedDefaultRentalDates()
      return
    }
    error.value = res.data?.text || 'Unable to load property details.'
  } catch (err) {
    error.value = err?.response?.data?.text || 'Unable to load property details.'
  } finally {
    loading.value = false
  }
}

onMounted(loadPropertyDetails)

async function requestVisit() {
  actionError.value = ''
  actionSuccess.value = ''
  if (!property.value?.id) return
  if (!actionForm.value.visit_date) {
    actionError.value = 'Please choose a visit date.'
    return
  }

  actionLoading.value = true
  try {
    const payload = new FormData()
    payload.append('property_id', String(property.value.id))
    payload.append('visit_date', actionForm.value.visit_date)
    if (actionForm.value.scheduled_time) payload.append('scheduled_time', actionForm.value.scheduled_time)
    const res = await api.post('/api/users/Bookings/request_visit.php', payload)
    if (res.data?.status) {
      actionSuccess.value = res.data?.text || 'Visit request submitted.'
    } else {
      actionError.value = extractApiError(res.data, 'Visit request failed.')
    }
  } catch (err) {
    actionError.value = extractApiError(err?.response?.data, 'Visit request failed.')
  } finally {
    actionLoading.value = false
  }
}

async function bookApartment() {
  actionError.value = ''
  actionSuccess.value = ''
  if (!property.value?.id) return
  if (!isTermBookingValid.value) {
    actionError.value = isLease.value
      ? 'Enter a valid lease start date and extra months.'
      : 'Select a valid start and end date. End date must be after start date.'
    return
  }

  actionLoading.value = true
  try {
    const payload = new FormData()
    payload.append('property_id', String(property.value.id))
    payload.append('visit_date', actionForm.value.visit_date)
    const computedEndDate = isLease.value ? leaseEndDate.value : actionForm.value.end_date
    payload.append('end_date', computedEndDate)
    if (isLease.value) payload.append('additional_months', String(leaseExtraMonths.value))
    if (actionForm.value.notes) payload.append('notes', actionForm.value.notes)
    const res = await api.post('/api/users/Bookings/create_booking.php', payload)
    if (res.data?.status) {
      const total = Number(res.data?.data?.total_amount || estimatedRentalTotal.value)
      actionSuccess.value = `${res.data?.text || 'Booking submitted.'} Estimated total: ${formatMoney(total)}`
    } else {
      actionError.value = extractApiError(res.data, 'Booking failed.')
    }
  } catch (err) {
    actionError.value = extractApiError(err?.response?.data, 'Booking failed.')
  } finally {
    actionLoading.value = false
  }
}
</script>
