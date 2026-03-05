<template>
  <div
    class="grid grid-cols-2 gap-2 rounded p-1"
    :class="variant === 'client'
      ? 'border border-[#2a4338] bg-[#101b17]'
      : 'border border-dayz-border-muted bg-[#0d1714]'"
  >
    <button
      type="button"
      class="h-11 text-xs font-semibold uppercase tracking-[0.18em] transition-all duration-300"
      :class="modelValue === 'agent' ? resolvedActiveClass : resolvedInactiveClass"
      @click="$emit('update:modelValue', 'agent')"
    >
      AGENT
    </button>
    <button
      type="button"
      class="h-11 text-xs font-semibold uppercase tracking-[0.18em] transition-all duration-300"
      :class="modelValue === 'user' ? resolvedActiveClass : resolvedInactiveClass"
      @click="$emit('update:modelValue', 'user')"
    >
      CLIENT
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: 'user'
  },
  variant: {
    type: String,
    default: 'default'
  }
})

defineEmits(['update:modelValue'])

const activeClass = 'bg-dayz-emerald text-dayz-gold border border-dayz-gold/80 shadow-[0_0_0_1px_rgba(198,167,94,0.16)_inset]'
const inactiveClass = 'text-dayz-text-soft border border-transparent hover:text-dayz-gold'
const clientActiveClass = 'text-dayz-gold border-b-2 border-dayz-gold font-bold bg-[#12241e]'
const clientInactiveClass = 'text-slate-300 border-b-2 border-transparent hover:text-dayz-gold/90'

const resolvedActiveClass = computed(() => (props.variant === 'client' ? clientActiveClass : activeClass))
const resolvedInactiveClass = computed(() => (props.variant === 'client' ? clientInactiveClass : inactiveClass))
</script>
