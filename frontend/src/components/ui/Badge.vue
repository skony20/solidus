<script setup lang="ts">
/**
 * Etykieta stanu - te same warianty co w makiecie: krytyczny, podwyzszony,
 * niski i neutralny.
 */
withDefaults(
  defineProps<{
    variant?: 'critical' | 'elevated' | 'low' | 'neutral' | 'cyan'
  }>(),
  { variant: 'neutral' },
)

const variants: Record<string, string> = {
  critical: 'bg-[rgba(255,75,137,0.14)] text-magenta-bright border-[rgba(255,75,137,0.35)]',
  elevated: 'bg-[rgba(255,177,64,0.14)] text-amber border-[rgba(255,177,64,0.35)]',
  low: 'bg-[rgba(0,226,139,0.14)] text-emerald-bright border-[rgba(0,226,139,0.35)]',
  cyan: 'bg-[rgba(0,219,231,0.12)] text-cyan-bright border-[rgba(0,219,231,0.35)]',
  neutral: 'bg-surface-highest text-content-variant border-outline',
}
</script>

<template>
  <span
    class="inline-block rounded-full border px-3 py-[3px] text-[11px] font-bold tracking-[0.04em]"
    :class="variants[variant]"
  >
    <slot />
  </span>
</template>
