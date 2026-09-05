<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { apiErrorMessage, apiValidationErrors } from '../../api/http'
import { formatPrice, usePricingStore, type PricingPlan, type PricingPlanDraft } from '../../stores/pricing'
import GlassCard from '../../components/ui/GlassCard.vue'
import GlowButton from '../../components/ui/GlowButton.vue'
import SectionTitle from '../../components/ui/SectionTitle.vue'
import Badge from '../../components/ui/Badge.vue'

/**
 * Zarzadzanie cennikiem strony informacyjnej.
 *
 * Ekran dostepny wylacznie dla roli `platform_admin` (administrator calego
 * systemu, nie wlasciciel biura). Straznik trasy w routerze chowa go przed
 * pozostalymi, ale prawdziwa ochrona jest po stronie API - ukrycie przycisku
 * nie jest zabezpieczeniem.
 *
 * FORMULARZ OPERUJE W ZLOTYCH, API W GROSZACH. Przeliczenie siedzi w dwoch
 * funkcjach ponizej i nigdzie indziej - kwoty rozjezdzaja sie zawsze tam,
 * gdzie ta zamiana jest rozsiana po komponencie.
 */
const store = usePricingStore()
const { plans, isLoading, error } = storeToRefs(store)

interface PlanForm {
  code: string
  name: string
  tagline: string
  /** W zlotych, jako tekst z pola formularza. Pusty = wycena indywidualna. */
  priceMonthly: string
  priceYearly: string
  currency: string
  ctaLabel: string
  isFeatured: boolean
  isActive: boolean
  position: number
  /** Jeden punkt na wiersz - najprostsza edycja listy, jaka istnieje. */
  features: string
}

const editedId = ref<number | null>(null)
const isCreating = ref(false)
const form = ref<PlanForm>(emptyForm())
const fieldErrors = ref<Record<string, string[]>>({})
const saveError = ref<string | null>(null)
const isSaving = ref(false)
const pendingDeleteId = ref<number | null>(null)

onMounted(() => {
  void store.loadForAdmin()
})

function emptyForm(): PlanForm {
  return {
    code: '',
    name: '',
    tagline: '',
    priceMonthly: '',
    priceYearly: '',
    currency: 'PLN',
    ctaLabel: '',
    isFeatured: false,
    isActive: true,
    position: nextPosition(),
    features: '',
  }
}

/** Nowy plan lADuje na koncu listy, z zapasem na wstawienie czegos przed nim. */
function nextPosition(): number {
  const positions = (plans.value ?? []).map((plan) => plan.position)

  return positions.length > 0 ? Math.max(...positions) + 10 : 10
}

function groszeToZloty(grosze: number | null): string {
  return grosze === null ? '' : (grosze / 100).toFixed(2)
}

/** Akceptuje przecinek jako separator dziesietny - tak sie pisze ceny po polsku. */
function zlotyToGrosze(value: string): number | null {
  const normalized = value.trim().replace(',', '.')

  if (normalized === '') {
    return null
  }

  return Math.round(Number(normalized) * 100)
}

function startCreate(): void {
  editedId.value = null
  isCreating.value = true
  form.value = emptyForm()
  fieldErrors.value = {}
  saveError.value = null
}

function startEdit(plan: PricingPlan): void {
  editedId.value = plan.id
  isCreating.value = false
  fieldErrors.value = {}
  saveError.value = null
  form.value = {
    code: plan.code,
    name: plan.name,
    tagline: plan.tagline ?? '',
    priceMonthly: groszeToZloty(plan.priceMonthly),
    priceYearly: groszeToZloty(plan.priceYearly),
    currency: plan.currency,
    ctaLabel: plan.ctaLabel ?? '',
    isFeatured: plan.isFeatured,
    isActive: plan.isActive,
    position: plan.position,
    features: plan.features.join('\n'),
  }
}

function cancel(): void {
  editedId.value = null
  isCreating.value = false
  fieldErrors.value = {}
  saveError.value = null
}

function toDraft(): PricingPlanDraft {
  return {
    code: form.value.code.trim(),
    name: form.value.name.trim(),
    tagline: form.value.tagline.trim() || null,
    priceMonthly: zlotyToGrosze(form.value.priceMonthly),
    priceYearly: zlotyToGrosze(form.value.priceYearly),
    currency: form.value.currency.trim().toUpperCase(),
    ctaLabel: form.value.ctaLabel.trim() || null,
    isFeatured: form.value.isFeatured,
    isActive: form.value.isActive,
    position: form.value.position,
    features: form.value.features
      .split('\n')
      .map((line) => line.trim())
      .filter((line) => line !== ''),
  }
}

async function save(): Promise<void> {
  isSaving.value = true
  fieldErrors.value = {}
  saveError.value = null

  try {
    await store.save(editedId.value, toDraft())
    cancel()
  } catch (e) {
    fieldErrors.value = apiValidationErrors(e)
    saveError.value = apiErrorMessage(e, 'Nie udało się zapisać planu.')
  } finally {
    isSaving.value = false
  }
}

async function confirmDelete(id: number): Promise<void> {
  saveError.value = null

  try {
    await store.remove(id)
  } catch (e) {
    saveError.value = apiErrorMessage(e, 'Nie udało się usunąć planu.')
  } finally {
    pendingDeleteId.value = null
  }
}

function firstError(field: string): string | null {
  return fieldErrors.value[field]?.[0] ?? null
}

const inputClass =
  'w-full rounded-glass-sm border border-outline bg-surface-low px-3.5 py-2.5 text-[13px] text-content outline-none focus:border-[rgba(0,219,231,0.5)]'
</script>

<template>
  <div class="animate-fade-in">
    <SectionTitle eyebrow="Administracja systemu" title="Cennik">
      Plany widoczne na stronie informacyjnej. Zmiany są widoczne publicznie od razu po zapisaniu
      i trafiają do dziennika zmian.
    </SectionTitle>

    <p v-if="saveError && !isCreating && editedId === null" class="mb-4 text-[13px] text-magenta-bright">
      {{ saveError }}
    </p>
    <p v-if="error" class="mb-4 text-[13px] text-magenta-bright">{{ error }}</p>

    <div class="mb-5 flex justify-end">
      <GlowButton v-if="!isCreating && editedId === null" @click="startCreate">
        Dodaj plan
      </GlowButton>
    </div>

    <!-- FORMULARZ -->
    <GlassCard v-if="isCreating || editedId !== null" padding="lg" class="mb-6">
      <h3 class="m-0 mb-5 text-[19px] font-bold">
        {{ isCreating ? 'Nowy plan' : `Edycja planu: ${form.name}` }}
      </h3>

      <form class="grid gap-4 md:grid-cols-2" @submit.prevent="save">
        <label class="block">
          <span class="mb-1.5 block text-[12px] font-bold text-content-variant">Kod</span>
          <input v-model="form.code" :class="inputClass" placeholder="np. biuro" />
          <span v-if="firstError('code')" class="mt-1 block text-[12px] text-magenta-bright">
            {{ firstError('code') }}
          </span>
          <span class="mt-1 block text-[11.5px] text-content-variant">
            Stały identyfikator planu — nie zmieniaj go dla planu, który jest już sprzedawany.
          </span>
        </label>

        <label class="block">
          <span class="mb-1.5 block text-[12px] font-bold text-content-variant">Nazwa</span>
          <input v-model="form.name" :class="inputClass" placeholder="np. Biuro" />
          <span v-if="firstError('name')" class="mt-1 block text-[12px] text-magenta-bright">
            {{ firstError('name') }}
          </span>
        </label>

        <label class="block md:col-span-2">
          <span class="mb-1.5 block text-[12px] font-bold text-content-variant">Podtytuł</span>
          <input v-model="form.tagline" :class="inputClass" placeholder="Jedno zdanie: dla kogo jest ten plan." />
          <span v-if="firstError('tagline')" class="mt-1 block text-[12px] text-magenta-bright">
            {{ firstError('tagline') }}
          </span>
        </label>

        <label class="block">
          <span class="mb-1.5 block text-[12px] font-bold text-content-variant">
            Cena miesięczna (netto)
          </span>
          <input v-model="form.priceMonthly" :class="inputClass" inputmode="decimal" placeholder="349,00" />
          <span v-if="firstError('priceMonthly')" class="mt-1 block text-[12px] text-magenta-bright">
            {{ firstError('priceMonthly') }}
          </span>
          <span class="mt-1 block text-[11.5px] text-content-variant">
            Puste pole = „wycena indywidualna" na stronie.
          </span>
        </label>

        <label class="block">
          <span class="mb-1.5 block text-[12px] font-bold text-content-variant">
            Cena roczna (netto)
          </span>
          <input v-model="form.priceYearly" :class="inputClass" inputmode="decimal" placeholder="3490,00" />
          <span v-if="firstError('priceYearly')" class="mt-1 block text-[12px] text-magenta-bright">
            {{ firstError('priceYearly') }}
          </span>
        </label>

        <label class="block">
          <span class="mb-1.5 block text-[12px] font-bold text-content-variant">Waluta</span>
          <input v-model="form.currency" :class="inputClass" maxlength="3" />
          <span v-if="firstError('currency')" class="mt-1 block text-[12px] text-magenta-bright">
            {{ firstError('currency') }}
          </span>
        </label>

        <label class="block">
          <span class="mb-1.5 block text-[12px] font-bold text-content-variant">
            Etykieta przycisku
          </span>
          <input v-model="form.ctaLabel" :class="inputClass" placeholder="Wybierz plan" />
        </label>

        <label class="block md:col-span-2">
          <span class="mb-1.5 block text-[12px] font-bold text-content-variant">
            Punkty planu — jeden w wierszu
          </span>
          <textarea v-model="form.features" :class="inputClass" rows="7" />
          <span v-if="firstError('features')" class="mt-1 block text-[12px] text-magenta-bright">
            {{ firstError('features') }}
          </span>
        </label>

        <label class="block">
          <span class="mb-1.5 block text-[12px] font-bold text-content-variant">Kolejność</span>
          <input v-model.number="form.position" type="number" min="0" :class="inputClass" />
          <span v-if="firstError('position')" class="mt-1 block text-[12px] text-magenta-bright">
            {{ firstError('position') }}
          </span>
        </label>

        <div class="flex flex-col justify-center gap-3">
          <label class="flex items-center gap-2.5 text-[13px]">
            <input v-model="form.isFeatured" type="checkbox" class="h-4 w-4 accent-[#00dbe7]" />
            Wyróżniony („najczęściej wybierany")
          </label>
          <label class="flex items-center gap-2.5 text-[13px]">
            <input v-model="form.isActive" type="checkbox" class="h-4 w-4 accent-[#00dbe7]" />
            Widoczny na stronie
          </label>
        </div>

        <div class="md:col-span-2">
          <p v-if="saveError" class="mb-3 text-[13px] text-magenta-bright">{{ saveError }}</p>
          <div class="flex gap-3">
            <GlowButton type="submit" :disabled="isSaving">
              {{ isSaving ? 'Zapisuję…' : 'Zapisz plan' }}
            </GlowButton>
            <GlowButton variant="ghost" :disabled="isSaving" @click="cancel">Anuluj</GlowButton>
          </div>
        </div>
      </form>
    </GlassCard>

    <!-- LISTA PLANOW -->
    <p v-if="isLoading" class="text-sm text-content-variant">Wczytuję cennik…</p>

    <div v-else class="flex flex-col gap-4">
      <GlassCard v-for="plan in plans" :key="plan.id" padding="lg">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2.5">
              <h3 class="m-0 text-[17px] font-bold">{{ plan.name }}</h3>
              <code class="text-[12px] text-content-variant">{{ plan.code }}</code>
              <Badge v-if="plan.isFeatured" variant="cyan">WYRÓŻNIONY</Badge>
              <Badge :variant="plan.isActive ? 'low' : 'neutral'">
                {{ plan.isActive ? 'WIDOCZNY' : 'UKRYTY' }}
              </Badge>
            </div>
            <p v-if="plan.tagline" class="mt-1.5 text-[13px] text-content-variant">
              {{ plan.tagline }}
            </p>
            <p class="mt-2.5 text-[13px]">
              <span class="font-bold">
                {{ formatPrice(plan.priceMonthly, plan.currency) ?? 'Wycena indywidualna' }}
              </span>
              <span v-if="plan.priceMonthly !== null" class="text-content-variant"> / mies.</span>
              <span v-if="plan.priceYearly !== null" class="text-content-variant">
                · {{ formatPrice(plan.priceYearly, plan.currency) }} / rok
              </span>
            </p>
            <ul class="mt-3 flex list-none flex-col gap-1 p-0 text-[12.5px] text-content-variant">
              <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2">
                <span class="material-symbols-outlined mt-px text-[15px]! text-cyan-bright">
                  check
                </span>
                {{ feature }}
              </li>
            </ul>
          </div>

          <div class="flex shrink-0 flex-col items-end gap-2">
            <span class="text-[11.5px] text-content-variant">poz. {{ plan.position }}</span>
            <div class="flex gap-2">
              <GlowButton variant="ghost" @click="startEdit(plan)">Edytuj</GlowButton>
              <GlowButton
                v-if="pendingDeleteId !== plan.id"
                variant="ghost"
                @click="pendingDeleteId = plan.id"
              >
                Usuń
              </GlowButton>
              <template v-else>
                <GlowButton variant="magenta" @click="confirmDelete(plan.id)">
                  Na pewno usuń
                </GlowButton>
                <GlowButton variant="ghost" @click="pendingDeleteId = null">Nie</GlowButton>
              </template>
            </div>
          </div>
        </div>
      </GlassCard>

      <p v-if="plans.length === 0" class="text-sm text-content-variant">
        Cennik jest pusty. Dodaj pierwszy plan — sekcja cennika na stronie pokaże wtedy zaproszenie
        do kontaktu.
      </p>
    </div>
  </div>
</template>
