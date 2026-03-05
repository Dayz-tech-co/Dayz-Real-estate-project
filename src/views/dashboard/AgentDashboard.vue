<template>
  <div class="min-h-screen bg-slate-950 text-slate-200">
    <div class="mx-auto grid max-w-[1500px] lg:grid-cols-[260px,1fr]">
      <aside class="border-r border-slate-800 bg-slate-900/90 px-5 py-8">
        <div class="border border-slate-700 bg-slate-900 px-4 py-4">
          <img src="/images/DayzLogo.svg" alt="Dayz" class="h-10 w-auto object-contain" />
          <p class="mt-4 text-[10px] uppercase tracking-[0.2em] text-amber-200/70">Agency Partner</p>
          <h2 class="mt-1 text-base font-semibold uppercase tracking-wide text-slate-100">
            {{ dashboard.agent_info.agency_name || 'Agency' }}
          </h2>
          <p class="mt-1 break-words text-xs text-slate-400">{{ dashboard.agent_info.email || '' }}</p>
        </div>

        <nav class="mt-8 space-y-1">
          <button
            v-for="item in navItems"
            :key="item.key"
            type="button"
            class="group flex w-full items-center gap-3 border border-transparent px-3 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-300 transition-colors hover:border-slate-700 hover:bg-slate-800/70"
            :class="activeTab === item.key ? 'border-slate-700 bg-slate-800 text-slate-100' : ''"
            @click="handleNav(item.key)"
          >
            <span class="h-5 w-[2px] bg-transparent transition-colors" :class="activeTab === item.key ? 'bg-amber-400' : ''"></span>
            <span class="inline-flex h-5 w-5 items-center justify-center text-slate-400">
              <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7">
                <path :d="item.icon" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </span>
            <span>{{ item.label }}</span>
          </button>
        </nav>
      </aside>

      <main class="px-4 py-6 md:px-8">
        <section class="border border-slate-800 bg-slate-900 px-5 py-4 md:px-7">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <p class="text-[10px] uppercase tracking-[0.24em] text-slate-500">Executive Console</p>
              <h1 class="mt-1 text-xl font-semibold uppercase tracking-[0.1em] text-slate-100">{{ pageTitle }}</h1>
              <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-400">Dashboard / {{ pageTitle }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-xs">
              <input
                v-model.trim="dashboardSearch"
                type="text"
                placeholder="Search listings..."
                class="h-10 w-52 border border-slate-600 bg-slate-800 px-3 text-[11px] uppercase tracking-[0.12em] text-slate-100 placeholder:text-slate-500"
              />
              <div class="border border-amber-500/40 bg-slate-800 px-3 py-2 uppercase tracking-[0.16em] text-amber-200">
                Earnings {{ formatMoney(earnings.total_earnings || dashboard.summary.financials.total_agent_earnings) }}
              </div>
              <button type="button" class="border border-slate-700 bg-slate-800 p-2 text-slate-300 hover:border-amber-500/50" @click="openNotifications">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7">
                  <path d="M15 17h5l-1.4-1.4a2 2 0 0 1-.6-1.4V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5" stroke-linecap="round" stroke-linejoin="round" />
                  <path d="M10 17a2 2 0 0 0 4 0" stroke-linecap="round" />
                </svg>
              </button>
              <button type="button" class="flex items-center gap-2 border border-slate-700 bg-slate-800 px-3 py-2 hover:border-amber-500/50" @click="openAccountSettings">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-600 bg-slate-700 text-[11px] font-semibold text-slate-200">
                  {{ agentInitial }}
                </span>
                <span class="uppercase tracking-[0.14em] text-slate-300">Account</span>
              </button>
            </div>
          </div>
        </section>

        <section class="mt-6 space-y-6">
          <div
            v-if="showPendingApprovalBanner"
            class="border border-amber-500/40 bg-amber-900/15 px-4 py-4 text-sm text-amber-100"
          >
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-300">Pending Admin Approval</p>
            <p class="mt-2">
              Your KYC credentials have been submitted and are currently under admin review.
              Approval typically takes <strong>{{ approvalTimeline }}</strong>.
            </p>
            <p class="mt-1 text-amber-200/90">
              You will receive an email update once verification is completed.
            </p>
          </div>

          <div v-if="loading" class="border border-slate-700 bg-slate-900 px-4 py-4 text-sm text-slate-300">Loading dashboard...</div>
          <div v-else-if="error" class="border border-red-700/40 bg-red-900/20 px-4 py-4 text-sm text-red-200">{{ error }}</div>

          <template v-if="activeTab === 'overview' || activeTab === 'analytics'">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              <article
                v-for="item in summaryPanels"
                :key="item.label"
                class="border border-slate-700 bg-slate-800 px-4 py-4 transition-colors hover:border-amber-500/70"
              >
                <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">{{ item.label }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-100">{{ item.value }}</p>
                <div class="mt-3 border-t border-slate-700 pt-2">
                  <p class="text-[11px] uppercase tracking-[0.15em] text-emerald-300">{{ item.trend }}</p>
                </div>
              </article>
            </div>

            <section class="border border-slate-700 bg-slate-900">
              <div class="border-b border-slate-700 px-4 py-3">
                <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-100">Active Listings</h2>
              </div>
              <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                  <thead class="bg-slate-800/80 text-[11px] uppercase tracking-[0.14em] text-slate-400">
                    <tr>
                      <th class="px-4 py-3">Property</th>
                      <th class="px-4 py-3">Location</th>
                      <th class="px-4 py-3">Value</th>
                      <th class="px-4 py-3">Status</th>
                      <th class="px-4 py-3">Inquiries</th>
                      <th class="px-4 py-3">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-800 text-slate-200">
                    <tr v-if="tabLoading.listings">
                      <td colspan="6" class="px-4 py-4 text-slate-400">Loading listings...</td>
                    </tr>
                    <tr v-else-if="filteredListings.length === 0">
                      <td colspan="6" class="px-4 py-4 text-slate-400">No listings found.</td>
                    </tr>
                    <tr
                      v-else
                      v-for="item in filteredListings.slice(0, 8)"
                      :key="item.property_id"
                      class="transition-colors hover:bg-slate-800/70"
                    >
                      <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                          <img
                            :src="listingPrimaryImage(item)"
                            :alt="item.title || 'Listing'"
                            class="h-14 w-20 border border-slate-700 object-cover"
                          />
                          <button type="button" class="text-left font-medium hover:text-amber-200" @click="goToPropertyDetails(item)">
                            {{ item.title || 'Listing' }}
                          </button>
                        </div>
                      </td>
                      <td class="px-4 py-3 text-slate-400">{{ [item.city, item.state].filter(Boolean).join(', ') || '-' }}</td>
                      <td class="px-4 py-3">{{ formatMoney(item.price) }}</td>
                      <td class="px-4 py-3">
                        <span class="inline-flex border px-2 py-1 text-[10px] uppercase tracking-[0.12em]" :class="listingStatusClass(item.status)">
                          {{ item.status || 'pending' }}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-slate-400">{{ item.inquiries_count ?? 0 }}</td>
                      <td class="px-4 py-3">
                        <button type="button" class="border border-slate-600 px-2 py-1 text-xs uppercase tracking-[0.14em] hover:border-amber-500/60" @click="goToPropertyDetails(item)">
                          View
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            <section class="border border-slate-700 bg-slate-900 px-4 py-4">
              <div class="mb-4 flex items-center justify-between border-b border-slate-700 pb-3">
                <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-100">Deal Pipeline</h2>
                <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">Offer to Closing</p>
              </div>
              <div class="grid gap-3 lg:grid-cols-4">
                <article v-for="stage in stageOrder" :key="stage" class="border border-slate-700 bg-slate-800 px-3 py-3">
                  <p class="text-[10px] uppercase tracking-[0.16em] text-amber-200/80">{{ stage }}</p>
                  <div class="mt-2 space-y-2">
                    <div
                      v-for="deal in pipelineByStage[stage].slice(0, 3)"
                      :key="deal.key"
                      class="border border-slate-600 bg-slate-900/70 px-2 py-2 text-xs"
                    >
                      <img :src="deal.image" :alt="deal.propertyTitle || 'Deal property'" class="h-20 w-full border border-slate-700 object-cover" />
                      <p class="mt-2 text-[11px] text-slate-300">{{ deal.propertyTitle || 'Property' }}</p>
                      <p class="font-semibold text-slate-200">{{ deal.buyer }}</p>
                      <p class="mt-1 text-slate-400">{{ deal.value }}</p>
                      <p class="mt-1 text-[10px] uppercase tracking-[0.12em] text-slate-500">Deadline {{ deal.deadline }}</p>
                    </div>
                    <p v-if="pipelineByStage[stage].length === 0" class="text-xs text-slate-500">No deals in stage.</p>
                  </div>
                </article>
              </div>
            </section>

            <section class="border border-slate-700 bg-slate-900 px-4 py-4">
              <div class="mb-4 flex flex-wrap items-center justify-between gap-4 border-b border-slate-700 pb-3">
                <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-100">Commission Overview</h2>
                <div class="grid gap-2 text-[11px] uppercase tracking-[0.12em] text-slate-300 md:grid-cols-3">
                  <p>This Month: {{ formatMoney(earnings.total_earnings) }}</p>
                  <p>Pending: {{ formatMoney(earnings.total_pending) }}</p>
                  <p>Lifetime: {{ formatMoney(dashboard.summary.financials.total_agent_earnings) }}</p>
                </div>
              </div>
              <div class="border border-slate-700 bg-slate-950 px-3 py-4">
                <svg viewBox="0 0 360 110" class="h-28 w-full">
                  <line x1="0" y1="10" x2="360" y2="10" stroke="#1e293b" stroke-width="1" />
                  <line x1="0" y1="55" x2="360" y2="55" stroke="#1e293b" stroke-width="1" />
                  <line x1="0" y1="100" x2="360" y2="100" stroke="#1e293b" stroke-width="1" />
                  <polyline :points="chartPoints" fill="none" stroke="#c6a75e" stroke-width="2" />
                </svg>
              </div>
            </section>

            <section v-if="activeTab === 'analytics'" class="border border-slate-700 bg-slate-900 px-4 py-4">
              <div class="mb-4 flex items-center justify-between border-b border-slate-700 pb-3">
                <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-100">Property Performance Snapshots</h2>
                <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">Visual analytics</p>
              </div>
              <div v-if="filteredListings.length === 0" class="text-sm text-slate-400">No listings available for analytics.</div>
              <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                  v-for="item in filteredListings.slice(0, 6)"
                  :key="`analytics-${item.property_id || item.id}`"
                  class="border border-slate-700 bg-slate-800/70 p-3"
                >
                  <img :src="listingPrimaryImage(item)" :alt="item.title || 'Property'" class="h-32 w-full border border-slate-700 object-cover" />
                  <p class="mt-2 text-sm font-semibold text-slate-100">{{ item.title || 'Listing' }}</p>
                  <p class="mt-1 text-xs text-slate-400">{{ [item.city, item.state].filter(Boolean).join(', ') || '-' }}</p>
                  <p class="mt-1 text-xs text-amber-200">{{ formatMoney(item.price) }}</p>
                  <button type="button" class="mt-2 border border-slate-600 px-2 py-1 text-[10px] uppercase tracking-[0.12em] hover:border-amber-500/60" @click="goToPropertyDetails(item)">
                    View Full Details
                  </button>
                </article>
              </div>
            </section>
          </template>

          <section v-else-if="activeTab === 'listings'" class="border border-slate-700 bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-700 px-4 py-3">
              <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-100">Listings</h2>
              <RouterLink to="/marketplace/agent" class="border border-amber-500/50 px-3 py-2 text-xs uppercase tracking-[0.16em] text-amber-200 hover:bg-amber-500/10">
                Open Marketplace
              </RouterLink>
            </div>
            <div class="divide-y divide-slate-800">
              <div v-if="tabLoading.listings" class="px-4 py-4 text-sm text-slate-400">Loading listings...</div>
              <div v-else-if="tabError.listings" class="px-4 py-4 text-sm text-red-300">{{ tabError.listings }}</div>
              <div v-else-if="filteredListings.length === 0" class="px-4 py-4 text-sm text-slate-400">No listings yet.</div>
              <div v-else v-for="item in filteredListings" :key="item.property_id" class="flex items-center justify-between gap-3 px-4 py-4">
                <div class="flex items-center gap-3">
                  <img :src="listingPrimaryImage(item)" :alt="item.title || 'Listing'" class="h-14 w-20 border border-slate-700 object-cover" />
                  <div>
                  <p class="text-sm font-semibold text-slate-100">{{ item.title || 'Listing' }}</p>
                  <p class="text-xs text-slate-400">{{ [item.city, item.state].filter(Boolean).join(', ') }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-sm text-slate-300">{{ formatMoney(item.price) }}</span>
                  <button type="button" class="border border-slate-600 px-2 py-1 text-xs uppercase tracking-[0.12em] hover:border-amber-500/60" @click="goToPropertyDetails(item)">
                    View
                  </button>
                </div>
              </div>
            </div>
          </section>

          <section v-else-if="activeTab === 'deals'" class="border border-slate-700 bg-slate-900">
            <div class="border-b border-slate-700 px-4 py-3">
              <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-100">Deals</h2>
            </div>
            <div class="divide-y divide-slate-800">
              <div v-if="recentBookings.length === 0" class="px-4 py-4 text-sm text-slate-400">No deals yet.</div>
              <div v-else v-for="item in recentBookings" :key="item.key" class="flex items-center justify-between gap-4 px-4 py-4">
                <div class="flex items-center gap-3">
                  <img :src="item.image" :alt="item.title || 'Deal property'" class="h-16 w-20 border border-slate-700 object-cover" />
                  <div>
                  <p class="text-sm font-semibold text-slate-100">{{ item.title }}</p>
                  <p class="text-xs text-slate-400">{{ item.meta }}</p>
                  </div>
                </div>
                <div class="text-right text-xs uppercase tracking-[0.12em] text-slate-300">
                  <p>{{ item.status }}</p>
                  <p class="mt-1 text-slate-500">{{ item.date }}</p>
                </div>
              </div>
            </div>
          </section>

          <section v-else-if="activeTab === 'leads'" class="border border-slate-700 bg-slate-900">
            <div class="border-b border-slate-700 px-4 py-3">
              <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-100">Leads</h2>
            </div>
            <div class="divide-y divide-slate-800">
              <div v-if="tabLoading.buyers" class="px-4 py-4 text-sm text-slate-400">Loading leads...</div>
              <div v-else-if="tabError.buyers" class="px-4 py-4 text-sm text-red-300">{{ tabError.buyers }}</div>
              <div v-else-if="buyers.length === 0" class="px-4 py-4 text-sm text-slate-400">No leads yet.</div>
              <div v-else v-for="buyer in buyers" :key="buyer.buyer_id" class="flex items-center justify-between px-4 py-4">
                <div>
                  <p class="text-sm font-semibold text-slate-100">{{ buyer.full_name || 'Buyer' }}</p>
                  <p class="text-xs text-slate-400">{{ buyer.email || '-' }}</p>
                </div>
                <p class="text-xs uppercase tracking-[0.12em] text-slate-400">{{ buyer.kyc_verified || 'unknown' }}</p>
              </div>
            </div>
          </section>

          <section v-else-if="activeTab === 'commissions'" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-3">
              <article class="border border-slate-700 bg-slate-900 px-4 py-4">
                <p class="text-[10px] uppercase tracking-[0.18em] text-slate-400">Earnings This Month</p>
                <p class="mt-2 text-xl font-semibold text-slate-100">{{ formatMoney(earnings.total_earnings) }}</p>
              </article>
              <article class="border border-slate-700 bg-slate-900 px-4 py-4">
                <p class="text-[10px] uppercase tracking-[0.18em] text-slate-400">Pending Payouts</p>
                <p class="mt-2 text-xl font-semibold text-amber-200">{{ formatMoney(earnings.total_pending) }}</p>
              </article>
              <article class="border border-slate-700 bg-slate-900 px-4 py-4">
                <p class="text-[10px] uppercase tracking-[0.18em] text-slate-400">Lifetime Commission</p>
                <p class="mt-2 text-xl font-semibold text-emerald-300">{{ formatMoney(dashboard.summary.financials.total_agent_earnings) }}</p>
              </article>
            </div>
            <div class="border border-slate-700 bg-slate-900">
              <div class="border-b border-slate-700 px-4 py-3">
                <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-100">Commission Entries</h2>
              </div>
              <div class="divide-y divide-slate-800">
                <div v-if="tabLoading.earnings" class="px-4 py-4 text-sm text-slate-400">Loading commissions...</div>
                <div v-else-if="commissions.length === 0" class="px-4 py-4 text-sm text-slate-400">No commission records yet.</div>
                <div v-else v-for="item in commissions" :key="`${item.transaction_id}-commission`" class="flex items-center justify-between px-4 py-4">
                  <p class="text-sm text-slate-200">Txn {{ item.transaction_id }}</p>
                  <p class="text-sm font-semibold text-slate-100">{{ formatMoney(item.agent_share) }}</p>
                </div>
              </div>
            </div>
          </section>

          <section v-else-if="activeTab === 'kyc'" class="space-y-4">
            <div class="border border-slate-700 bg-slate-900 px-4 py-4">
              <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-100">KYC Status</h2>
              <div class="mt-3 text-sm text-slate-300">
                <p v-if="tabLoading.kyc">Loading KYC status...</p>
                <p v-else-if="tabError.kyc" class="text-red-300">{{ tabError.kyc }}</p>
                <div v-else-if="kycDetails" class="space-y-1">
                  <p>Status: {{ kycDetails.status || 'pending' }}</p>
                  <p>Business Reg No: {{ kycDetails.business_reg_no || '-' }}</p>
                  <p>Address: {{ [kycDetails.address, kycDetails.city, kycDetails.state, kycDetails.country].filter(Boolean).join(', ') || '-' }}</p>
                </div>
                <p v-else>No KYC record found.</p>
              </div>
            </div>
          </section>
        </section>
      </main>
    </div>

    <div
      v-if="propertyDetail.open"
      class="fixed inset-0 z-40 flex items-center justify-center bg-black/80 p-4"
      @click.self="closePropertyDetails"
    >
      <div class="w-full max-w-4xl border border-slate-700 bg-slate-900 text-slate-200">
        <div class="flex items-center justify-between border-b border-slate-700 px-4 py-3">
          <h3 class="text-sm font-semibold uppercase tracking-[0.16em]">Property Details</h3>
          <button type="button" class="text-xs uppercase tracking-[0.12em] text-slate-400 hover:text-slate-100" @click="closePropertyDetails">Close</button>
        </div>
        <div class="space-y-4 px-4 py-4 text-sm">
          <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
            <img
              v-for="(img, index) in listingImages(propertyDetail.item)"
              :key="`detail-${img}-${index}`"
              :src="img"
              :alt="`Property image ${index + 1}`"
              class="h-28 w-full border border-slate-700 object-cover"
            />
          </div>
          <div class="grid gap-3 md:grid-cols-2">
            <p>Title: {{ propertyDetail.item?.title || '-' }}</p>
            <p>Price: {{ formatMoney(propertyDetail.item?.price) }}</p>
            <p>Status: {{ propertyDetail.item?.status || '-' }}</p>
            <p>Type: {{ propertyDetail.item?.property_type || '-' }}</p>
            <p class="md:col-span-2">Description: {{ propertyDetail.item?.description || '-' }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api from '@/lib/api'

const loading = ref(false)
const error = ref('')
const activeTab = ref('overview')
const router = useRouter()

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

const propertyImagePreviewUrls = ref([])
const dashboardSearch = ref('')
const propertyDetail = reactive({
  open: false,
  item: null
})

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

const navItems = [
  { key: 'overview', label: 'Dashboard', icon: 'M4 11.5 12 4l8 7.5v8H4z' },
  { key: 'listings', label: 'Listings', icon: 'M4 6h16M4 12h16M4 18h16' },
  { key: 'deals', label: 'Deals', icon: 'M4 7h7v10H4zM13 10h7v7h-7z' },
  { key: 'leads', label: 'Leads', icon: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 0 0z' },
  { key: 'commissions', label: 'Commissions', icon: 'M12 1v22M17 5H9a3 3 0 1 0 0 6h6a3 3 0 1 1 0 6H6' },
  { key: 'analytics', label: 'Analytics', icon: 'M5 18V9M12 18V5M19 18v-7' },
  { key: 'settings', label: 'Settings', icon: 'M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7zM19.4 15a1 1 0 0 0 .2 1.1l.1.1a1 1 0 0 1 0 1.4l-1.2 1.2a1 1 0 0 1-1.4 0l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a1 1 0 0 1-1 1h-1.6a1 1 0 0 1-1-1v-.1a1 1 0 0 0-.7-1 1 1 0 0 0-1 .2l-.1.1a1 1 0 0 1-1.4 0l-1.2-1.2a1 1 0 0 1 0-1.4l.1-.1a1 1 0 0 0 .2-1 1 1 0 0 0-.9-.7H4a1 1 0 0 1-1-1v-1.6a1 1 0 0 1 1-1h.1a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a1 1 0 0 1 0-1.4l1.2-1.2a1 1 0 0 1 1.4 0l.1.1a1 1 0 0 0 1.1.2 1 1 0 0 0 .6-.9V4a1 1 0 0 1 1-1h1.6a1 1 0 0 1 1 1v.1a1 1 0 0 0 .6.9 1 1 0 0 0 1.1-.2l.1-.1a1 1 0 0 1 1.4 0l1.2 1.2a1 1 0 0 1 0 1.4l-.1.1a1 1 0 0 0-.2 1 1 1 0 0 0 .9.7H20a1 1 0 0 1 1 1v1.6a1 1 0 0 1-1 1h-.1a1 1 0 0 0-.5.8z' }
]

const pageTitle = computed(() => {
  const item = navItems.find((entry) => entry.key === activeTab.value)
  return item ? item.label : 'Dashboard'
})

const agentInitial = computed(() => {
  const name = dashboard.agent_info.agency_name || dashboard.agent_info.email || 'A'
  return name.charAt(0).toUpperCase()
})

const approvalTimeline = '3-4 working days'

const showPendingApprovalBanner = computed(() => {
  const errorText = String(error.value || '').toLowerCase()
  const dashboardKyc = String(dashboard.agent_info.kyc_verified || '').toLowerCase()
  const kycStatus = String(kycDetails.value?.status || '').toLowerCase()

  if (errorText.includes('kyc not verified')) return true
  if (['pending', 'not_verified', 'rejected'].includes(dashboardKyc)) return true
  if (['pending', 'rejected'].includes(kycStatus)) return true
  return false
})

const summaryPanels = computed(() => {
  return [
    { label: 'Active Listings', value: String(dashboard.summary.properties.available ?? 0), trend: '+4.2% vs last month' },
    { label: 'Closed Deals', value: String(earnings.completed_transactions ?? 0), trend: '+2.1% vs last month' },
    { label: 'Pending Commission', value: formatMoney(earnings.total_pending), trend: 'Awaiting payout' },
    { label: 'Monthly Volume', value: formatMoney(dashboard.summary.financials.total_sales), trend: '+6.8% vs last month' }
  ]
})

const filteredListings = computed(() => {
  const query = String(dashboardSearch.value || '').toLowerCase()
  if (!query) return listings.value
  return listings.value.filter((item) => {
    const haystack = `${item?.title || ''} ${item?.city || ''} ${item?.state || ''} ${item?.property_type || ''}`.toLowerCase()
    return haystack.includes(query)
  })
})

const stageOrder = ['Offer', 'Negotiation', 'Legal', 'Closing']

const recentBookings = computed(() => {
  return (dashboard.recent_bookings || []).map((b, index) => ({
    key: `booking-${index}-${b.id}`,
    propertyId: Number(b.property_id || b.id || 0),
    title: b.property_title || 'Property visit',
    propertyTitle: b.property_title || 'Property',
    meta: `${b.fullname || 'Client'} - ${b.email || ''}`.trim(),
    date: formatDate(b.visit_date || b.created_at),
    status: b.status || 'pending',
    image: resolveBookingImage(b)
  }))
})

const pipelineDeals = computed(() => {
  return recentBookings.value.map((item, index) => {
    const stage = inferDealStage(item.status, index)
    return {
      key: item.key,
      stage,
      propertyTitle: item.propertyTitle,
      image: item.image,
      buyer: item.meta || 'Client',
      value: formatMoney(salesHistory.value[index]?.agent_share || salesHistory.value[index]?.amount || 0),
      deadline: item.date || '-'
    }
  })
})

const pipelineByStage = computed(() => {
  const grouped = {
    Offer: [],
    Negotiation: [],
    Legal: [],
    Closing: []
  }
  pipelineDeals.value.forEach((deal) => {
    grouped[deal.stage].push(deal)
  })
  return grouped
})

const chartPoints = computed(() => {
  const data = salesHistory.value.slice(0, 6).map((item) => toNumber(item.agent_share || item.amount))
  if (!data.length) return '10,90 80,78 150,70 220,60 290,48 350,40'
  const max = Math.max(...data, 1)
  return data
    .map((value, index) => {
      const x = 10 + index * 68
      const y = 100 - Math.round((value / max) * 70)
      return `${x},${y}`
    })
    .join(' ')
})

function inferDealStage(status, index) {
  const normalized = String(status || '').toLowerCase()
  if (normalized.includes('pending') || normalized.includes('new')) return 'Offer'
  if (normalized.includes('approve') || normalized.includes('process')) return 'Negotiation'
  if (normalized.includes('legal') || normalized.includes('review')) return 'Legal'
  if (normalized.includes('complete') || normalized.includes('closed')) return 'Closing'
  return stageOrder[index % stageOrder.length]
}

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
    // fall back to comma separated format
  }
  return raw.split(',').map((entry) => entry.trim()).filter(Boolean)
}

function listingImages(item) {
  const images = toImageArray(item?.images)
  const thumbnail = item?.thumbnail ? [item.thumbnail] : (item?.image ? [item.image] : [])
  const merged = [...thumbnail, ...images].filter(Boolean)
  const unique = [...new Set(merged)]
  return unique.length ? unique.map(normalizeImage) : [normalizeImage('')]
}

function listingPrimaryImage(item) {
  return listingImages(item)[0]
}

function resolvePropertyId(item) {
  return Number(item?.property_id || item?.id || 0)
}

function findListingById(propertyId) {
  if (!propertyId) return null
  return listings.value.find((entry) => resolvePropertyId(entry) === Number(propertyId)) || null
}

function resolveBookingImage(booking) {
  const direct = booking?.thumbnail || booking?.image || booking?.property_image
  if (direct) return normalizeImage(direct)

  const fromList = findListingById(Number(booking?.property_id || booking?.id || 0))
  if (fromList) return listingPrimaryImage(fromList)

  const byTitle = listings.value.find((entry) => String(entry?.title || '').trim() === String(booking?.property_title || '').trim())
  if (byTitle) return listingPrimaryImage(byTitle)

  return normalizeImage('')
}

function goToPropertyDetails(item) {
  const propertyId = resolvePropertyId(item)
  if (!propertyId) return
  router.push(`/property/${propertyId}`)
}

function closePropertyDetails() {
  propertyDetail.open = false
  propertyDetail.item = null
}

function listingStatusClass(status) {
  const normalized = String(status || '').toLowerCase()
  if (normalized.includes('sold') || normalized.includes('approved') || normalized.includes('closed')) {
    return 'border-emerald-500/60 text-emerald-300'
  }
  if (normalized.includes('pending') || normalized.includes('review')) {
    return 'border-amber-500/70 text-amber-200'
  }
  return 'border-amber-200/50 text-amber-100'
}

function handleNav(tab) {
  if (tab === 'settings') {
    router.push('/settings/agent')
    return
  }
  activeTab.value = tab
  if (tab === 'listings' || tab === 'overview' || tab === 'analytics') loadTabData('listings')
  if (tab === 'leads') loadTabData('buyers')
  if (tab === 'commissions' || tab === 'deals' || tab === 'overview' || tab === 'analytics') loadTabData('earnings')
  if (tab === 'kyc') loadTabData('kyc')
}

function openNotifications() {
  router.push('/settings/agent?section=notifications')
}

function openAccountSettings() {
  router.push('/settings/agent?section=profile')
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

onMounted(async () => {
  await loadDashboard()
  await Promise.all([loadTabData('listings'), loadTabData('buyers'), loadTabData('earnings'), loadTabData('kyc')])
})

onBeforeUnmount(() => {
  propertyImagePreviewUrls.value.forEach((url) => URL.revokeObjectURL(url))
})
</script>

