<script setup lang="ts">
import { reactive, watch } from 'vue'
import type { Client, ClientPayload } from '../../stores/clients'
import GlowButton from '../../components/ui/GlowButton.vue'

/**
 * Formularz klienta - ten sam dla dodawania i edycji.
 *
 * Bledy walidacji przychodza z API (`fieldErrors`), wiec regula biznesowa
 * jest zapisana raz, po stronie serwera, a nie zdublowana w przegladarce.
 */
const props = defineProps<{
  client?: Client | null
  fieldErrors: Record<string, string[]>
  isSaving: boolean
  submitLabel: string
}>()

const emit = defineEmits<{ submit: [payload: ClientPayload]; cancel: [] }>()

const form = reactive<ClientPayload>({
  name: '',
  nip: '',
  email: '',
  phone: '',
  address: '',
  status: 'lead',
  notes: '',
})

const statuses = [
  { value: 'lead', label: 'Potencjalny' },
  { value: 'onboarding', label: 'Wdrożenie' },
  { value: 'active', label: 'Aktywny' },
  { value: 'suspended', label: 'Zawieszony' },
  { value: 'archived', label: 'Archiwum' },
]

watch(
  () => props.client,
  (client) => {
    if (client) {
      Object.assign(form, {
        name: client.name,
        nip: client.nip ?? '',
        email: client.email ?? '',
        phone: client.phone ?? '',
        address: client.address ?? '',
        status: client.status,
        notes: client.notes ?? '',
      })
    }
  },
  { immediate: true },
)

const inputClass =
  'w-full rounded-glass-sm border border-outline bg-surface-low px-3.5 py-2.5 text-sm text-content outline-none placeholder:text-content-variant focus:border-[rgba(0,219,231,0.5)]'
</script>

<template>
  <form class="flex flex-col gap-3" @submit.prevent="emit('submit', { ...form })">
    <label class="flex flex-col gap-1.5">
      <span class="text-xs font-semibold text-content-variant">Nazwa firmy *</span>
      <input v-model="form.name" required :class="inputClass" placeholder="np. Piekarnia Złoty Kłos sp. z o.o." />
      <span v-for="msg in fieldErrors.name" :key="msg" class="text-xs text-magenta-bright">{{ msg }}</span>
    </label>

    <div class="grid gap-3 sm:grid-cols-2">
      <label class="flex flex-col gap-1.5">
        <span class="text-xs font-semibold text-content-variant">NIP</span>
        <input v-model="form.nip" :class="inputClass" placeholder="527-00-00-001" />
        <span v-for="msg in fieldErrors.nip" :key="msg" class="text-xs text-magenta-bright">{{ msg }}</span>
      </label>

      <label class="flex flex-col gap-1.5">
        <span class="text-xs font-semibold text-content-variant">Status</span>
        <select v-model="form.status" :class="inputClass">
          <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
      </label>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
      <label class="flex flex-col gap-1.5">
        <span class="text-xs font-semibold text-content-variant">E-mail</span>
        <input v-model="form.email" type="email" :class="inputClass" />
        <span v-for="msg in fieldErrors.email" :key="msg" class="text-xs text-magenta-bright">{{ msg }}</span>
      </label>

      <label class="flex flex-col gap-1.5">
        <span class="text-xs font-semibold text-content-variant">Telefon</span>
        <input v-model="form.phone" :class="inputClass" />
      </label>
    </div>

    <label class="flex flex-col gap-1.5">
      <span class="text-xs font-semibold text-content-variant">Adres</span>
      <input v-model="form.address" :class="inputClass" />
    </label>

    <label class="flex flex-col gap-1.5">
      <span class="text-xs font-semibold text-content-variant">Notatki</span>
      <textarea v-model="form.notes" rows="3" :class="inputClass" />
    </label>

    <div class="mt-1.5 flex gap-2">
      <GlowButton type="submit" :disabled="isSaving">
        {{ isSaving ? 'Zapisywanie...' : submitLabel }}
      </GlowButton>
      <GlowButton variant="ghost" @click="emit('cancel')">Anuluj</GlowButton>
    </div>
  </form>
</template>
