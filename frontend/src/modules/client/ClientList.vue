<script setup lang="ts">
import type { Client } from '../../stores/clients'
import Badge from '../../components/ui/Badge.vue'

/**
 * Tabela klientow. Komponent jest celowo "glupi" - dostaje dane i emituje
 * zdarzenia, nie zna store'a ani API. Dzieki temu da sie go przetestowac
 * bez stawiania backendu (patrz ClientList.spec.ts).
 */
defineProps<{
  clients: Client[]
  isLoading: boolean
}>()

defineEmits<{ select: [client: Client]; remove: [client: Client] }>()

/** Kolor etykiety zalezy od etapu wspolpracy z klientem. */
function statusVariant(status: string): 'low' | 'cyan' | 'elevated' | 'neutral' | 'critical' {
  switch (status) {
    case 'active':
      return 'low'
    case 'onboarding':
      return 'cyan'
    case 'lead':
      return 'neutral'
    case 'suspended':
      return 'elevated'
    default:
      return 'critical'
  }
}

/** NIP czyta sie latwiej w grupach: 527-00-00-001. */
function formatNip(nip: string | null): string {
  if (nip === null || nip.length !== 10) {
    return nip ?? '—'
  }

  return `${nip.slice(0, 3)}-${nip.slice(3, 5)}-${nip.slice(5, 7)}-${nip.slice(7)}`
}
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full border-collapse text-[13px]">
      <thead>
        <tr class="text-left text-[11px] uppercase tracking-[0.04em] text-content-variant">
          <th class="px-5 py-2.5 font-semibold">Klient</th>
          <th class="px-3 py-2.5 font-semibold">NIP</th>
          <th class="px-3 py-2.5 font-semibold">Kontakt</th>
          <th class="px-3 py-2.5 font-semibold">Status</th>
          <th class="px-5 py-2.5 font-semibold text-right">Akcje</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="isLoading">
          <td colspan="5" class="px-5 py-8 text-center text-content-variant">Wczytywanie...</td>
        </tr>

        <tr v-else-if="clients.length === 0">
          <td colspan="5" class="px-5 py-8 text-center text-content-variant">
            Brak klientów. Dodaj pierwszego formularzem obok.
          </td>
        </tr>

        <template v-else>
          <!--
            v-for siedzi w osobnym <template>, a nie obok v-else na tym samym
            elemencie - Vue nie definiuje kolejnosci obu dyrektyw na jednym tagu.
          -->
          <tr
            v-for="client in clients"
            :key="client.id"
            class="hairline cursor-pointer hover:bg-white/[0.03]"
            data-test="client-row"
            @click="$emit('select', client)"
          >
            <td class="px-5 py-3.5 font-bold">{{ client.name }}</td>
            <td class="px-3 py-3.5 text-content-variant">{{ formatNip(client.nip) }}</td>
            <td class="px-3 py-3.5 text-content-variant">{{ client.email ?? '—' }}</td>
            <td class="px-3 py-3.5">
              <Badge :variant="statusVariant(client.status)">
                {{ client.statusLabel.toUpperCase() }}
              </Badge>
            </td>
            <td class="px-5 py-3.5 text-right">
              <button
                type="button"
                class="text-content-variant transition-colors hover:text-magenta-bright"
                title="Usuń klienta"
                data-test="client-remove"
                @click.stop="$emit('remove', client)"
              >
                <span class="material-symbols-outlined !text-[18px]">delete</span>
              </button>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>
