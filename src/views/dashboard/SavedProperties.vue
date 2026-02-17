<template>
  <div class="min-h-screen bg-theme text-white">
    <section class="layout-content-container px-6 py-12">
      <div class="flex items-center justify-between mb-8">
        <div>
          <p class="uppercase tracking-[0.3em] text-xs text-white/60">Saved Properties</p>
          <h1 class="font-display text-3xl">Your Wishlist</h1>
        </div>
        <RouterLink to="/" class="text-xs uppercase tracking-widest border border-white/20 px-4 py-2 hover:bg-white/10">
          Back to Home
        </RouterLink>
      </div>

      <div v-if="loading" class="text-white/70">Loading saved properties...</div>
      <div v-else-if="error" class="text-red-400">{{ error }}</div>
      <div v-else-if="properties.length === 0" class="text-white/70">No saved properties yet.</div>

      <div v-else class="grid gap-8 md:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="property in properties"
          :key="property.id"
          class="bg-white shadow-xl border border-black/5 hover:shadow-2xl transition-shadow cursor-pointer"
          @click="openProperty(property.id)"
        >
          <div class="relative">
            <img :src="property.image" :alt="property.title" class="h-48 w-full object-cover" />
            <button
              class="absolute bottom-3 right-3 h-9 w-9 rounded-full bg-white/90 text-emerald-950 border border-black/10 flex items-center justify-center"
              @click.stop="remove(property.id)"
              aria-label="Remove from wishlist"
            >
              <span class="text-lg">♥</span>
            </button>
          </div>
          <div class="p-5 text-emerald-950">
            <div class="flex items-end justify-between">
              <h3 class="font-display text-2xl">₦ {{ formatPrice(property.price) }}</h3>
              <span class="text-xs text-emerald-900/60">{{ property.typeLabel }}</span>
            </div>
            <p class="text-sm text-emerald-900/70 mt-2">{{ property.address }}</p>
            <div class="grid grid-cols-3 gap-3 text-[10px] uppercase tracking-widest text-emerald-900/50 mt-4">
              <div>Bedrooms<br /><span class="text-emerald-900">{{ property.beds }}</span></div>
              <div>Baths<br /><span class="text-emerald-900">{{ property.baths }}</span></div>
              <div>Space<br /><span class="text-emerald-900">{{ property.size }}</span></div>
            </div>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import api from '@/lib/api'

const loading = ref(false)
const error = ref('')
const properties = ref([])
const router = useRouter()

const formatPrice = (value) => new Intl.NumberFormat().format(Number(value || 0))

const normalizeImage = (path) => {
  if (!path) return '/uploads/properties/1761862624_DJI_0253-2-scaled.webp'
  if (path.startsWith('http')) return path
  if (path.startsWith('/')) return path
  return `/${path}`
}

async function loadWishlist() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.post('/api/users/Properties/wishlist_get.php')
    if (res.data?.status) {
      properties.value = (res.data?.data || []).map((p) => ({
        id: p.id,
        title: p.title,
        price: Number(p.price || 0),
        address: p.location || `${p.city || ''} ${p.state || ''}`.trim(),
        beds: p.bed || '—',
        baths: p.bath || '—',
        size: p.asize || '—',
        typeLabel: p.property_type || 'Estate',
        image: normalizeImage(p.thumbnail || (p.images && p.images[0]))
      }))
    } else {
      properties.value = []
      error.value = res.data?.text || 'Unable to load wishlist.'
    }
  } catch (err) {
    error.value = err?.response?.data?.text || 'Unable to load wishlist.'
  } finally {
    loading.value = false
  }
}

async function remove(id) {
  const payload = new FormData()
  payload.append('property_id', id)
  await api.post('/api/users/Properties/wishlist_remove.php', payload)
  properties.value = properties.value.filter((item) => item.id !== id)
}

function openProperty(id) {
  const propertyId = Number(id)
  if (!propertyId) return
  router.push(`/property/${propertyId}`)
}

onMounted(loadWishlist)
</script>
