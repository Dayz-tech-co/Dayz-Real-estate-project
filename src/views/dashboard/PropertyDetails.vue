<template>
  <div class="min-h-screen bg-theme text-white">
    <section class="layout-content-container px-6 py-12">
      <div class="mb-8">
        <button
          type="button"
          class="text-xs uppercase tracking-widest border border-white/20 px-4 py-2 hover:bg-white/10"
          @click="goBack"
        >
          Back
        </button>
      </div>

      <div v-if="loading" class="text-white/70">Loading property details...</div>
      <div v-else-if="error" class="text-red-400">{{ error }}</div>
      <div v-else-if="!property" class="text-white/70">Property not found.</div>

      <div v-else class="grid gap-8 lg:grid-cols-[1.25fr,0.75fr]">
        <div class="space-y-4">
          <div class="bg-white/5 border border-white/10">
            <img :src="mainImage" :alt="property.title || 'Property'" class="w-full h-[420px] object-cover" />
          </div>
          <div v-if="images.length > 1" class="grid grid-cols-4 gap-3">
            <button
              v-for="(img, index) in images"
              :key="`${img}-${index}`"
              type="button"
              class="border border-white/10 bg-white/5"
              @click="activeImage = img"
            >
              <img :src="img" :alt="`Property image ${index + 1}`" class="h-24 w-full object-cover" />
            </button>
          </div>
        </div>

        <aside class="bg-emerald-950/80 border border-white/10 p-6 space-y-4">
          <p class="text-xs uppercase tracking-widest text-white/60">{{ property.property_type || 'Property' }}</p>
          <h1 class="font-display text-3xl">{{ property.title || 'Property details' }}</h1>
          <p class="text-2xl font-display">{{ formatMoney(property.price) }}</p>
          <p class="text-sm text-white/70">{{ property.location || [property.city, property.state].filter(Boolean).join(', ') }}</p>

          <div class="grid grid-cols-3 gap-3 text-[11px] uppercase tracking-widest text-white/70 pt-3">
            <div>Beds<br /><span class="text-white">{{ property.bed || '-' }}</span></div>
            <div>Baths<br /><span class="text-white">{{ property.bath || '-' }}</span></div>
            <div>Size<br /><span class="text-white">{{ property.asize || '-' }}</span></div>
          </div>

          <div class="pt-3 border-t border-white/10">
            <p class="text-xs uppercase tracking-widest text-white/60 mb-2">Description</p>
            <p class="text-sm text-white/80 whitespace-pre-line">{{ property.description || 'No description provided.' }}</p>
          </div>

          <div class="pt-3 border-t border-white/10">
            <p class="text-xs uppercase tracking-widest text-white/60 mb-2">Agent</p>
            <p class="text-sm text-white">{{ agent?.agency_name || '-' }}</p>
            <p class="text-sm text-white/70">{{ agent?.email || '-' }}</p>
            <p class="text-sm text-white/70">{{ agent?.phoneno || '-' }}</p>
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

function formatMoney(value) {
  const amount = Number(value || 0)
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    maximumFractionDigits: 0
  }).format(amount)
}

function goBack() {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/dashboard/user')
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
    } else {
      error.value = res.data?.text || 'Unable to load property details.'
    }
  } catch (err) {
    error.value = err?.response?.data?.text || 'Unable to load property details.'
  } finally {
    loading.value = false
  }
}

onMounted(loadPropertyDetails)
</script>
