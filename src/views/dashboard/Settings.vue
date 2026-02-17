<template>
  <div class="min-h-screen bg-theme text-white">
    <section class="layout-content-container px-6 py-12 space-y-8">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs uppercase tracking-[0.3em] text-white/60">Settings</p>
          <h1 class="font-display text-3xl">Account Preferences</h1>
          <p class="text-sm text-white/60 mt-2">
            {{ isAgent ? 'Agent account configuration' : 'User account configuration' }}
          </p>
        </div>
        <button
          type="button"
          class="border border-white/20 px-4 py-2 text-xs uppercase tracking-widest hover:bg-white/10"
          @click="reloadAll"
        >
          Refresh
        </button>
      </div>

      <div class="grid gap-8 lg:grid-cols-[1.2fr,0.8fr] items-start">
        <div class="space-y-8">
          <div class="rounded-lg border border-white/10 bg-emerald-950/80 p-6">
            <div class="mb-6">
              <p class="text-xs uppercase tracking-widest text-white/60">Profile</p>
              <h2 class="font-display text-2xl">
                {{ isAgent ? 'Agency Profile' : 'Verified Account Details' }}
              </h2>
            </div>

            <div v-if="loadingProfile" class="text-sm text-white/60">
              Loading {{ isAgent ? 'agency' : 'user' }} profile...
            </div>

            <div v-else-if="isAgent" class="grid gap-3 md:grid-cols-2">
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">Agency Name</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.agency_name) }}</p>
              </div>
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">Email</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.email) }}</p>
              </div>
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">Phone</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.phoneno) }}</p>
              </div>
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">KYC</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.kyc_verified) }}</p>
              </div>
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm md:col-span-2">
                <p class="text-[10px] uppercase tracking-widest text-white/50">Business Address</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.business_address) }}</p>
              </div>
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">City</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.city) }}</p>
              </div>
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">State</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.state) }}</p>
              </div>
            </div>

            <div v-else-if="isVerifiedUser" class="grid gap-3 md:grid-cols-2">
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">First Name</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.fname) }}</p>
              </div>
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">Last Name</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.lname) }}</p>
              </div>
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">Email</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.email) }}</p>
              </div>
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">Phone</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.phoneno) }}</p>
              </div>
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">Country</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.country) }}</p>
              </div>
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">State</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.state) }}</p>
              </div>
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">City</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.city) }}</p>
              </div>
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">Street</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.streetname) }}</p>
              </div>
              <div class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm">
                <p class="text-[10px] uppercase tracking-widest text-white/50">Postal Code</p>
                <p class="mt-1 text-white">{{ valueOrDash(profile.postal_code) }}</p>
              </div>
            </div>

            <div v-else class="rounded border border-white/10 bg-white/5 px-4 py-3 text-sm text-white/70">
              Profile details are shown here after account verification is completed.
            </div>

            <p v-if="profileError" class="mt-4 text-sm text-red-300">{{ profileError }}</p>
          </div>

          <div class="rounded-lg border border-white/10 bg-emerald-950/80 p-6">
            <div class="mb-6">
              <p class="text-xs uppercase tracking-widest text-white/60">Preferences</p>
              <h2 class="font-display text-2xl">App Experience</h2>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
              <label class="space-y-2 text-sm">
                <span class="text-white/70">Language</span>
                <select v-model="prefs.language" class="form-select h-11 w-full bg-white/10 border-white/10 px-3">
                  <option value="en">English</option>
                  <option value="fr">French</option>
                  <option value="es">Spanish</option>
                </select>
              </label>
              <label class="space-y-2 text-sm">
                <span class="text-white/70">Currency</span>
                <select v-model="prefs.currency" class="form-select h-11 w-full bg-white/10 border-white/10 px-3">
                  <option value="NGN">NGN</option>
                  <option value="USD">USD</option>
                  <option value="GBP">GBP</option>
                </select>
              </label>
              <label class="flex items-center gap-3 text-sm">
                <input v-model="prefs.emailUpdates" type="checkbox" />
                <span>Email Updates</span>
              </label>
              <label class="flex items-center gap-3 text-sm">
                <input v-model="prefs.smsUpdates" type="checkbox" />
                <span>SMS Updates</span>
              </label>
            </div>

            <p class="mt-4 text-xs text-white/50">
              Preferences are saved locally on this device.
            </p>
          </div>
        </div>

        <aside class="space-y-8">
          <div class="rounded-lg border border-white/10 bg-emerald-950/80 p-6">
            <div class="mb-6 flex items-center justify-between">
              <div>
                <p class="text-xs uppercase tracking-widest text-white/60">Notifications</p>
                <h2 class="font-display text-2xl">Recent Alerts</h2>
              </div>
              <button
                type="button"
                class="border border-white/20 px-3 py-2 text-xs uppercase tracking-widest hover:bg-white/10 disabled:opacity-50"
                :disabled="unreadIds.length === 0"
                @click="markAllRead"
              >
                Mark All Read
              </button>
            </div>

            <div v-if="loadingNotifs" class="text-sm text-white/60">Loading notifications...</div>
            <div v-else-if="notifError" class="text-sm text-red-300">{{ notifError }}</div>
            <div v-else-if="notifications.length === 0" class="text-sm text-white/60">No notifications yet.</div>

            <div v-else class="space-y-4">
              <article
                v-for="item in notifications"
                :key="item.id"
                class="rounded border border-white/10 bg-emerald-950/60 p-4 cursor-pointer hover:bg-emerald-900/50"
                @click="openNotification(item)"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-[10px] uppercase tracking-widest text-white/50">{{ item.type || 'general' }}</p>
                    <h3 class="mt-1 text-sm font-semibold text-white">{{ item.title }}</h3>
                    <p class="mt-2 text-xs text-white/60">{{ item.message }}</p>
                    <p class="mt-3 text-[10px] uppercase tracking-widest text-white/40">{{ formatDate(item.created_at) }}</p>
                  </div>
                  <span
                    class="rounded border px-2 py-1 text-[10px] uppercase tracking-widest"
                    :class="item.is_read ? 'border-white/20 text-white/50' : 'border-emerald-300/30 text-emerald-200'"
                  >
                    {{ item.is_read ? 'Read' : 'Unread' }}
                  </span>
                </div>

                <div class="mt-4 flex gap-2">
                  <button
                    type="button"
                    class="border border-white/20 px-3 py-2 text-xs uppercase tracking-widest hover:bg-white/10 disabled:opacity-50"
                    :disabled="item.is_read"
                    @click.stop="markRead([item.id])"
                  >
                    Mark Read
                  </button>
                  <button
                    type="button"
                    class="border border-red-300/30 px-3 py-2 text-xs uppercase tracking-widest text-red-200 hover:bg-red-500/10"
                    @click.stop="deleteNotifications([item.id])"
                  >
                    Delete
                  </button>
                </div>
              </article>
            </div>
          </div>
        </aside>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api from '@/lib/api'

const role = ref('user')

const profile = reactive({
  fname: '',
  lname: '',
  email: '',
  phoneno: '',
  country: '',
  state: '',
  city: '',
  streetname: '',
  postal_code: '',
  agency_name: '',
  business_address: '',
  kyc_verified: '',
  status: '',
  emailverified: '',
  phoneverified: ''
})

const prefs = reactive({
  language: 'en',
  currency: 'NGN',
  emailUpdates: true,
  smsUpdates: false
})

const notifications = reactive([])

const state = reactive({
  loadingProfile: false,
  profileError: '',
  loadingNotifs: false,
  notifError: ''
})

const isAgent = computed(() => role.value === 'agent')
const prefsStorageKey = computed(() => (isAgent.value ? 'DAYZ_AGENT_PREFS' : 'DAYZ_USER_PREFS'))
const loadingProfile = computed(() => state.loadingProfile)
const loadingNotifs = computed(() => state.loadingNotifs)
const profileError = computed(() => state.profileError)
const notifError = computed(() => state.notifError)
const isVerifiedUser = computed(() => String(profile.kyc_verified || '').toLowerCase() === 'verified')
const unreadIds = computed(() => notifications.filter((n) => !n.is_read).map((n) => n.id))

function formatDate(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function valueOrDash(value) {
  if (value === undefined || value === null) return '-'
  const text = String(value).trim()
  return text || '-'
}

function loadPrefs() {
  const raw = localStorage.getItem(prefsStorageKey.value)
  if (!raw) return
  try {
    const parsed = JSON.parse(raw)
    Object.assign(prefs, parsed)
  } catch (err) {
    localStorage.removeItem(prefsStorageKey.value)
  }
}

watch(
  () => ({ ...prefs }),
  (value) => {
    localStorage.setItem(prefsStorageKey.value, JSON.stringify(value))
  },
  { deep: true }
)

async function loadUserProfile() {
  const res = await api.post('/api/users/Profile/view_profile.php')
  if (res.data?.status) {
    Object.assign(profile, res.data?.data || {})
    return
  }
  throw new Error(res.data?.text || 'Unable to load profile.')
}

async function loadAgentProfile() {
  const dashboardRes = await api.post('/api/agents/Dashboard/Agent_dashboard.php')
  if (!dashboardRes.data?.status) {
    throw new Error(dashboardRes.data?.text || 'Unable to load agent profile.')
  }

  const agentInfo = dashboardRes.data?.data?.agent_info || {}
  Object.assign(profile, {
    agency_name: agentInfo.agency_name || '',
    email: agentInfo.email || '',
    kyc_verified: agentInfo.kyc_verified || ''
  })

  if (!agentInfo.agency_name) return

  const payload = new FormData()
  payload.append('agency_name', agentInfo.agency_name)
  const profileRes = await api.post('/api/agents/profile/view_profile.php', payload)
  if (profileRes.data?.status) {
    Object.assign(profile, profileRes.data?.data || {})
  }
}

async function loadProfile() {
  state.loadingProfile = true
  state.profileError = ''
  try {
    if (isAgent.value) {
      await loadAgentProfile()
    } else {
      await loadUserProfile()
    }
  } catch (err) {
    state.profileError = err?.response?.data?.text || err?.message || 'Unable to load profile.'
  } finally {
    state.loadingProfile = false
  }
}

function notificationEndpoints() {
  if (isAgent.value) {
    return {
      list: '/api/agents/Notifications/list_all.php',
      read: '/api/agents/Notifications/mark_as_read.php',
      del: '/api/agents/Notifications/delete_notif.php'
    }
  }
  return {
    list: '/api/users/Notifications/list_all.php',
    read: '/api/users/Notifications/mark_as_read.php',
    del: '/api/users/Notifications/delete_notif.php'
  }
}

async function loadNotifications() {
  state.loadingNotifs = true
  state.notifError = ''
  try {
    const form = new FormData()
    form.append('page', '1')
    form.append('limit', '20')
    const endpoints = notificationEndpoints()
    const res = await api.post(endpoints.list, form)
    if (res.data?.status) {
      notifications.splice(0, notifications.length, ...(res.data?.data?.notifications || []))
    } else {
      state.notifError = res.data?.text || 'Unable to load notifications.'
      notifications.splice(0, notifications.length)
    }
  } catch (err) {
    state.notifError = err?.response?.data?.text || 'Unable to load notifications.'
    notifications.splice(0, notifications.length)
  } finally {
    state.loadingNotifs = false
  }
}

function notificationPayload(ids) {
  const form = new FormData()
  ids.forEach((id) => form.append('notification_ids[]', String(id)))
  return form
}

async function markRead(ids) {
  if (!ids.length) return
  try {
    const endpoints = notificationEndpoints()
    const res = await api.post(endpoints.read, notificationPayload(ids))
    if (res.data?.status) {
      notifications.forEach((n) => {
        if (ids.includes(n.id)) n.is_read = true
      })
    }
  } catch (err) {
    // ignore transient failures to avoid UI interruption
  }
}

async function markAllRead() {
  await markRead(unreadIds.value)
}

async function deleteNotifications(ids) {
  if (!ids.length) return
  try {
    const endpoints = notificationEndpoints()
    const res = await api.post(endpoints.del, notificationPayload(ids))
    if (res.data?.status) {
      const remaining = notifications.filter((n) => !ids.includes(n.id))
      notifications.splice(0, notifications.length, ...remaining)
    }
  } catch (err) {
    // ignore transient failures to avoid UI interruption
  }
}

function openNotification(item) {
  if (!item?.id) return
  if (!item.is_read) {
    markRead([item.id])
  }
}

function reloadAll() {
  loadProfile()
  loadNotifications()
}

onMounted(() => {
  const storedRole = localStorage.getItem('USER_ROLE')
  role.value = ['user', 'agent'].includes(storedRole) ? storedRole : 'user'
  loadPrefs()
  loadProfile()
  loadNotifications()
})
</script>
