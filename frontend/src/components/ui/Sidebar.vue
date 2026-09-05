<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { computed } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { navigationModules } from '../../router/modules'
import NavItem from './NavItem.vue'

/**
 * Menu boczne - odtworzone z makiety, ale lista modulow pochodzi z jednego
 * miejsca (router/modules.ts), zeby dodanie modulu nie wymagalo edycji
 * dwoch plikow.
 */
defineProps<{ open: boolean }>()
defineEmits<{ close: [] }>()

const auth = useAuthStore()
const { user, tenant, isPlatformAdmin } = storeToRefs(auth)

// Inicjaly do awatara, np. "Anna Kowalska" -> "AK".
const initials = computed(() =>
  (user.value?.name ?? '')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join(''),
)
</script>

<template>
  <aside
    class="sticky top-0 z-40 flex h-screen w-[272px] shrink-0 flex-col border-r border-white/[0.06] bg-[rgba(13,19,33,0.6)] px-5 py-7 backdrop-blur-[24px] max-[900px]:fixed max-[900px]:-translate-x-full max-[900px]:transition-transform max-[900px]:duration-[250ms]"
    :class="open && 'max-[900px]:translate-x-0!'"
  >
    <div>
      <div
        class="bg-linear-to-r from-cyan-bright to-cyan bg-clip-text text-[26px] font-extrabold leading-[1.05] tracking-[-0.02em] text-transparent"
      >
        SOLIDUS
      </div>
      <div class="mt-1.5 text-[11px] font-bold uppercase tracking-[0.12em] text-content-variant">
        {{ tenant?.name ?? 'System Biura Rachunkowego' }}
      </div>
    </div>

    <nav class="mt-6 flex-1 overflow-y-auto pr-1" @click="$emit('close')">
      <NavItem
        v-for="module in navigationModules"
        :key="module.path"
        :to="module.path"
        :icon="module.icon"
        :label="module.label"
      />
    </nav>

    <div class="border-t border-white/[0.07] pt-3.5">
      <!--
        Widoczne tylko dla administratora calego systemu. To wygoda, nie
        ochrona - dostepu pilnuje API i straznik tras.
      -->
      <NavItem
        v-if="isPlatformAdmin"
        to="/admin/cennik"
        icon="sell"
        label="Cennik (system)"
      />
      <NavItem to="/ustawienia" icon="settings" label="Ustawienia" />
      <div class="mt-2.5 flex items-center gap-2.5 px-1.5 py-2">
        <div
          class="flex h-[34px] w-[34px] items-center justify-center rounded-full bg-linear-to-br from-magenta to-cyan text-[13px] font-bold text-space"
        >
          {{ initials || '?' }}
        </div>
        <div class="min-w-0">
          <div class="truncate text-[13px] font-bold">{{ user?.name ?? 'Niezalogowany' }}</div>
          <div class="flex items-center gap-1 text-[11px] text-emerald-bright">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-bright" />
            Aktywny
          </div>
        </div>
      </div>
    </div>
  </aside>
</template>
