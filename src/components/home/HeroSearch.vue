<template>
  <section
    class="min-h-[650px] bg-cover bg-center flex items-center justify-center text-white"
    :style="heroStyle"
  >
    <div class="text-center max-w-4xl">
      <img
        src="/images/DayzLogo.svg"
        alt="Dayz"
        class="h-36 md:h-40 w-auto object-contain mx-auto mb-6 bg-black/60 p-3 rounded-sm ring-1 ring-white/10"
      />
      <span class="tracking-widest text-accent uppercase text-sm">
        Exquisite Living Awaits
      </span>

      <h1 class="text-6xl font-display my-6">
        Discover Your Sanctuary
      </h1>

      <p class="text-lg opacity-90">
        Curated properties for the discerning individual.
      </p>

      <div class="bg-white text-black mt-10 flex rounded-sm overflow-hidden">
        <input v-model="search.location" placeholder="City or ZIP" class="input" />
        <select v-model="search.type" class="input">
          <option value="">Property Type</option>
          <option value="house">House</option>
          <option value="villa">Villa</option>
        </select>
        <button class="bg-primary text-white px-8">Search</button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { reactive } from 'vue'

const search = reactive({
  location: '',
  type: ''
})

const heroStyle = {
  backgroundImage: 'linear-gradient(135deg, rgba(6,78,59,0.9), rgba(2,44,34,0.95))'
}
</script>

<style scoped>
.input {
  @apply h-16 px-4 border-none outline-none;
}
</style>
