import { defineStore } from 'pinia'
import { ref } from 'vue'
import { http } from '../api/http'

/**
 * Biuro widziane oczami operatora Solidusa - lista i szczegoly na panel
 * administracyjny (/admin/biura). Odpowiada backendowemu TenantOverview.
 *
 * Ceny sa w GROSZACH, tak jak w Module\Pricing - formatowanie robi
 * formatPrice() ze stores/pricing.ts, nie backend.
 */
export type TenantStatusValue = 'trial' | 'active' | 'suspended' | 'cancelled'

export interface TenantOverview {
  id: number
  name: string
  slug: string
  plan: string
  status: TenantStatusValue
  statusLabel: string
  pricingPlanId: number | null
  createdAt: string
  planCode: string | null
  planName: string | null
  userCount: number
  paidUntil: string | null
  isPaidUpToDate: boolean
}

/** Metadane konta pracownika biura - celowo bez niczego merytorycznego. */
export interface TenantUserSummary {
  id: number
  email: string
  name: string
  roles: string[]
  isActive: boolean
  createdAt: string
}

export interface TenantPayment {
  id: number
  tenantId: number
  amount: number
  currency: string
  periodStart: string
  periodEnd: string
  status: 'paid' | 'pending' | 'failed' | 'refunded'
  statusLabel: string
  provider: string
  providerReference: string | null
  note: string | null
  createdAt: string
}

export interface TenantDetail {
  item: TenantOverview
  users: TenantUserSummary[]
  payments: TenantPayment[]
}

export interface TenantPaymentDraft {
  amount: number
  currency: string
  periodStart: string
  periodEnd: string
  status: TenantPayment['status']
  provider: string
  providerReference?: string | null
  note?: string | null
}

export const useTenantAdminStore = defineStore('tenantAdmin', () => {
  const items = ref<TenantOverview[]>([])
  const total = ref(0)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const detail = ref<TenantDetail | null>(null)
  const isLoadingDetail = ref(false)

  async function load(search = '', status = ''): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const { data } = await http.get<{ items: TenantOverview[]; total: number }>('/admin/tenants', {
        params: { search: search || undefined, status: status || undefined, limit: 200 },
      })
      items.value = data.items
      total.value = data.total
    } catch {
      error.value = 'Nie udało się pobrać listy biur.'
    } finally {
      isLoading.value = false
    }
  }

  async function loadDetail(id: number): Promise<void> {
    isLoadingDetail.value = true
    error.value = null
    detail.value = null

    try {
      const { data } = await http.get<TenantDetail>(`/admin/tenants/${id}`)
      detail.value = data
    } catch {
      error.value = 'Nie udało się pobrać danych biura.'
    } finally {
      isLoadingDetail.value = false
    }
  }

  async function changeStatus(id: number, status: TenantStatusValue): Promise<void> {
    await http.put(`/admin/tenants/${id}/status`, { status })
    await loadDetail(id)
  }

  async function assignPlan(id: number, pricingPlanId: number | null): Promise<void> {
    await http.put(`/admin/tenants/${id}/plan`, { pricingPlanId })
    await loadDetail(id)
  }

  async function recordPayment(id: number, draft: TenantPaymentDraft): Promise<void> {
    await http.post(`/admin/tenants/${id}/payments`, draft)
    await loadDetail(id)
  }

  return {
    items,
    total,
    isLoading,
    error,
    detail,
    isLoadingDetail,
    load,
    loadDetail,
    changeStatus,
    assignPlan,
    recordPayment,
  }
})
