<script setup lang="ts">
import { RouterLink } from 'vue-router'

/**
 * Pozycja menu bocznego. Stan aktywny odtwarza poswiate z makiety:
 * cyjanowe obramowanie, wypelniona ikona i wewnetrzny blask.
 */
defineProps<{ to: string; icon: string; label: string }>()
</script>

<template>
  <RouterLink
    :to="to"
    class="nav-item mb-1 flex cursor-pointer items-center gap-3 rounded-nav border border-transparent px-3.5 py-[11px] text-sm font-semibold text-content-variant transition-all duration-150 hover:bg-white/[0.04] hover:text-content"
    active-class="border-[rgba(0,219,231,0.35)]! bg-[rgba(0,219,231,0.10)] text-cyan-bright! shadow-nav-active [&_.material-symbols-outlined]:[font-variation-settings:'FILL'_1,'wght'_500]"
  >
    <span class="material-symbols-outlined">{{ icon }}</span>
    {{ label }}
  </RouterLink>
</template>
