import { defineStore } from 'pinia'
import { ref } from 'vue'
import { apiErrorMessage, apiValidationErrors, http } from '../api/http'

export interface Client {
  id: number
  name: string
  nip: string | null
  email: string | null
  phone: string | null
  address: string | null
  status: string
  statusLabel: string
  notes: string | null
  createdAt: string
  updatedAt: string
}

/** Dane wysylane przy tworzeniu i edycji - bez pol nadawanych przez serwer. */
export type ClientPayload = Pick<
  Client,
  'name' | 'nip' | 'email' | 'phone' | 'address' | 'status' | 'notes'
>

/**
 * Stan modulu Klienci - wzorzec dla kolejnych modulow.
 *
 * Store rozmawia z API i wystawia widokom gotowe dane oraz stany
 * posrednie (ladowanie, blad, bledy walidacji per pole).
 */
export const useClientsStore = defineStore('clients', () => {
  const items = ref<Client[]>([])
  const current = ref<Client | null>(null)
  const total = ref(0)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const fieldErrors = ref<Record<string, string[]>>({})

  async function fetchAll(search = '', status = ''): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const { data } = await http.get<{ items: Client[]; total: number }>('/clients', {
        params: { search: search || undefined, status: status || undefined },
      })
      items.value = data.items
      total.value = data.total
    } catch (e) {
      error.value = apiErrorMessage(e, 'Nie udało się pobrać listy klientów.')
    } finally {
      isLoading.value = false
    }
  }

  async function fetchOne(id: number): Promise<void> {
    isLoading.value = true
    error.value = null

    try {
      const { data } = await http.get<{ item: Client }>(`/clients/${id}`)
      current.value = data.item
    } catch (e) {
      error.value = apiErrorMessage(e, 'Nie udało się pobrać danych klienta.')
    } finally {
      isLoading.value = false
    }
  }

  async function create(payload: ClientPayload): Promise<Client | null> {
    return save(() => http.post<{ item: Client }>('/clients', payload))
  }

  async function update(id: number, payload: ClientPayload): Promise<Client | null> {
    return save(() => http.put<{ item: Client }>(`/clients/${id}`, payload))
  }

  async function remove(id: number): Promise<boolean> {
    error.value = null

    try {
      await http.delete(`/clients/${id}`)
      items.value = items.value.filter((client) => client.id !== id)
      total.value = Math.max(total.value - 1, 0)

      return true
    } catch (e) {
      error.value = apiErrorMessage(e, 'Nie udało się usunąć klienta.')
      return false
    }
  }

  /** Wspolna obsluga zapisu - rozroznia blad walidacji od awarii. */
  async function save(request: () => Promise<{ data: { item: Client } }>): Promise<Client | null> {
    isLoading.value = true
    error.value = null
    fieldErrors.value = {}

    try {
      const { data } = await request()
      current.value = data.item

      return data.item
    } catch (e) {
      fieldErrors.value = apiValidationErrors(e)
      error.value = apiErrorMessage(e, 'Nie udało się zapisać klienta.')

      return null
    } finally {
      isLoading.value = false
    }
  }

  return {
    items,
    current,
    total,
    isLoading,
    error,
    fieldErrors,
    fetchAll,
    fetchOne,
    create,
    update,
    remove,
  }
})
