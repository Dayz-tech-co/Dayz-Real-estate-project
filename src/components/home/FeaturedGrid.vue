<template>
  <section class="px-10 py-16">
    <h2 class="text-4xl font-display mb-10">Featured Nigeria Collections</h2>

    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-8">
      <PropertyCard
        v-for="item in properties"
        :key="item.id"
        :property="item"
      />
    </div>
  </section>
</template>

<script setup>
import PropertyCard from '@/components/property/PropertyCard.vue'

const properties = [
  {
    id: 1,
    title: 'Banana Island Penthouse',
    price: 320000000,
    location: 'Ikoyi, Lagos',
    summary: 'Panoramic lagoon views with private elevator access.',
    thumbnail: '/uploads/properties/1761862624_swimming_images.jpeg'
  }
]
</script>
