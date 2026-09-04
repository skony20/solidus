<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import { useClientsStore, type ClientPayload } from '../../stores/clients'
import GlassCard from '../../components/ui/GlassCard.vue'
import SectionTitle from '../../components/ui/SectionTitle.vue'
import Badge from '../../components/ui/Badge.vue'
import ClientForm from './ClientForm.vue'

/** Szczegoly klienta z edycja w miejscu. */
const route = useRoute()
const router = useRouter()
const store = useClientsStore()
const { current, isLoading, error, fieldErrors } = storeToRefs(store)

const clientId = computed(() => Number(route.params.id))

onMounted(() => store.fetchOne(clientId.value))

async function save(payload: ClientPayload): Promise<void> {
  const updated = await store.update(clientId.value, payload)

  if (updated) {
    await router.push({ name: 'clients' })
  }
}

function cancel(): void {
  void router.push({ name: 'clients' })
}

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString('pl-PL')
}
</script>

<template>
  <div class="animate-fade-in">
    <SectionTitle eyebrow="CRM 360°" :title="current?.name ?? 'Klient'" />

    <p
      v-if="error"
      class="mb-4 rounded-glass-sm border border-[rgba(255,75,137,0.35)] bg-[rgba(255,75,137,0.1)] px-3.5 py-2.5 text-[13px] text-magenta-bright"
    >
      {{ error }}
    </p>

    <div class="grid items-start gap-5 lg:grid-cols-[1fr_320px]">
      <GlassCard padding="lg">
        <h3 class="m-0 mb-4 text-[19px] font-bold">Dane klienta</h3>
        <ClientForm
          :client="current"
          :field-errors="fieldErrors"
          :is-saving="isLoading"
          submit-label="Zapisz zmiany"
          @submit="save"
          @cancel="cancel"
        />
      </GlassCard>

      <GlassCard padding="sm">
        <h3 class="m-0 mb-3 text-[15px] font-bold">Podsumowanie</h3>
        <dl v-if="current" class="m-0 flex flex-col gap-2.5 text-[13px]">
          <div class="flex justify-between gap-3">
            <dt class="text-content-variant">Status</dt>
            <dd class="m-0">
              <Badge variant="cyan">{{ current.statusLabel }}</Badge>
            </dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-content-variant">Dodano</dt>
            <dd class="m-0">{{ formatDate(current.createdAt) }}</dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt class="text-content-variant">Ostatnia zmiana</dt>
            <dd class="m-0">{{ formatDate(current.updatedAt) }}</dd>
          </div>
        </dl>
        <p class="mt-4 text-xs text-content-variant">
          Historia zmian z dziennika audytu pojawi się tutaj w kolejnym sprincie.
        </p>
      </GlassCard>
    </div>
  </div>
</template>
