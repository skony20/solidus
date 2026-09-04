<script setup lang="ts">
import { ref } from 'vue'
import Sidebar from '../ui/Sidebar.vue'
import Topbar from '../ui/Topbar.vue'

/**
 * Rama aplikacji z makiety: menu boczne po lewej, gorny pasek i tresc modulu.
 * Ponizej 900px menu chowa sie za przyciskiem.
 */
const menuOpen = ref(false)
</script>

<template>
  <div class="flex min-h-screen">
    <Sidebar :open="menuOpen" @close="menuOpen = false" />

    <div class="min-w-0 flex-1">
      <Topbar @toggle-menu="menuOpen = !menuOpen" />
      <main class="mx-auto max-w-[1400px] p-8">
        <slot />
      </main>
    </div>
  </div>
</template>
