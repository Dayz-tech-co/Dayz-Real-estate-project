<template>
  <div class="bg-white shadow hover:shadow-xl transition rounded-sm overflow-hidden">
    <div class="h-48 bg-cover bg-center">
      <img v-if="property.thumbnail" :src="property.thumbnail" :alt="property.title" class="h-full w-full object-cover" />
    </div>

    <div class="p-5">
      <h3 class="font-display text-lg">{{ property.title }}</h3>
      <p class="text-primary font-bold mt-1">
        ₦ {{ formatPrice(property.price) }}
      </p>
      <p class="text-sm text-gray-500">
        {{ property.location || `${property.city}, ${property.state}` }}
      </p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  property: Object
})

const formatPrice = (price) =>
  new Intl.NumberFormat().format(price || 0)
</script>
