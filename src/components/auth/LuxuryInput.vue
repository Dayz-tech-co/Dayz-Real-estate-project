<template>
  <label class="block">
    <span
      class="mb-2 block text-[10px] font-semibold uppercase tracking-[0.18em]"
      :class="variant === 'client' ? 'text-dayz-gold/80' : 'text-dayz-gold/85'"
    >
      {{ label }}
    </span>
    <div class="group relative">
      <span
        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2"
        :class="variant === 'client' ? 'text-dayz-gold/65' : 'text-dayz-gold/70'"
      >
        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" :stroke-width="variant === 'client' ? 1.5 : 1.8">
          <path :d="iconPath" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </span>
      <input
        :type="effectiveType"
        :value="modelValue"
        :placeholder="placeholder"
        :required="required"
        :autocomplete="autocomplete"
        class="h-12 w-full pl-10 pr-12 text-sm text-slate-100 outline-none transition-all duration-300"
        :class="variant === 'client'
          ? 'border border-[#294037] bg-[#121C19] placeholder:text-slate-500 focus:border-dayz-gold focus:shadow-[0_0_0_1px_rgba(198,167,94,0.22),0_0_10px_rgba(29,82,62,0.24)]'
          : 'border border-dayz-border-muted bg-[#0f1916] placeholder:text-slate-500 focus:border-dayz-gold focus:shadow-[0_0_0_1px_rgba(198,167,94,0.28),0_0_14px_rgba(15,61,46,0.35)]'"
        @input="$emit('update:modelValue', $event.target.value)"
      />
      <button
        v-if="passwordToggle"
        type="button"
        class="absolute right-3 top-1/2 -translate-y-1/2 transition-colors hover:text-dayz-gold hover:drop-shadow-[0_0_6px_rgba(198,167,94,0.45)]"
        :class="variant === 'client' ? 'text-dayz-gold/70' : 'text-dayz-gold/80'"
        @click="visible = !visible"
      >
        <svg v-if="visible" viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor">
          <path d="M3.3 2.3 2.3 3.3l3.2 3.2A11.1 11.1 0 0 0 1 12s3.7 7 11 7c2.3 0 4.2-.6 5.8-1.5l2.9 2.9 1-1-17.4-17.1Zm8.9 8.8 2.5 2.4a2.9 2.9 0 0 1-2.5.5 3 3 0 0 1-2.3-2.3 2.9 2.9 0 0 1 .5-2.5l1.8 1.9Zm8.8.9s-3.7-7-11-7c-1.3 0-2.4.2-3.5.6l2 1.9a4.8 4.8 0 0 1 7 6.7l1.5 1.5c2.5-1.8 4-4.6 4-4.6Z" />
        </svg>
        <svg v-else viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor">
          <path d="M12 5C4.7 5 1 12 1 12s3.7 7 11 7 11-7 11-7-3.7-7-11-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z" />
        </svg>
      </button>
    </div>
  </label>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  type: { type: String, default: 'text' },
  icon: { type: String, default: 'user' },
  required: { type: Boolean, default: false },
  passwordToggle: { type: Boolean, default: false },
  autocomplete: { type: String, default: 'off' },
  variant: { type: String, default: 'default' }
})

defineEmits(['update:modelValue'])

const visible = ref(false)

const iconMap = {
  email: 'M4 6h16v12H4z M4 7l8 6 8-6',
  lock: 'M7 11V8a5 5 0 0 1 10 0v3 M6 11h12v9H6z',
  user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M4 20a8 8 0 0 1 16 0',
  phone: 'M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.6A2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7l.5 3a2 2 0 0 1-.6 1.8L7.7 9.7a16 16 0 0 0 6.6 6.6l1.2-1.2a2 2 0 0 1 1.8-.6l3 .5A2 2 0 0 1 22 16.9z',
  building: 'M3 21h18 M6 21V5h12v16 M10 9h4 M10 13h4',
  wallet: 'M3 7h18v12H3z M15 12h4'
}

const iconPath = computed(() => iconMap[props.icon] || iconMap.user)
const effectiveType = computed(() => {
  if (!props.passwordToggle) return props.type
  return visible.value ? 'text' : 'password'
})
</script>
