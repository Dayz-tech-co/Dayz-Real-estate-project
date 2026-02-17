<template>
  <div>
    <div class="layout-content-container px-6 pt-10">
      <div class="flex flex-wrap gap-3 justify-center">
        <RouterLink
          to="/register?role=user"
          class="px-5 py-2 text-xs uppercase tracking-widest border border-white/30 text-white hover:bg-white/10"
        >
          User
        </RouterLink>
        <RouterLink
          to="/register?role=agent"
          class="px-5 py-2 text-xs uppercase tracking-widest border border-white/30 text-white hover:bg-white/10"
        >
          Agent
        </RouterLink>
      </div>
    </div>

    <RegisterUser v-if="role === 'user'" />
    <RegisterAgent v-else-if="role === 'agent'" />

    <div v-else class="text-center text-red-500 py-10">
      Invalid role
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'

import RegisterUser from '@/components/auth/RegisterUser.vue'
import RegisterAgent from '@/components/auth/RegisterAgent.vue'

const route = useRoute()

const role = computed(() => {
  const requested = String(route.query.role || 'user')
  return ['user', 'agent'].includes(requested) ? requested : 'user'
})
</script>
