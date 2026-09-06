import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { apiErrorMessage, apiValidationErrors, http, setAccessToken } from '../api/http'

export interface AuthUser {
  id: number
  email: string
  name: string
  roles: string[]
  isActive: boolean
  emailVerified: boolean
}

export interface RegisterResult {
  ok: boolean
  /** Slug nadany nowemu biuru - potrzebny do kroku z kodem. */
  tenantSlug?: string
  /** false, gdy konto powstalo, ale maila z kodem nie udalo sie wyslac. */
  emailSent?: boolean
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
  /** Bledy walidacji per pole z ostatniej proby (rejestracja) - do podswietlenia formularza. */
  const validationErrors = ref<Record<string, string[]>>({})

  /**
   * Ustawiane, gdy logowanie odbilo sie o niepotwierdzony adres e-mail albo
   * po rejestracji - ekran /rejestracja czyta to, zeby od razu pokazac krok
   * z kodem dla wlasciwego biura.
   */
  const pendingVerification = ref<{ tenantSlug: string; email: string } | null>(null)

  const isAuthenticated = computed(() => user.value !== null)

  /**
   * Administrator calego systemu (nie wlasciciel biura) - jedyna rola, ktora
   * siega poza wlasnego tenanta. Dzis odblokowuje zarzadzanie cennikiem strony
   * informacyjnej. Nazwa roli musi zgadzac sie ze stala Role::PLATFORM_ADMIN
   * po stronie backendu.
   */
  const isPlatformAdmin = computed(() => user.value?.roles.includes('platform_admin') === true)

  interface AuthPayload {
    accessToken: string
    user: AuthUser
    tenant?: AuthTenant
  }

  function applyAuthPayload(data: AuthPayload): void {
    setAccessToken(data.accessToken)
    user.value = data.user
    if (data.tenant) {
      tenant.value = data.tenant
    }
    pendingVerification.value = null
  }

  async function login(tenantSlug: string, email: string, password: string): Promise<boolean> {
    isLoading.value = true
    error.value = null

    try {
      const { data } = await http.post<AuthPayload>('/auth/login', {
        tenant: tenantSlug,
        email,
        password,
      })

      applyAuthPayload(data)

      return true
    } catch (e) {
      // Konto istnieje, ale adres e-mail nie zostal jeszcze potwierdzony -
      // zapamietujemy dane, zeby ekran rejestracji pokazal krok z kodem.
      if (apiValidationErrors(e).reason?.[0] === 'email_unverified') {
        pendingVerification.value = { tenantSlug, email }
      }

      error.value = apiErrorMessage(e, 'Nie udało się zalogować.')
      return false
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Zaklada nowe biuro. Konto powstaje niezweryfikowane - dopiero podanie
   * kodu z maila (verifyEmail) je aktywuje i loguje.
   */
  async function register(
    tenantName: string,
    name: string,
    email: string,
    password: string,
  ): Promise<RegisterResult> {
    isLoading.value = true
    error.value = null
    validationErrors.value = {}

    try {
      const { data } = await http.post<{
        tenant: { slug: string; name: string }
        email: string
        emailSent: boolean
      }>('/auth/register', { tenantName, name, email, password })

      pendingVerification.value = { tenantSlug: data.tenant.slug, email: data.email }

      return { ok: true, tenantSlug: data.tenant.slug, emailSent: data.emailSent }
    } catch (e) {
      error.value = apiErrorMessage(e, 'Nie udało się założyć konta.')
      validationErrors.value = apiValidationErrors(e)
      return { ok: false }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Potwierdza adres kodem z maila. Po sukcesie uzytkownik jest zalogowany
   * (backend oddaje token tak jak przy logowaniu).
   */
  async function verifyEmail(tenantSlug: string, email: string, code: string): Promise<boolean> {
    isLoading.value = true
    error.value = null

    try {
      const { data } = await http.post<AuthPayload>('/auth/verify-email', {
        tenant: tenantSlug,
        email,
        code,
      })

      applyAuthPayload(data)

      return true
    } catch (e) {
      error.value = apiErrorMessage(e, 'Nie udało się potwierdzić adresu.')
      return false
    } finally {
      isLoading.value = false
    }
  }

  async function resendCode(tenantSlug: string, email: string): Promise<boolean> {
    error.value = null

    try {
      await http.post('/auth/resend-code', { tenant: tenantSlug, email })
      return true
    } catch (e) {
      error.value = apiErrorMessage(e, 'Nie udało się wysłać kodu ponownie.')
      return false
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
    pendingVerification.value = null
    validationErrors.value = {}
  }

  return {
    user,
    tenant,
    isLoading,
    error,
    validationErrors,
    pendingVerification,
    isAuthenticated,
    isPlatformAdmin,
    login,
    register,
    verifyEmail,
    resendCode,
    logout,
    restoreSession,
    clear,
  }
})
