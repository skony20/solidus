<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { formatPrice, usePricingStore } from '../../stores/pricing'

/**
 * Sekcja cennika na stronie informacyjnej.
 *
 * Tresc pochodzi w calosci z bazy (GET /api/pricing) - w kodzie nie ma ani
 * jednej kwoty. Zmiana ceny to edycja w panelu administratora systemu, nie
 * wdrozenie nowej wersji frontendu.
 *
 * Przelacznik miesiac/rok liczy oszczednosc sam, na podstawie obu cen planu,
 * zamiast pokazywac wpisany recznie procent - inaczej rabat na stronie
 * rozjechalby sie z cenami przy pierwszej podwyzce.
 */
const store = usePricingStore()
const { activePlans, isLoading, error } = storeToRefs(store)

type Period = 'monthly' | 'yearly'
const period = ref<Period>('monthly')

onMounted(() => {
  void store.load()
})

/** Pokazujemy przelacznik tylko wtedy, gdy ktorykolwiek plan ma obie ceny. */
const hasYearlyOption = computed(() =>
  activePlans.value.some((plan) => plan.priceMonthly !== null && plan.priceYearly !== null),
)

/** Najwieksza oszczednosc roczna wsrod planow, w pelnych procentach. */
const yearlyDiscount = computed(() => {
  const discounts = activePlans.value
    .filter((plan) => plan.priceMonthly !== null && plan.priceYearly !== null)
    .map((plan) => 1 - plan.priceYearly! / (plan.priceMonthly! * 12))
    .filter((value) => value > 0)

  return discounts.length > 0 ? Math.round(Math.max(...discounts) * 100) : 0
})

function priceLabel(plan: { priceMonthly: number | null; priceYearly: number | null; currency: string }) {
  const grosze = period.value === 'monthly' ? plan.priceMonthly : plan.priceYearly

  return formatPrice(grosze, plan.currency)
}
</script>

<template>
  <section id="cennik" class="py-[74px]">
    <div class="mx-auto max-w-[1180px] px-8">
      <div class="mx-auto mb-10 max-w-[560px] text-center">
        <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-content-variant">
          Cennik
        </div>
        <h2 class="my-1.5 text-[clamp(24px,3vw,32px)] font-extrabold tracking-[-0.01em]">
          Tyle kosztuje spokój
        </h2>
        <p class="mx-auto mt-3.5 text-[15.5px] leading-relaxed text-content-variant">
          Bez opłaty wdrożeniowej i bez umowy na rok. Możesz zmienić plan albo zrezygnować
          w dowolnym momencie.
        </p>
      </div>

      <!-- Przelacznik okresu rozliczeniowego -->
      <div v-if="hasYearlyOption" class="mb-10 flex items-center justify-center gap-2">
        <div class="hairline inline-flex rounded-full bg-surface-low p-1">
          <button
            v-for="option in (['monthly', 'yearly'] as const)"
            :key="option"
            type="button"
            class="rounded-full px-5 py-2 text-[13px] font-bold transition-all duration-150"
            :class="
              period === option
                ? 'bg-linear-to-br from-cyan-bright to-cyan text-[#00363a]'
                : 'text-content-variant hover:text-content'
            "
            @click="period = option"
          >
            {{ option === 'monthly' ? 'Miesięcznie' : 'Rocznie' }}
          </button>
        </div>
        <span
          v-if="yearlyDiscount > 0"
          class="rounded-full border border-[rgba(0,226,139,0.35)] bg-[rgba(0,226,139,0.12)] px-3 py-1 text-[11.5px] font-bold text-emerald-bright"
        >
          −{{ yearlyDiscount }}% przy płatności rocznej
        </span>
      </div>

      <!-- Stany brzegowe: cennik jest danymi z bazy, wiec moze go chwilowo nie byc -->
      <p v-if="isLoading" class="py-10 text-center text-sm text-content-variant">
        Wczytuję cennik…
      </p>
      <p v-else-if="error" class="py-10 text-center text-sm text-magenta-bright">
        {{ error }}
      </p>
      <p v-else-if="activePlans.length === 0" class="py-10 text-center text-sm text-content-variant">
        Cennik jest w tej chwili aktualizowany. Napisz do nas — podamy wycenę indywidualnie.
      </p>

      <div v-else class="grid gap-5 lg:grid-cols-3">
        <div
          v-for="plan in activePlans"
          :key="plan.id"
          class="glass relative flex flex-col p-8"
          :class="plan.isFeatured && 'border-[rgba(0,219,231,0.35)] lg:-translate-y-2'"
        >
          <span
            v-if="plan.isFeatured"
            class="absolute -top-3 left-8 rounded-full border border-[rgba(0,219,231,0.35)] bg-[rgba(0,219,231,0.14)] px-3 py-1 text-[11px] font-bold tracking-[0.04em] text-cyan-bright backdrop-blur-[20px]"
          >
            NAJCZĘŚCIEJ WYBIERANY
          </span>

          <h3 class="text-[20px] font-extrabold">{{ plan.name }}</h3>
          <p v-if="plan.tagline" class="mt-2 min-h-[42px] text-[13.5px] leading-relaxed text-content-variant">
            {{ plan.tagline }}
          </p>

          <div class="mt-6 flex items-baseline gap-2">
            <template v-if="priceLabel(plan)">
              <span class="text-[34px] font-extrabold leading-none">{{ priceLabel(plan) }}</span>
              <span class="text-[13px] text-content-variant">
                netto / {{ period === 'monthly' ? 'mies.' : 'rok' }}
              </span>
            </template>
            <span v-else class="text-[24px] font-extrabold leading-none text-cyan-bright">
              Wycena indywidualna
            </span>
          </div>

          <ul class="mt-7 flex flex-1 list-none flex-col gap-2.5 p-0 text-[13.5px] text-content-variant">
            <li v-for="feature in plan.features" :key="feature" class="flex items-start gap-2.5">
              <span class="material-symbols-outlined mt-px text-[18px]! text-cyan-bright">check</span>
              {{ feature }}
            </li>
          </ul>

          <a
            href="#audyt"
            class="mt-8 block rounded-nav px-5 py-3.5 text-center text-sm font-bold no-underline transition-all duration-150 hover:-translate-y-px"
            :class="
              plan.isFeatured
                ? 'bg-linear-to-br from-cyan-bright to-cyan text-[#00363a] hover:shadow-glow'
                : 'border border-outline text-content hover:border-[rgba(0,219,231,0.4)] hover:text-cyan-bright'
            "
          >
            {{ plan.ctaLabel ?? 'Wybierz plan' }}
          </a>
        </div>
      </div>

      <p class="mt-8 text-center text-[12px] text-content-variant">
        Ceny netto, do każdej doliczamy VAT. Rozliczenie na podstawie faktury.
      </p>
    </div>
  </section>
</template>
