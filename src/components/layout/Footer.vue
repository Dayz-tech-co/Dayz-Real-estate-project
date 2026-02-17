<template>
  <footer class="bg-emerald-950 text-white">
    <div class="layout-content-container px-6 py-12 grid gap-8 md:grid-cols-3">
      <div>
        <div class="flex items-center gap-3 mb-3">
          <img
            src="/images/DayzLogo.svg"
            alt="Dayz"
            class="h-20 md:h-24 w-auto object-contain bg-black/60 p-2 rounded-sm ring-1 ring-white/10"
          />
        </div>
        <p class="text-white/70 text-sm">
          Curated real estate experiences for buyers, investors, and elite agencies.
        </p>
      </div>
      <div>
        <h4 class="uppercase tracking-widest text-xs text-white/60 mb-4">Explore</h4>
        <ul class="space-y-2 text-sm">
          <li><RouterLink class="hover:text-white" to="/">Home</RouterLink></li>
          <li><RouterLink class="hover:text-white" to="/blog">Insights</RouterLink></li>
          <li><RouterLink class="hover:text-white" to="/register">Register</RouterLink></li>
        </ul>
      </div>
      <div>
        <h4 class="uppercase tracking-widest text-xs text-white/60 mb-4">Contact</h4>
        <p class="text-sm text-white/70">support@dayz.com</p>
        <p class="text-sm text-white/70">+1 (212) 555-0198</p>
      </div>
    </div>
    <div class="border-t border-white/10 text-center text-xs text-white/50 py-4">
      Dayz Real Estate &copy; 2026
    </div>
  </footer>
</template>

<script setup>
import { RouterLink } from 'vue-router'
</script>
