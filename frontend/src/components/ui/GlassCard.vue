<script setup lang="ts">
/**
 * Szklana karta - podstawowy pojemnik na tresc w calym Solidusie.
 *
 * `accent` koloruje obramowanie, gdy karta niesie informacje o stanie:
 * zielone dla poprawnego, magenta dla ryzyka, bursztynowe dla ostrzezenia.
 */
withDefaults(
  defineProps<{
    accent?: 'none' | 'cyan' | 'magenta' | 'emerald' | 'amber'
    padding?: 'none' | 'sm' | 'md' | 'lg'
    small?: boolean
  }>(),
  { accent: 'none', padding: 'md', small: false },
)

const accentBorders: Record<string, string> = {
  none: '',
  cyan: 'border-[rgba(0,219,231,0.35)]',
  magenta: 'border-[rgba(255,75,137,0.35)]',
  emerald: 'border-[rgba(0,226,139,0.3)]',
  amber: 'border-[rgba(255,177,64,0.35)]',
}

const paddings: Record<string, string> = {
  none: 'p-0',
  sm: 'p-[18px]',
  md: 'p-6',
  lg: 'p-7',
}
</script>

<template>
  <div class="glass" :class="[small && 'glass-sm', accentBorders[accent], paddings[padding]]">
    <slot />
  </div>
</template>
