<template>
  <section class="layout-content-container px-6 py-16 text-white">
    <div class="grid gap-10 lg:grid-cols-[280px,1fr] items-start">
      <aside class="bg-emerald-950/80 border border-white/10 p-6 space-y-8">
        <div>
          <p class="uppercase tracking-[0.3em] text-xs text-white/60 font-semibold mb-3">{{ sidebarLabel }}</p>
          <h2 class="font-display text-2xl">{{ sidebarTitle }}</h2>
          <p class="text-white/60 text-sm mt-2">{{ sidebarSubtitle }}</p>
        </div>
        <div>
          <p class="text-xs uppercase tracking-widest text-white/60 mb-3">Search</p>
          <input
            v-model.trim="searchText"
            type="text"
            placeholder="Search title, category, tag"
            class="form-input w-full h-11 px-3 bg-white/90 text-emerald-950"
          />
        </div>
        <div>
          <p class="text-xs uppercase tracking-widest text-white/60 mb-3">Topics</p>
          <div class="space-y-2 text-sm">
            <label class="flex items-center gap-2">
              <input v-model="selectedTopics" type="checkbox" value="Market" />
              Market Intelligence
            </label>
            <label class="flex items-center gap-2">
              <input v-model="selectedTopics" type="checkbox" value="Design" />
              Design and Staging
            </label>
            <label class="flex items-center gap-2">
              <input v-model="selectedTopics" type="checkbox" value="Strategy" />
              Investor Signals
            </label>
            <label class="flex items-center gap-2">
              <input v-model="selectedTopics" type="checkbox" value="Yield" />
              Yield Watch
            </label>
            <label class="flex items-center gap-2">
              <input v-model="selectedTopics" type="checkbox" value="Hospitality" />
              Hospitality
            </label>
          </div>
        </div>
        <button
          class="w-full border border-white/20 text-xs uppercase tracking-widest py-3 hover:bg-white/10"
          @click="resetFilters"
        >
          Reset Feed
        </button>
      </aside>

      <section>
        <div class="grid gap-10 lg:grid-cols-[1.1fr,0.9fr] items-center mb-14">
          <div>
            <p class="uppercase tracking-[0.3em] text-xs text-white/60 font-semibold mb-3">{{ topLabel }}</p>
            <h1 class="text-4xl font-display font-bold text-white mb-4">{{ heroTitle }}</h1>
            <p class="text-white/75 text-lg">{{ heroDescription }}</p>
          </div>
          <div class="rounded-lg overflow-hidden border border-white/10">
            <img src="/uploads/properties/1761862624_DJI_0253-2-scaled.webp" alt="Market overview" class="h-56 w-full object-cover" />
          </div>
        </div>

        <div class="flex items-center justify-between mb-6">
          <div>
            <p class="uppercase tracking-[0.3em] text-xs text-white/60 font-semibold">{{ latestLabel }}</p>
            <h2 class="font-display text-2xl mt-2">{{ latestTitle }}</h2>
          </div>
          <div class="text-xs uppercase tracking-widest text-white/60">Drag to explore</div>
        </div>

        <div
          ref="carouselRef"
          class="flex gap-6 overflow-x-auto pb-6 cursor-grab active:cursor-grabbing select-none"
          @mousedown="onPointerDown"
          @mousemove="onPointerMove"
          @mouseup="onPointerUp"
          @mouseleave="onPointerUp"
          @touchstart="onPointerDown"
          @touchmove="onPointerMove"
          @touchend="onPointerUp"
        >
          <article
            v-for="post in filteredPosts"
            :key="post.slug"
            class="min-w-[280px] md:min-w-[320px] lg:min-w-[360px] rounded-lg bg-white shadow-xl overflow-hidden group transition-transform duration-300 hover:-translate-y-1 snap-start"
          >
            <div class="relative">
              <img :src="post.image" :alt="post.title" class="h-56 w-full object-cover" />
              <span class="absolute top-3 left-3 bg-emerald-950 text-white text-[10px] tracking-widest uppercase px-2 py-1">
                {{ post.tag }}
              </span>
            </div>
            <div class="p-6">
              <p class="text-xs uppercase tracking-widest text-emerald-900/60 mb-2">{{ post.category }}</p>
              <h3 class="text-xl font-semibold text-emerald-900 mb-3">{{ post.title }}</h3>
              <p class="text-emerald-900/60 text-sm mb-4">{{ post.excerpt }}</p>
              <RouterLink :to="`/blog/${post.slug}`" class="text-xs font-bold uppercase tracking-widest text-emerald-900">
                Read More
              </RouterLink>
            </div>
          </article>
        </div>
        <div v-if="filteredPosts.length === 0" class="rounded-lg border border-white/10 bg-emerald-950/60 p-5 text-sm text-white/70">
          No intel matches your current search/preferences.
        </div>
      </section>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { insightsPosts } from '@/lib/insights'

const carouselRef = ref(null)
const isDragging = ref(false)
const startX = ref(0)
const scrollLeft = ref(0)

function getPointerX(event) {
  if (event?.touches?.[0]) return event.touches[0].pageX
  if (event?.changedTouches?.[0]) return event.changedTouches[0].pageX
  return event.pageX
}

function onPointerDown(event) {
  const el = carouselRef.value
  if (!el) return
  isDragging.value = true
  startX.value = getPointerX(event)
  scrollLeft.value = el.scrollLeft
}

function onPointerMove(event) {
  const el = carouselRef.value
  if (!el || !isDragging.value) return
  event.preventDefault()
  const x = getPointerX(event)
  const walk = x - startX.value
  el.scrollLeft = scrollLeft.value - walk
}

function onPointerUp() {
  isDragging.value = false
}

const posts = insightsPosts
const searchText = ref('')
const selectedTopics = ref([])
const isAgent = computed(() => localStorage.getItem('USER_ROLE') === 'agent')
const sidebarLabel = computed(() => (isAgent.value ? 'Market Intel' : 'Insights'))
const sidebarTitle = computed(() => (isAgent.value ? 'Agent Intelligence Desk' : 'Nigeria Market Lens'))
const sidebarSubtitle = computed(() =>
  isAgent.value
    ? 'Network updates across your listings, buyer momentum, and close rates.'
    : 'Handpicked analysis for Lagos, Abuja, and Port Harcourt.'
)
const topLabel = computed(() => (isAgent.value ? 'Dayz Agent Intel' : 'Dayz Nigeria Digest'))
const heroTitle = computed(() =>
  isAgent.value ? 'Signal Deck for High-Performance Agents' : 'Luxury Signals That Move The Market'
)
const heroDescription = computed(() =>
  isAgent.value
    ? 'Agent-focused playbooks on pricing, demand pockets, client response speed, and conversion quality.'
    : 'Curated intelligence on Lagos, Abuja, and coastal luxury markets. Discover design, pricing shifts, and buyer behavior that moves Nigeria premium real estate.'
)
const latestLabel = computed(() => (isAgent.value ? 'Latest Intel' : 'Latest Insights'))
const latestTitle = computed(() =>
  isAgent.value ? 'Swipe Through This Week Agent Signals' : 'Swipe Through This Week Signals'
)

const filteredPosts = computed(() => {
  const term = searchText.value.toLowerCase()
  const chosen = selectedTopics.value
  return posts.filter((post) => {
    const topicMatch = chosen.length ? chosen.includes(post.category) : true
    const haystack = `${post.title} ${post.excerpt} ${post.category} ${post.tag}`.toLowerCase()
    const searchMatch = term ? haystack.includes(term) : true
    return topicMatch && searchMatch
  })
})

function resetFilters() {
  searchText.value = ''
  selectedTopics.value = []
}
</script>
