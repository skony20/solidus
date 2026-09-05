<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute } from 'vue-router'
import { apiErrorMessage, apiValidationErrors } from '../../api/http'
import { useTenantAdminStore, type TenantStatusValue } from '../../stores/tenants'
import { usePricingStore, formatPrice } from '../../stores/pricing'
import GlassCard from '../../components/ui/GlassCard.vue'
import SectionTitle from '../../components/ui/SectionTitle.vue'
import Badge from '../../components/ui/Badge.vue'
import GlowButton from '../../components/ui/GlowButton.vue'

/**
 * Szczegoly jednego biura z perspektywy operatora: stan konta, plan, lista
 * pracowniczych kont (tylko metadane) i historia platnosci z formularzem
 * do recznego ksiegowania kolejnej.
 */
const route = useRoute()
const tenantId = computed(() => Number(route.params.id))

const store = useTenantAdminStore()
const { detail, isLoadingDetail, error } = storeToRefs(store)

const pricingStore = usePricingStore()
const { plans } = storeToRefs(pricingStore)

const statusBadgeVariant: Record<TenantStatusValue, 'cyan' | 'low' | 'critical' | 'neutral'> = {
  trial: 'cyan',
  active: 'low',
  suspended: 'critical',
  cancelled: 'neutral',
}

const statusActions: Array<{ status: TenantStatusValue; label: string; variant: 'cyan' | 'magenta' | 'ghost' }> = [
  { status: 'trial', label: 'Ustaw jako próbne', variant: 'ghost' },
  { status: 'active', label: 'Aktywuj', variant: 'cyan' },
  { status: 'suspended', label: 'Zawieś dostęp', variant: 'magenta' },
  { status: 'cancelled', label: 'Zakończ współpracę', variant: 'ghost' },
]

const selectedPlanId = ref<string>('')
const isSavingPlan = ref(false)
const planError = ref<string | null>(null)

const paymentForm = ref({
  amount: '',
  currency: 'PLN',
  periodStart: '',
  periodEnd: '',
  status: 'paid' as const,
  provider: 'manual',
  note: '',
})
const isSavingPayment = ref(false)
const paymentErrors = ref<Record<string, string[]>>({})
const paymentError = ref<string | null>(null)

onMounted(async () => {
  await Promise.all([store.loadDetail(tenantId.value), pricingStore.loadForAdmin()])
  selectedPlanId.value = detail.value?.item.pricingPlanId?.toString() ?? ''
})

async function changeStatus(status: TenantStatusValue): Promise<void> {
  await store.changeStatus(tenantId.value, status)
}

async function savePlan(): Promise<void> {
  isSavingPlan.value = true
  planError.value = null

  try {
    const id = selectedPlanId.value === '' ? null : Number(selectedPlanId.value)
    await store.assignPlan(tenantId.value, id)
  } catch (e) {
    planError.value = apiErrorMessage(e, 'Nie udało się przypisać planu.')
  } finally {
    isSavingPlan.value = false
  }
}

/** Formularz operuje w zlotych - to samo przeliczenie co w PricingAdminView. */
function zlotyToGrosze(value: string): number {
  return Math.round(Number(value.trim().replace(',', '.')) * 100)
}

async function submitPayment(): Promise<void> {
  isSavingPayment.value = true
  paymentErrors.value = {}
  paymentError.value = null

  try {
    await store.recordPayment(tenantId.value, {
      amount: zlotyToGrosze(paymentForm.value.amount),
      currency: paymentForm.value.currency,
      periodStart: paymentForm.value.periodStart,
      periodEnd: paymentForm.value.periodEnd,
      status: paymentForm.value.status,
      provider: paymentForm.value.provider,
      note: paymentForm.value.note || null,
    })
    paymentForm.value = {
      amount: '',
      currency: 'PLN',
      periodStart: '',
      periodEnd: '',
      status: 'paid',
      provider: 'manual',
      note: '',
    }
  } catch (e) {
    paymentErrors.value = apiValidationErrors(e)
    paymentError.value = apiErrorMessage(e, 'Nie udało się zapisać płatności.')
  } finally {
    isSavingPayment.value = false
  }
}

const inputClass =
  'w-full rounded-glass-sm border border-outline bg-surface-low px-3.5 py-2.5 text-[13px] text-content outline-none focus:border-[rgba(0,219,231,0.5)]'
</script>

<template>
  <div class="animate-fade-in">
    <RouterLink to="/admin/biura" class="mb-4 inline-block text-[13px] text-cyan-bright no-underline">
      ← Wróć do listy biur
    </RouterLink>

    <p v-if="isLoadingDetail" class="text-sm text-content-variant">Wczytuję biuro…</p>
    <p v-else-if="error" class="text-sm text-magenta-bright">{{ error }}</p>

    <template v-else-if="detail">
      <SectionTitle eyebrow="Administracja systemu" :title="detail.item.name">
        {{ detail.item.slug }} · założone {{ new Date(detail.item.createdAt).toLocaleDateString('pl-PL') }}
      </SectionTitle>

      <div class="grid gap-5 lg:grid-cols-[1.3fr_1fr]">
        <div class="flex flex-col gap-5">
          <!-- STAN KONTA -->
          <GlassCard padding="lg">
            <div class="mb-4 flex items-center justify-between">
              <h3 class="m-0 text-[17px] font-bold">Stan konta</h3>
              <Badge :variant="statusBadgeVariant[detail.item.status]">
                {{ detail.item.statusLabel }}
              </Badge>
            </div>
            <div class="flex flex-wrap gap-2.5">
              <GlowButton
                v-for="action in statusActions"
                :key="action.status"
                :variant="action.variant"
                :disabled="detail.item.status === action.status"
                @click="changeStatus(action.status)"
              >
                {{ action.label }}
              </GlowButton>
            </div>
          </GlassCard>

          <!-- PLAN -->
          <GlassCard padding="lg">
            <h3 class="m-0 mb-4 text-[17px] font-bold">Plan abonamentowy</h3>
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="savePlan">
              <label class="min-w-[220px] flex-1">
                <span class="mb-1.5 block text-[12px] font-bold text-content-variant">Plan z cennika</span>
                <select v-model="selectedPlanId" :class="inputClass">
                  <option value="">— wycena indywidualna (poza katalogiem) —</option>
                  <option v-for="plan in plans" :key="plan.id" :value="plan.id.toString()">
                    {{ plan.name }}{{ plan.isActive ? '' : ' (ukryty)' }}
                    <template v-if="plan.priceMonthly !== null">
                      — {{ formatPrice(plan.priceMonthly, plan.currency) }}/mies.
                    </template>
                  </option>
                </select>
              </label>
              <GlowButton type="submit" :disabled="isSavingPlan">
                {{ isSavingPlan ? 'Zapisuję…' : 'Zapisz plan' }}
              </GlowButton>
            </form>
            <p v-if="planError" class="mt-2.5 text-[13px] text-magenta-bright">{{ planError }}</p>
          </GlassCard>

          <!-- HISTORIA PLATNOSCI -->
          <GlassCard padding="lg">
            <h3 class="m-0 mb-4 text-[17px] font-bold">Historia płatności</h3>

            <div v-if="detail.payments.length === 0" class="mb-5 text-sm text-content-variant">
              Brak odnotowanych płatności.
            </div>
            <div v-else class="mb-5 flex flex-col gap-2.5">
              <div
                v-for="payment in detail.payments"
                :key="payment.id"
                class="hairline flex flex-wrap items-center justify-between gap-2 rounded-2xl px-4 py-3 text-[13px]"
              >
                <div>
                  <span class="font-bold">{{ formatPrice(payment.amount, payment.currency) }}</span>
                  <span class="ml-2 text-content-variant">
                    {{ payment.periodStart }} → {{ payment.periodEnd }}
                  </span>
                </div>
                <div class="flex items-center gap-2 text-content-variant">
                  <span>{{ payment.provider }}</span>
                  <Badge :variant="payment.status === 'paid' ? 'low' : payment.status === 'failed' ? 'critical' : 'elevated'">
                    {{ payment.statusLabel }}
                  </Badge>
                </div>
                <div v-if="payment.note" class="w-full text-[12px] text-content-variant">
                  {{ payment.note }}
                </div>
              </div>
            </div>

            <h4 class="m-0 mb-3 text-[14px] font-bold text-content-variant">Odnotuj nową płatność</h4>
            <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="submitPayment">
              <label>
                <span class="mb-1.5 block text-[12px] font-bold text-content-variant">Kwota (netto)</span>
                <input v-model="paymentForm.amount" :class="inputClass" inputmode="decimal" placeholder="349,00" />
                <span v-if="paymentErrors.amount" class="mt-1 block text-[12px] text-magenta-bright">
                  {{ paymentErrors.amount[0] }}
                </span>
              </label>
              <label>
                <span class="mb-1.5 block text-[12px] font-bold text-content-variant">Waluta</span>
                <input v-model="paymentForm.currency" :class="inputClass" maxlength="3" />
              </label>
              <label>
                <span class="mb-1.5 block text-[12px] font-bold text-content-variant">Początek okresu</span>
                <input v-model="paymentForm.periodStart" type="date" :class="inputClass" />
                <span v-if="paymentErrors.periodStart" class="mt-1 block text-[12px] text-magenta-bright">
                  {{ paymentErrors.periodStart[0] }}
                </span>
              </label>
              <label>
                <span class="mb-1.5 block text-[12px] font-bold text-content-variant">Koniec okresu</span>
                <input v-model="paymentForm.periodEnd" type="date" :class="inputClass" />
                <span v-if="paymentErrors.periodEnd" class="mt-1 block text-[12px] text-magenta-bright">
                  {{ paymentErrors.periodEnd[0] }}
                </span>
              </label>
              <label class="sm:col-span-2">
                <span class="mb-1.5 block text-[12px] font-bold text-content-variant">Notatka</span>
                <input
                  v-model="paymentForm.note"
                  :class="inputClass"
                  placeholder="np. przelew z 5.09, potwierdzenie w mailu"
                />
              </label>
              <div class="sm:col-span-2">
                <p v-if="paymentError" class="mb-2.5 text-[13px] text-magenta-bright">{{ paymentError }}</p>
                <GlowButton type="submit" :disabled="isSavingPayment">
                  {{ isSavingPayment ? 'Zapisuję…' : 'Zapisz płatność' }}
                </GlowButton>
              </div>
            </form>
          </GlassCard>
        </div>

        <!-- KONTA PRACOWNICZE -->
        <GlassCard padding="lg" class="h-fit">
          <h3 class="m-0 mb-1 text-[17px] font-bold">Konta w biurze</h3>
          <p class="mb-4 text-[12px] text-content-variant">
            Wyłącznie identyfikacja konta — żadnych danych klientów biura.
          </p>
          <div class="flex flex-col gap-2.5">
            <div
              v-for="account in detail.users"
              :key="account.id"
              class="hairline rounded-2xl px-4 py-3 text-[13px]"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="font-bold">{{ account.name }}</span>
                <Badge :variant="account.isActive ? 'low' : 'neutral'">
                  {{ account.isActive ? 'aktywne' : 'wyłączone' }}
                </Badge>
              </div>
              <div class="mt-0.5 text-[12px] text-content-variant">{{ account.email }}</div>
              <div class="mt-1 flex flex-wrap gap-1.5">
                <span
                  v-for="role in account.roles"
                  :key="role"
                  class="rounded-full bg-surface-highest px-2 py-0.5 text-[10.5px] font-bold text-content-variant"
                >
                  {{ role }}
                </span>
              </div>
            </div>
          </div>
        </GlassCard>
      </div>
    </template>
  </div>
</template>
