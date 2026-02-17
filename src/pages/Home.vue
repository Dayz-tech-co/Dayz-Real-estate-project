<template>
  <div class="bg-theme text-white">
    <div class="layout-content-container px-6 py-10">
      <div v-if="showSearch" class="mb-10 bg-emerald-950/80 border border-white/10 rounded-lg p-6">
        <div class="flex flex-wrap items-center gap-4">
          <input
            v-model="search.query"
            type="text"
            placeholder="Search by city, property type, or address"
            class="form-input flex-1 min-w-[220px] h-12 px-4 bg-white/90 text-emerald-950"
          />
          <select v-model="search.type" class="form-select h-12 px-4 bg-white/90 text-emerald-950">
            <option value="">Property Type</option>
            <option value="shortlet">Shortlet</option>
            <option value="apartment">Apartment</option>
            <option value="hotel">Hotel</option>
            <option value="house">House</option>
            <option value="land">Land</option>
            <option value="office">Office</option>
          </select>
          <input
            v-model.number="search.maxBudget"
            type="number"
            min="0"
            placeholder="Max Budget (₦)"
            class="form-input w-[200px] h-12 px-4 bg-white/90 text-emerald-950"
          />
          <button class="h-12 px-6 border border-white/20 text-xs uppercase tracking-widest hover:bg-white/10" @click="applyFilters">
            Apply
          </button>
        </div>
      </div>

      <div class="grid lg:grid-cols-[280px,1fr] gap-10">
        <aside class="bg-emerald-950/80 border border-white/10 p-6 space-y-8 self-start h-fit">
          <div>
            <p class="text-xs uppercase tracking-widest text-white/60 mb-2">Curation</p>
            <h2 class="font-display text-2xl">Nigeria Curated Collection</h2>
            <p class="text-white/60 text-sm mt-2">{{ filteredProperties.length }} exceptional properties available</p>
          </div>

          <div>
            <p class="text-xs uppercase tracking-widest text-white/60 mb-3">Territory</p>
            <div class="bg-white/5 border border-white/10 px-3 py-2 text-sm">Lagos, Abuja, Port Harcourt</div>
          </div>

          <div>
            <p class="text-xs uppercase tracking-widest text-white/60 mb-3">Investment Range</p>
            <div class="h-2 bg-white/10 rounded-full overflow-hidden">
              <div class="h-full bg-white/60 w-1/2"></div>
            </div>
            <div class="flex justify-between text-xs text-white/50 mt-2">
              <span>₦80M</span>
              <span>₦550M+</span>
            </div>
          </div>

          <div>
            <p class="text-xs uppercase tracking-widest text-white/60 mb-3">Property Type</p>
            <div class="space-y-2 text-sm">
              <label class="flex items-center gap-2">
                <input type="checkbox" value="shortlet" v-model="filters.types" /> Shortlet
              </label>
              <label class="flex items-center gap-2">
                <input type="checkbox" value="apartment" v-model="filters.types" /> Apartment
              </label>
              <label class="flex items-center gap-2">
                <input type="checkbox" value="hotel" v-model="filters.types" /> Hotel
              </label>
              <label class="flex items-center gap-2">
                <input type="checkbox" value="house" v-model="filters.types" /> House
              </label>
              <label class="flex items-center gap-2">
                <input type="checkbox" value="land" v-model="filters.types" /> Land
              </label>
              <label class="flex items-center gap-2">
                <input type="checkbox" value="office" v-model="filters.types" /> Office
              </label>
            </div>
          </div>

          <button class="w-full border border-white/20 text-xs uppercase tracking-widest py-3 hover:bg-white/10" @click="applyFilters">
            Apply Filters
          </button>
          <RouterLink
            :to="isAgent ? '/marketplace/agent' : '/saved'"
            class="block w-full text-center border border-white/20 text-xs uppercase tracking-widest py-3 hover:bg-white/10"
          >
            {{ isAgent ? 'Agent Listings' : 'Saved Properties' }}
          </RouterLink>
        </aside>

        <section class="bg-[var(--stone-100)] text-emerald-950 border border-white/20">
          <div class="px-8 py-6 border-b border-black/10 flex items-center justify-between">
            <div>
              <p class="text-xs uppercase tracking-widest text-emerald-900/60">Dayz / Nigeria / Prime Cities</p>
              <h1 class="font-display text-4xl mt-2">Nigeria Prime Collections</h1>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-xs uppercase tracking-widest text-emerald-900/60">Sort by: Curated</span>
              <button class="border border-emerald-900/20 px-3 py-2 text-xs">Grid</button>
            </div>
          </div>

          <div class="p-8 grid gap-8 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="property in visibleProperties" :key="property.clientKey" class="bg-white shadow-xl border border-black/5">
              <div class="relative">
                <RouterLink :to="`/property/${property.propertyId}`">
                  <img :src="property.image" :alt="property.title" class="h-48 w-full object-cover" />
                </RouterLink>
                <span class="absolute top-3 left-3 bg-emerald-950 text-white text-[10px] tracking-widest uppercase px-2 py-1">
                  {{ property.tag }}
                </span>
                <button
                  v-if="!isAgent"
                  class="absolute bottom-3 right-3 h-9 w-9 rounded-full bg-white/90 text-emerald-950 border border-black/10 flex items-center justify-center"
                  @click="toggleSaved(property.propertyId)"
                  aria-label="Save property"
                >
                  <span class="text-lg">{{ isSaved(property.propertyId) ? '♥' : '♡' }}</span>
                </button>
                <span
                  v-else
                  class="absolute bottom-3 right-3 bg-cyan-900 text-white text-[10px] tracking-widest uppercase px-2 py-1"
                >
                  Agent View
                </span>
              </div>
              <div v-if="property.previewImages.length > 0" class="px-4 py-3 border-b border-black/5">
                <div class="grid grid-cols-4 gap-2">
                  <img
                    v-for="(img, index) in visiblePreviewImages(property)"
                    :key="`${property.id}-preview-${index}`"
                    :src="img"
                    :alt="`${property.title} preview ${index + 1}`"
                    class="h-14 w-full object-cover border border-black/10"
                  />
                </div>
                <button
                  v-if="property.previewImages.length > 4"
                  class="mt-3 text-[10px] uppercase tracking-widest text-emerald-900/70 hover:text-emerald-900"
                  @click="togglePreviewExpand(property.clientKey)"
                >
                  {{ isPreviewExpanded(property.clientKey) ? 'Hide' : 'See more photos' }}
                  ({{ property.previewImages.length }})
                </button>
              </div>
              <div class="p-5">
                <div class="flex items-end justify-between">
                  <h3 class="font-display text-2xl">₦ {{ formatPrice(property.price) }}</h3>
                  <span class="text-xs text-emerald-900/60">{{ property.typeLabel }}</span>
                </div>
                <RouterLink :to="`/property/${property.propertyId}`" class="block text-sm text-emerald-900/70 mt-2 hover:text-emerald-900">
                  {{ property.address }}
                </RouterLink>
                <div class="grid grid-cols-3 gap-3 text-[10px] uppercase tracking-widest text-emerald-900/50 mt-4">
                  <div>Bedrooms<br /><span class="text-emerald-900">{{ property.beds }}</span></div>
                  <div>Baths<br /><span class="text-emerald-900">{{ property.baths }}</span></div>
                  <div>Space<br /><span class="text-emerald-900">{{ property.size }}</span></div>
                </div>
              </div>
            </article>
          </div>

          <div class="px-8 pb-10" v-if="showListingsToggle">
            <button
              class="w-full border border-emerald-900/20 text-xs uppercase tracking-widest py-3 hover:bg-emerald-900/5 disabled:opacity-60 disabled:cursor-not-allowed"
              @click="handleListingsToggle"
            >
              {{ isListingsExpanded ? 'Back to Top Listings' : 'See More Listings' }}
            </button>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, RouterLink, useRouter } from 'vue-router'
import api from '@/lib/api'

const route = useRoute()
const router = useRouter()
const isAgent = computed(() => localStorage.getItem('USER_ROLE') === 'agent')

const showSearch = computed(() => route.query.search === '1')

const search = reactive({
  query: '',
  type: '',
  maxBudget: null
})

const filters = reactive({
  types: [],
  activeTypes: []
})

const properties = ref([])
const loading = ref(false)
const error = ref('')

const localImageFiles = [
  '1761861139_DJI_0253-2-scaled.webp',
  '1761861139_House_lagos_images.jpeg',
  '1761861139_swimming_images.jpeg',
  '1761862549_DJI_0253-2-scaled.webp',
  '1761862549_House_lagos_images.jpeg',
  '1761862549_swimming_images.jpeg',
  '1761862624_DJI_0253-2-scaled.webp',
  '1761862624_House_lagos_images.jpeg',
  '1761862624_swimming_images.jpeg',
  '1761863440_DJI_0253-2-scaled.webp',
  '1761863440_House_lagos_images.jpeg',
  '1761863440_swimming_images.jpeg',
  '1761864059_DJI_0253-2-scaled.webp',
  '1761864059_House_lagos_images.jpeg',
  '1761864059_swimming_images.jpeg',
  '1761864433_DJI_0253-2-scaled.webp',
  '1761864433_House_lagos_images.jpeg',
  '1761864433_swimming_images.jpeg',
  '1761865195_DJI_0253-2-scaled.webp',
  '1761865195_House_lagos_images.jpeg',
  '1761865195_swimming_images.jpeg',
  '1762164599_Abuja_house.png',
  '1762164599_Abuja_inner.png',
  '1762164599_Abuja_sitting_room.png',
  '1762164599_Abuja_Toilet.png',
  '1762272158_DJI_0253-2-scaled.webp',
  '1762272158_House_lagos_images.jpeg',
  '1762272158_swimming_images.jpeg',
  '1762334192_Abuja_house.png',
  '1762334192_Abuja_inner.png',
  '1762334192_Abuja_sitting_room.png',
  '1762334192_Abuja_Toilet.png',
  '1762334515_Abuja_house.png',
  '1762334515_Abuja_inner.png',
  '1762334515_Abuja_sitting_room.png',
  '1762334515_Abuja_Toilet.png',
  '1762337053_Abuja_house.png',
  '1762337053_Abuja_inner.png',
  '1762337053_Abuja_sitting_room.png',
  '1762337053_Abuja_Toilet.png',
  '1762337332_DJI_0253-2-scaled.webp',
  '1762337332_House_lagos_images.jpeg',
  '1762337332_swimming_images.jpeg',
  '1762337423_DJI_0253-2-scaled.webp',
  '1762337423_House_lagos_images.jpeg',
  '1762337423_swimming_images.jpeg',
  '1762337670_DJI_0253-2-scaled.webp',
  '1762337670_House_lagos_images.jpeg',
  '1762337670_swimming_images.jpeg',
  '1762337914_DJI_0253-2-scaled.webp',
  '1762337914_House_lagos_images.jpeg',
  '1762337914_swimming_images.jpeg',
  '1765384947_DJI_0253-2-scaled.webp',
  '1765384947_House_lagos_images.jpeg',
  '1765384947_swimming_images.jpeg',
  '1765385043_Abuja_house.png',
  '1765385043_Abuja_inner.png',
  '1765385043_Abuja_sitting_room.png',
  '1765385043_Abuja_Toilet.png',
  '1765385172_Abuja_house.png',
  '1765385172_Abuja_inner.png',
  '1765385172_Abuja_sitting_room.png',
  '1765385172_Abuja_Toilet.png',
  '1765385376_DJI_0253-2-scaled.webp',
  '1765385376_House_lagos_images.jpeg',
  '1765385376_swimming_images.jpeg',
  '1765385440_Abuja_house.png',
  '1765385440_Abuja_inner.png',
  '1765385440_Abuja_sitting_room.png',
  '1765385440_Abuja_Toilet.png',
  '1765387547_DJI_0253-2-scaled.webp',
  '1765387547_House_lagos_images.jpeg',
  '1765387547_swimming_images.jpeg',
  '1765545623_Las_vegas_apt_bedroom.webp',
  '1765545623_Las_vegas_apt_kitch.webp',
  '1765545623_Las_vegas_apt_overview.webp',
  '1765545623_Las_vegas_apt_toilet.webp',
  '1765545623_Las_vegas_inner.webp',
  '1765547677_ibadan_apt_bedroom.jpg',
  '1765547677_ibadan_apt_comp.jpg',
  '1765547677_ibadan_apt_overview.jpg',
  '1765547677_ibadan_inner_view.jpg',
  '1765547677_ibadan_sitting_room_view.jpg',
  '1765547799_ilorin_apt_bedrrom_view.jpg',
  '1765547799_ilorin_apt_overview.jpg',
  '1765547895_ilorin_apt_bedrrom_view.jpg',
  '1765547895_ilorin_apt_overview.jpg',
  '1765547946_ibadan_apt_bedroom.jpg',
  '1765547946_ibadan_apt_comp.jpg',
  '1765547946_ibadan_apt_overview.jpg',
  '1765547946_ibadan_inner_view.jpg',
  '1765547946_ibadan_sitting_room_view.jpg',
  '1765548025_Las_vegas_apt_bedroom.webp',
  '1765548025_Las_vegas_apt_kitch.webp',
  '1765548025_Las_vegas_apt_overview.webp',
  '1765548025_Las_vegas_apt_toilet.webp',
  '1765548025_Las_vegas_inner.webp',
  '1765548482_Las_vegas_apt_bedroom.webp',
  '1765548482_Las_vegas_apt_kitch.webp',
  '1765548482_Las_vegas_apt_overview.webp',
  '1765548482_Las_vegas_apt_toilet.webp',
  '1765548482_Las_vegas_inner.webp',
  '1765550988_Las_vegas_apt_bedroom.webp',
  '1765550988_Las_vegas_apt_kitch.webp',
  '1765550988_Las_vegas_apt_overview.webp',
  '1765550988_Las_vegas_apt_toilet.webp',
  '1765550988_Las_vegas_inner.webp',
  '1765551069_ilorin_apt_bedrrom_view.jpg',
  '1765551069_ilorin_apt_overview.jpg'
]

const wishlistKey = 'DAYZ_WISHLIST'
const savedIds = ref([])

const formatPrice = (value) => new Intl.NumberFormat().format(Number(value || 0))

const normalizeImage = (path) => {
  if (!path) return '/uploads/properties/1761862624_DJI_0253-2-scaled.webp'
  if (path.startsWith('http')) return path
  if (path.startsWith('/')) return path
  return `/${path}`
}

function normalizePropertyType(value) {
  const raw = String(value || '').trim().toLowerCase()
  if (raw === 'shortlet') return { key: 'shortlet', label: 'Shortlet' }
  if (raw === 'apartment') return { key: 'apartment', label: 'Apartment' }
  if (raw === 'hotel') return { key: 'hotel', label: 'Hotel' }
  if (raw === 'house') return { key: 'house', label: 'House' }
  if (raw === 'land') return { key: 'land', label: 'Land' }
  if (raw === 'office') return { key: 'office', label: 'Office' }
  return { key: 'shortlet', label: value || 'Shortlet' }
}

const buildPreviewImages = (property) => {
  const candidates = []
  if (property.thumbnail) candidates.push(property.thumbnail)

  if (Array.isArray(property.images)) {
    candidates.push(...property.images)
  } else if (typeof property.images === 'string' && property.images.trim()) {
    const trimmed = property.images.trim()
    try {
      const parsed = JSON.parse(trimmed)
      if (Array.isArray(parsed)) {
        candidates.push(...parsed)
      } else if (typeof parsed === 'string') {
        candidates.push(parsed)
      }
    } catch (err) {
      candidates.push(...trimmed.split(',').map((item) => item.trim()))
    }
  }

  const unique = [...new Set(candidates.filter(Boolean))]
  const normalized = unique.map(normalizeImage)
  if (normalized.length === 0) {
    return [normalizeImage('')]
  }
  return normalized
}

function buildLocalImageSets() {
  const grouped = {}
  localImageFiles.forEach((filename) => {
    const groupId = filename.split('_')[0]
    if (!grouped[groupId]) grouped[groupId] = []
    grouped[groupId].push(`/uploads/properties/${filename}`)
  })

  return Object.values(grouped)
}

async function loadProperties() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.post('/api/users/Properties/view_all_properties.php', {
      page: 1,
      limit: 40
    })

    if (res.data?.status) {
      const imageSets = buildLocalImageSets()
      const raw = res.data?.data?.properties || []
      let expanded = raw.slice()
      const MIN_LISTINGS = 30

      if (expanded.length > 0 && expanded.length < MIN_LISTINGS) {
        let guard = 0
        while (expanded.length < MIN_LISTINGS && guard < 200) {
          const cloned = raw.map((p, cloneIndex) => ({
            ...p,
            __clientKey: `${p.id}-dup-${guard}-${cloneIndex}`,
            __syntheticTypeIndex: (guard + cloneIndex) % 6
          }))
          expanded = expanded.concat(cloned)
          guard += 1
        }
      }

      const typeRotation = [
        { key: 'shortlet', label: 'Shortlet' },
        { key: 'apartment', label: 'Apartment' },
        { key: 'hotel', label: 'Hotel' },
        { key: 'house', label: 'House' },
        { key: 'land', label: 'Land' },
        { key: 'office', label: 'Office' }
      ]

      properties.value = expanded.slice(0, Math.max(MIN_LISTINGS, raw.length)).map((p, index) => {
        const localSet = imageSets.length > 0 ? imageSets[index % imageSets.length] : []
        const previewImages = localSet.length > 0 ? localSet.map(normalizeImage) : buildPreviewImages(p)
        const normalizedType = normalizePropertyType(p.property_type)
        const syntheticType =
          typeof p.__syntheticTypeIndex === 'number'
            ? typeRotation[p.__syntheticTypeIndex % typeRotation.length]
            : null
        const assignedType = syntheticType || normalizedType
        const propertyId = Number(p.id)

        return {
          propertyId,
          clientKey: p.__clientKey || `property-${propertyId}-${index}`,
          title: p.title,
          price: Number(p.price || 0),
          address: p.location || `${p.city || ''} ${p.state || ''}`.trim(),
          beds: p.bed || '—',
          baths: p.bath || '—',
          size: p.asize || '—',
          tag: p.featured ? 'Featured' : 'Curated',
          type: assignedType.key,
          typeLabel: assignedType.label,
          image: previewImages[0],
          previewImages
        }
      })
    } else {
      error.value = res.data?.text || 'Unable to load properties.'
    }
  } catch (err) {
    error.value = err?.response?.data?.text || 'Unable to load properties.'
  } finally {
    loading.value = false
  }
}

async function loadWishlist() {
  try {
    const res = await api.post('/api/users/Properties/wishlist_get.php')
    if (res.data?.status) {
      savedIds.value = (res.data?.data || []).map((p) => Number(p.id))
    } else {
      savedIds.value = []
    }
  } catch (err) {
    savedIds.value = []
  }
}

function isSaved(id) {
  return savedIds.value.includes(Number(id))
}

async function toggleSaved(id) {
  const propertyId = Number(id)
  if (!propertyId) return
  const token = localStorage.getItem('AUTH_TOKEN')
  if (!token) {
    router.push('/login?role=user')
    return
  }

  const payload = new FormData()
  payload.append('property_id', propertyId)

  try {
    if (isSaved(propertyId)) {
      await api.post('/api/users/Properties/wishlist_remove.php', payload)
      savedIds.value = savedIds.value.filter((item) => item !== propertyId)
    } else {
      await api.post('/api/users/Properties/wishlist_add.php', payload)
      savedIds.value = [...savedIds.value, propertyId]
    }
  } catch (err) {
    localStorage.setItem(wishlistKey, JSON.stringify(savedIds.value))
  }
}

const filteredProperties = computed(() => {
  const query = search.query.trim().toLowerCase()
  const type = search.type.trim().toLowerCase()
  const budget = Number(search.maxBudget || 0)

  return properties.value.filter((property) => {
    const matchesType = type ? property.type === type : true
    const matchesFilterTypes = filters.activeTypes.length > 0 ? filters.activeTypes.includes(property.type) : true
    const haystack = `${property.title} ${property.address} ${property.type}`.toLowerCase()
    const matchesQuery = query ? haystack.includes(query) : true
    const matchesBudget = budget ? property.price <= budget : true

    return matchesType && matchesFilterTypes && matchesQuery && matchesBudget
  })
})

const INITIAL_VISIBLE = 12
const visibleCount = ref(INITIAL_VISIBLE)

const visibleProperties = computed(() => {
  return filteredProperties.value.slice(0, visibleCount.value)
})

const hasMore = computed(() => visibleCount.value < filteredProperties.value.length)
const isListingsExpanded = computed(() => visibleCount.value > INITIAL_VISIBLE)
const showListingsToggle = computed(() => filteredProperties.value.length > INITIAL_VISIBLE)

function showMore() {
  if (!hasMore.value) return
  visibleCount.value += INITIAL_VISIBLE
}

function collapseListings() {
  visibleCount.value = INITIAL_VISIBLE
}

function handleListingsToggle() {
  if (!isListingsExpanded.value) {
    showMore()
  } else {
    collapseListings()
  }
}

function applyFilters() {
  filters.activeTypes = [...filters.types]
  visibleCount.value = INITIAL_VISIBLE
}

watch([() => search.query, () => search.type, () => search.maxBudget, () => filters.types.length], () => {
  visibleCount.value = INITIAL_VISIBLE
})

const previewExpandedIds = ref([])

function isPreviewExpanded(id) {
  return previewExpandedIds.value.includes(id)
}

function togglePreviewExpand(id) {
  if (isPreviewExpanded(id)) {
    previewExpandedIds.value = previewExpandedIds.value.filter((item) => item !== id)
  } else {
    previewExpandedIds.value = [...previewExpandedIds.value, id]
  }
}

function visiblePreviewImages(property) {
  if (!property?.previewImages?.length) return []
  if (isPreviewExpanded(property.clientKey)) return property.previewImages
  return property.previewImages.slice(0, 4)
}

onMounted(() => {
  loadProperties()
  if (!isAgent.value) loadWishlist()
})
</script>
