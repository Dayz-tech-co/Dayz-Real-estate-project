<template>
  <div class="min-h-screen bg-theme text-white">
    <section class="layout-content-container px-6 py-10">
      <div class="flex items-center justify-between mb-6">
        <div>
          <p class="uppercase tracking-[0.3em] text-xs text-white/60">Property Comparison</p>
          <h1 class="font-display text-3xl">Side-by-side analysis</h1>
          <p class="text-white/70">Compare your shortlisted estates in Lagos and Abuja.</p>
        </div>
        <button class="px-5 py-2 text-xs uppercase tracking-widest border border-white/20 hover:bg-white/10">
          Export Analysis
        </button>
      </div>

      <div class="bg-white text-emerald-950 rounded-lg overflow-hidden border border-white/10">
        <div class="grid grid-cols-1 lg:grid-cols-[240px,1fr,1fr]">
          <div class="p-6 border-b border-black/10 lg:border-b-0 lg:border-r border-black/10">
            <p class="text-xs uppercase tracking-widest text-emerald-900/60">Comparison Metrics</p>
            <h2 class="font-display text-2xl mt-3">Features</h2>
          </div>
          <div class="border-b border-black/10 lg:border-b-0 lg:border-r border-black/10">
            <img src="/uploads/properties/1762164599_Abuja_house.png" alt="Abuja residence" class="h-48 w-full object-cover" />
            <div class="p-5">
              <p class="text-xs uppercase tracking-widest text-emerald-900/60">Listing #ABJ-0921</p>
              <h3 class="font-display text-2xl">Maitama Villa</h3>
              <p class="text-sm text-emerald-900/60">Abuja, FCT</p>
            </div>
          </div>
          <div class="border-b border-black/10 lg:border-b-0">
            <img src="/uploads/properties/1761862624_DJI_0253-2-scaled.webp" alt="Lagos residence" class="h-48 w-full object-cover" />
            <div class="p-5">
              <p class="text-xs uppercase tracking-widest text-emerald-900/60">Listing #LAG-7710</p>
              <h3 class="font-display text-2xl">Lekki Waterfront</h3>
              <p class="text-sm text-emerald-900/60">Lagos, NG</p>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[240px,1fr,1fr] divide-y divide-black/10 lg:divide-y-0">
          <div class="p-5 border-r border-black/10 text-xs uppercase tracking-widest text-emerald-900/60">Asking Price</div>
          <div class="p-5 border-r border-black/10 font-semibold">₦ 420,000,000</div>
          <div class="p-5 font-semibold">₦ 510,000,000</div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-[240px,1fr,1fr] divide-y divide-black/10 lg:divide-y-0 bg-emerald-50/70">
          <div class="p-5 border-r border-black/10 text-xs uppercase tracking-widest text-emerald-900/60">Total Area</div>
          <div class="p-5 border-r border-black/10">4,200 sq.ft</div>
          <div class="p-5">3,600 sq.ft</div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-[240px,1fr,1fr] divide-y divide-black/10 lg:divide-y-0">
          <div class="p-5 border-r border-black/10 text-xs uppercase tracking-widest text-emerald-900/60">Bedrooms</div>
          <div class="p-5 border-r border-black/10">5 Bedrooms</div>
          <div class="p-5">4 Bedrooms</div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-[240px,1fr,1fr] divide-y divide-black/10 lg:divide-y-0 bg-emerald-50/70">
          <div class="p-5 border-r border-black/10 text-xs uppercase tracking-widest text-emerald-900/60">Amenities</div>
          <div class="p-5 border-r border-black/10">Pool · Gym</div>
          <div class="p-5">Lagoon View · Smart Home</div>
        </div>
      </div>
    </section>
  </div>
</template>
