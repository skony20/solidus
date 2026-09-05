import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { apiErrorMessage, http } from '../api/http'

/**
 * Plan abonamentowy z cennika.
 *
 * Ceny sa w GROSZACH - tak jak w bazie i w API. Formatowanie na "149,00 zl"
 * robi funkcja formatPrice, a nie backend: kwota jako napis nie da sie
 * przeliczyc ani posortowac.
 *
 * null w cenie znaczy "wycena indywidualna", co jest czyms innym niz 0.
 */
export interface PricingPlan {
  id: number
  code: string
  name: string
  tagline: string | null
  priceMonthly: number | null
  priceYearly: number | null
  currency: string
  ctaLabel: string | null
  isFeatured: boolean
  isActive: boolean
  position: number
  features: string[]
}

/** Ksztalt wysylany do API przy zapisie - bez pol nadawanych przez serwer. */
export type PricingPlanDraft = Omit<PricingPlan, 'id'>

export function formatPrice(grosze: number | null, currency = 'PLN'): string | null {
  if (grosze === null) {
    return null
  }

  return new Intl.NumberFormat('pl-PL', {
    style: 'currency',
    currency,
    // Ceny abonamentu sa okragle; groszowa koncowka tylko zasmieca cennik.
    minimumFractionDigits: grosze % 100 === 0 ? 0 : 2,
    maximumFractionDigits: 2,
  }).format(grosze / 100)
}

/**
 * Cennik: publiczny odczyt dla strony informacyjnej i zapis dla administratora
 * systemu.
 *
 * Jeden store obsluguje oba zastosowania, bo operuja na tych samych danych -
 * rozni je tylko endpoint. Publiczny `load()` nie wymaga tokenu, wiec dziala
 * na stronie ogladanej przez niezalogowanego goscia.
 */
export const usePricingStore = defineStore('pricing', () => {
  const plans = ref<PricingPlan[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const activePlans = computed(() => plans.value.filter((plan) => plan.isActive))

  /** Publiczny cennik - wylacznie plany aktywne, bez uwierzytelnienia. */
  async function load(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const { data } = await http.get<{ items: PricingPlan[] }>('/pricing')
      plans.value = data.items
    } catch (e) {
      error.value = apiErrorMessage(e, 'Nie udało się pobrać cennika.')
    } finally {
      isLoading.value = false
    }
  }

  /** Widok administratora - takze plany wyłączone. */
  async function loadForAdmin(): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const { data } = await http.get<{ items: PricingPlan[] }>('/admin/pricing')
      plans.value = data.items
    } catch (e) {
      error.value = apiErrorMessage(e, 'Nie udało się pobrać cennika.')
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Zapis planu. Zwraca true przy powodzeniu; bledy walidacji per pole
   * wyciaga z wyjatku sam formularz (apiValidationErrors), zeby podswietlic
   * konkretne pola - dlatego wyjatek leci dalej.
   */
  async function save(id: number | null, draft: PricingPlanDraft): Promise<void> {
    if (id === null) {
      await http.post('/admin/pricing', draft)
    } else {
      await http.put(`/admin/pricing/${id}`, draft)
    }

    await loadForAdmin()
  }

  async function remove(id: number): Promise<void> {
    await http.delete(`/admin/pricing/${id}`)
    await loadForAdmin()
  }

  return { plans, activePlans, isLoading, error, load, loadForAdmin, save, remove }
})
