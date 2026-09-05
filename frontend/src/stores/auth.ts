import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { apiErrorMessage, http, setAccessToken } from '../api/http'

export interface AuthUser {
  id: number
  email: string
  name: string
  roles: string[]
  isActive: boolean
}

export interface AuthTenant {
  id: number
  name: string
  slug: string
  plan: string
}

/**
 * Stan zalogowanego uzytkownika i jego biura.
 *
 * Token nie jest tu trzymany - siedzi w module api/http.ts, w zwyklej
 * zmiennej. Store przechowuje tylko to, co interfejs faktycznie wyswietla.
 */
export const useAuthStore = defineStore('auth', () => {
  const user = ref<AuthUser | null>(null)
  const tenant = ref<AuthTenant | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed(() => user.value !== null)

  /**
   * Administrator calego systemu (nie wlasciciel biura) - jedyna rola, ktora
   * siega poza wlasnego tenanta. Dzis odblokowuje zarzadzanie cennikiem strony
   * informacyjnej. Nazwa roli musi zgadzac sie ze stala Role::PLATFORM_ADMIN
   * po stronie backendu.
   */
  const isPlatformAdmin = computed(() => user.value?.roles.includes('platform_admin') === true)

  async function login(tenantSlug: string, email: string, password: string): Promise<boolean> {
    isLoading.value = true
    error.value = null

    try {
      const { data } = await http.post<{
        accessToken: string
        user: AuthUser
        tenant: AuthTenant
      }>('/auth/login', { tenant: tenantSlug, email, password })

      setAccessToken(data.accessToken)
      user.value = data.user
      tenant.value = data.tenant

      return true
    } catch (e) {
      error.value = apiErrorMessage(e, 'Nie udało się zalogować.')
      return false
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Po odswiezeniu strony access token przepada (zyje tylko w pamieci),
   * ale ciasteczko refresh zostaje. Ta metoda probuje na jego podstawie
   * odtworzyc sesje, zanim odeslemy uzytkownika na ekran logowania.
   */
  async function restoreSession(): Promise<boolean> {
    try {
      const { data } = await http.post<{ accessToken: string; user: AuthUser }>('/auth/refresh')
      setAccessToken(data.accessToken)
      user.value = data.user

      const me = await http.get<{ user: AuthUser; tenant: AuthTenant }>('/auth/me')
      user.value = me.data.user
      tenant.value = me.data.tenant

      return true
    } catch {
      clear()
      return false
    }
  }

  /**
   * Unieważnia sesję po stronie serwera i czyści stan.
   *
   * Store celowo nie przekierowuje sam - nie importuje routera, bo router
   * importuje store (strażnik tras). Taki cykl sprawiał, że jeden z modułów
   * dostawał drugi w połowie zainicjalizowany. Przekierowanie robi ten,
   * kto wywołuje logout.
   */
  async function logout(): Promise<void> {
    try {
      await http.post('/auth/logout')
    } finally {
      clear()
    }
  }

  function clear(): void {
    setAccessToken(null)
    user.value = null
    tenant.value = null
  }

  return {
    user,
    tenant,
    isLoading,
    error,
    isAuthenticated,
    isPlatformAdmin,
    login,
    logout,
    restoreSession,
    clear,
  }
})
