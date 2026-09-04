<script setup lang="ts">
/**
 * Naglowek modulu: mala etykieta kontekstu ("eyebrow") nad duzym tytulem.
 * Kazdy ekran w makiecie zaczyna sie tym ukladem.
 */
defineProps<{ eyebrow?: string; title: string }>()
</script>

<template>
  <div class="mb-6">
    <div
      v-if="eyebrow"
      class="text-[11px] font-bold uppercase tracking-[0.12em] text-content-variant"
    >
      {{ eyebrow }}
    </div>
    <h1 class="my-1 text-[26px] font-extrabold tracking-[-0.01em]">{{ title }}</h1>
    <p v-if="$slots.default" class="mt-2 max-w-2xl text-[13px] text-content-variant">
      <slot />
    </p>
  </div>
</template>
