<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useRouter } from 'vue-router'
import { useClientsStore, type Client, type ClientPayload } from '../../stores/clients'
import GlassCard from '../../components/ui/GlassCard.vue'
import SectionTitle from '../../components/ui/SectionTitle.vue'
import Badge from '../../components/ui/Badge.vue'
import ClientList from './ClientList.vue'
import ClientForm from './ClientForm.vue'

/**
 * Ekran "Baza Klientów" - wzorcowy modul Solidusa.
 *
 * Uklad z makiety: szeroka tabela po lewej, panel dodawania po prawej.
 */
const store = useClientsStore()
const { items, isLoading, error, fieldErrors, total } = storeToRefs(store)
const router = useRouter()

const search = ref('')
const statusFilter = ref('')
const showForm = ref(false)

const filters = [
  { value: '', label: 'WSZYSCY' },
  { value: 'onboarding', label: 'WDROŻENIE' },
  { value: 'active', label: 'AKTYWNI' },
  { value: 'lead', label: 'POTENCJALNI' },
]

onMounted(() => store.fetchAll())

function applyFilters(): void {
  void store.fetchAll(search.value, statusFilter.value)
}

function setFilter(value: string): void {
  statusFilter.value = value
  applyFilters()
}

async function createClient(payload: ClientPayload): Promise<void> {
  const created = await store.create(payload)

  if (created) {
    showForm.value = false
    await store.fetchAll(search.value, statusFilter.value)
  }
}

async function removeClient(client: Client): Promise<void> {
  // Bez okna potwierdzenia - usuniecie i tak zostaje w audit logu,
  // a docelowo zastapi je miekkie archiwum.
  await store.remove(client.id)
}

function openClient(client: Client): void {
  void router.push({ name: 'client-detail', params: { id: client.id } })
}
</script>

<template>
  <div class="animate-fade-in">
    <SectionTitle eyebrow="CRM 360°" title="Baza Klientów">
      Firmy obsługiwane przez Twoje biuro. Każda operacja trafia do dziennika zmian.
    </SectionTitle>

    <p
      v-if="error"
      class="mb-4 rounded-glass-sm border border-[rgba(255,75,137,0.35)] bg-[rgba(255,75,137,0.1)] px-3.5 py-2.5 text-[13px] text-magenta-bright"
    >
      {{ error }}
    </p>

    <div class="grid items-start gap-5 xl:grid-cols-[1fr_340px]">
      <GlassCard padding="none" class="overflow-hidden">
        <div
          class="flex flex-wrap items-center justify-between gap-3 border-b border-white/[0.07] px-6 py-5"
        >
          <div class="relative min-w-[200px] flex-1">
            <span
              class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 !text-base text-content-variant"
            >
              search
            </span>
            <input
              v-model="search"
              class="w-full rounded-full border border-outline bg-surface-low py-2.5 pl-9 pr-3.5 text-[13px] text-content outline-none placeholder:text-content-variant focus:border-[rgba(0,219,231,0.4)]"
              placeholder="Szukaj po nazwie lub NIP..."
              @keyup.enter="applyFilters"
            />
          </div>

          <div class="flex gap-1.5">
            <button v-for="filter in filters" :key="filter.value" @click="setFilter(filter.value)">
              <Badge :variant="statusFilter === filter.value ? 'cyan' : 'neutral'">
                {{ filter.label }}
              </Badge>
            </button>
          </div>
        </div>

        <ClientList
          :clients="items"
          :is-loading="isLoading"
          @select="openClient"
          @remove="removeClient"
        />

        <div class="border-t border-white/[0.07] px-6 py-3 text-xs text-content-variant">
          Klientów: {{ total }}
        </div>
      </GlassCard>

      <GlassCard padding="sm">
        <h3 class="m-0 mb-1.5 flex items-center gap-2 text-[15px] font-bold">
          <span class="material-symbols-outlined !text-[18px] text-cyan-bright">person_add</span>
          Nowy klient
        </h3>

        <ClientForm
          v-if="showForm"
          :field-errors="fieldErrors"
          :is-saving="isLoading"
          submit-label="Dodaj klienta"
          @submit="createClient"
          @cancel="showForm = false"
        />

        <div v-else>
          <p class="m-0 mb-3.5 text-xs text-content-variant">
            NIP jest sprawdzany sumą kontrolną i musi być unikalny w Twoim biurze.
          </p>
          <button
            type="button"
            class="w-full rounded-nav bg-gradient-to-br from-cyan-bright to-cyan px-5 py-2.5 text-[13px] font-bold text-[#00363a] transition-all duration-150 hover:-translate-y-px hover:shadow-glow"
            @click="showForm = true"
          >
            Dodaj klienta
          </button>
        </div>
      </GlassCard>
    </div>
  </div>
</template>
