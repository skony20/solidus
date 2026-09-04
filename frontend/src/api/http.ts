import axios, { AxiosError, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios'

/**
 * Jedyny klient HTTP w aplikacji.
 *
 * Odpowiada za dwie rzeczy, ktorych nie chcemy powtarzac w kazdym module:
 *  1. doklejenie access tokenu do naglowka Authorization,
 *  2. ciche odnowienie sesji, gdy token wygasl (API odpowie 401).
 *
 * Tenant NIE jest doklejany recznie - siedzi w claimie `tid` wewnatrz tokenu,
 * a backend odczytuje go w TenantMiddleware. Przegladarka nie moze go podmienic.
 */
const baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8080'

export const http: AxiosInstance = axios.create({
  baseURL: `${baseURL}/api`,
  // Konieczne, zeby ciasteczko z refresh tokenem dotarlo do /api/auth/refresh.
  withCredentials: true,
  headers: { 'Content-Type': 'application/json' },
})

/** Access token zyje wylacznie w pamieci - w localStorage bylby lupem dla XSS. */
let accessToken: string | null = null

export function setAccessToken(token: string | null): void {
  accessToken = token
}

export function getAccessToken(): string | null {
  return accessToken
}

/** Wywolywane, gdy odnowienie sesji sie nie powiodlo - ustawia je store auth. */
let onSessionExpired: (() => void) | null = null

export function setSessionExpiredHandler(handler: () => void): void {
  onSessionExpired = handler
}

http.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  if (accessToken !== null) {
    config.headers.Authorization = `Bearer ${accessToken}`
  }

  return config
})

/**
 * Gdy kilka zapytan dostanie 401 naraz, odnawiamy sesje raz i pozostale
 * czekaja na ten sam wynik - inaczej rotacja refresh tokenu uniewaznilaby
 * sama siebie w wyscigu.
 */
let refreshPromise: Promise<string | null> | null = null

async function refreshAccessToken(): Promise<string | null> {
  refreshPromise ??= axios
    .post<{ accessToken: string }>(`${baseURL}/api/auth/refresh`, {}, { withCredentials: true })
    .then((response) => {
      accessToken = response.data.accessToken
      return accessToken
    })
    .catch(() => null)
    .finally(() => {
      refreshPromise = null
    })

  return refreshPromise
}

http.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const request = error.config as (InternalAxiosRequestConfig & { _retried?: boolean }) | undefined

    const isAuthEndpoint = request?.url?.includes('/auth/')
    if (error.response?.status !== 401 || request === undefined || request._retried || isAuthEndpoint) {
      return Promise.reject(error)
    }

    request._retried = true
    const token = await refreshAccessToken()

    if (token === null) {
      onSessionExpired?.()
      return Promise.reject(error)
    }

    return http(request)
  },
)

/**
 * Wyciaga czytelny komunikat z odpowiedzi API, ktora ma ksztalt
 * {error: {message, details}} (patrz Shared/Http/JsonResponse.php).
 */
export function apiErrorMessage(error: unknown, fallback = 'Coś poszło nie tak.'): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { error?: { message?: string } } | undefined
    return data?.error?.message ?? fallback
  }

  return fallback
}

/**
 * Bledy walidacji per pole, do podswietlenia formularza.
 */
export function apiValidationErrors(error: unknown): Record<string, string[]> {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { error?: { details?: Record<string, string[]> } } | undefined
    return data?.error?.details ?? {}
  }

  return {}
}
