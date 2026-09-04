<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

/**
 * Gorny pasek: wyszukiwarka i szybkie akcje. Przycisk menu pojawia sie
 * dopiero ponizej 900px, gdy menu boczne chowa sie poza ekran.
 */
defineEmits<{ toggleMenu: [] }>()

const auth = useAuthStore()
const router = useRouter()

async function logout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div
    class="sticky top-0 z-20 flex items-center gap-4 border-b border-white/[0.06] bg-[rgba(13,19,33,0.55)] px-8 py-4 backdrop-blur-bar"
  >
    <span
      class="material-symbols-outlined hidden cursor-pointer max-[900px]:block"
      @click="$emit('toggleMenu')"
    >
      menu
    </span>

    <div class="relative max-w-[420px] flex-1">
      <span
        class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 !text-[18px] text-content-variant"
      >
        search
      </span>
      <input
        class="w-full rounded-full border border-outline bg-surface-low py-[11px] pl-10 pr-4 text-sm text-content outline-none placeholder:text-content-variant focus:border-[rgba(0,219,231,0.4)]"
        placeholder="Szukaj danych, klientów, transmisji..."
      />
    </div>

    <div class="flex-1" />

    <button
      v-for="icon in ['notifications', 'podcasts', 'hub']"
      :key="icon"
      class="flex h-[38px] w-[38px] items-center justify-center rounded-full border border-outline bg-surface-low text-content-variant transition-all duration-150 hover:border-[rgba(0,219,231,0.4)] hover:text-cyan-bright"
    >
      <span class="material-symbols-outlined !text-[18px]">{{ icon }}</span>
    </button>

    <button
      title="Wyloguj"
      class="flex h-[38px] w-[38px] items-center justify-center rounded-full border border-[rgba(0,219,231,0.35)] bg-surface-low text-cyan-bright transition-all duration-150 hover:shadow-glow"
      @click="logout"
    >
      <span class="material-symbols-outlined !text-[18px]">logout</span>
    </button>
  </div>
</template>
