<template>
  <section class="layout-content-container px-6 py-16 text-white">
    <article v-if="post" class="max-w-4xl mx-auto space-y-8">
      <div class="rounded-xl overflow-hidden border border-white/10">
        <img :src="post.image" :alt="post.title" class="h-[360px] w-full object-cover" />
      </div>

      <div>
        <p class="text-xs uppercase tracking-[0.3em] text-white/60">{{ post.category }}</p>
        <h1 class="font-display text-4xl mt-3">{{ post.title }}</h1>
        <p class="text-white/80 mt-4">{{ post.excerpt }}</p>
      </div>

      <div class="space-y-4 text-white/85 leading-7">
        <p v-for="(paragraph, index) in post.body" :key="`${post.slug}-${index}`">
          {{ paragraph }}
        </p>
      </div>

      <RouterLink to="/blog" class="inline-flex border border-white/20 px-5 py-3 text-xs uppercase tracking-widest hover:bg-white/10">
        Back to {{ isAgent ? 'Market Intel' : 'Insights' }}
      </RouterLink>
    </article>

    <div v-else class="max-w-3xl mx-auto rounded-lg border border-red-200/20 bg-red-900/30 p-6">
      <h2 class="font-display text-2xl">Insight not found</h2>
      <p class="text-white/75 mt-3">The requested article does not exist.</p>
      <RouterLink to="/blog" class="inline-flex mt-5 border border-white/20 px-5 py-3 text-xs uppercase tracking-widest hover:bg-white/10">
        Return to feed
      </RouterLink>
    </div>
  </section>
</template>

<script setup>
import { computed, watchEffect } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { getInsightBySlug } from '@/lib/insights'

const route = useRoute()
const router = useRouter()
const slug = computed(() => String(route.params.slug || '').trim())
const post = computed(() => getInsightBySlug(slug.value))
const isAgent = computed(() => localStorage.getItem('USER_ROLE') === 'agent')

watchEffect(() => {
  if (!slug.value || slug.value.toLowerCase() === 'undefined') {
    router.replace('/blog')
  }
})
</script>
