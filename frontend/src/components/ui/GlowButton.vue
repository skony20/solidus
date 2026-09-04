<script setup lang="ts">
/**
 * Przycisk akcji z poswiata. Wariant magenta jest zarezerwowany dla dzialan
 * zwiazanych z ryzykiem (np. "Skanuj" w module AML).
 */
withDefaults(
  defineProps<{
    variant?: 'cyan' | 'magenta' | 'ghost'
    type?: 'button' | 'submit'
    disabled?: boolean
  }>(),
  { variant: 'cyan', type: 'button', disabled: false },
)

const variants: Record<string, string> = {
  cyan: 'bg-gradient-to-br from-cyan-bright to-cyan text-[#00363a] hover:shadow-glow',
  magenta: 'bg-gradient-to-br from-magenta-bright to-magenta text-[#3f0019] hover:shadow-glow-magenta',
  ghost: 'bg-surface-low border border-outline text-content hover:border-[rgba(0,219,231,0.4)] hover:text-cyan-bright',
}
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    class="rounded-nav border-0 px-5 py-2 text-[13px] font-bold transition-all duration-150 hover:-translate-y-px disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0 disabled:hover:shadow-none"
    :class="variants[variant]"
  >
    <slot />
  </button>
</template>
