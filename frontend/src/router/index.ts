import { createRouter, createWebHistory } from 'vue-router'
import { setSessionExpiredHandler } from '../api/http'
import { useAuthStore } from '../stores/auth'

/**
 * Trasy SPA odwzorowuja moduly backendu 1:1.
 *
 * Widoki sa ladowane leniwie (dynamiczny import), wiec pierwsze wejscie do
 * aplikacji nie sciaga kodu wszystkich dziesieciu modulow naraz.
 */
const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('../modules/auth/LoginView.vue'),
      meta: { public: true },
    },
    {
      /*
       * Rejestracja nowego biura. Publiczna - to druga (obok strony
       * informacyjnej) sciezka wejscia dla kogos bez konta. Krok z kodem
       * e-mail obsluguje ten sam widok.
       */
      path: '/rejestracja',
      name: 'register',
      component: () => import('../modules/auth/RegisterView.vue'),
      meta: { public: true },
    },
    {
      /*
       * Strona informacyjna. Publiczna i pod glownym adresem - to ona jest
       * pierwszym kontaktem z Solidusem dla kogos, kto jeszcze nie ma konta.
       * Zalogowany uzytkownik trafia do aplikacji z menu, nie przez przekierowanie:
       * cennik i tresc marketingowa maja byc dostepne takze dla klienta.
       */
      path: '/',
      name: 'landing',
      component: () => import('../modules/landing/LandingView.vue'),
      meta: { public: true },
    },
    {
      path: '/mission-control',
      name: 'mission-control',
      component: () => import('../modules/mission-control/MissionControlView.vue'),
    },
    {
      path: '/klienci',
      name: 'clients',
      component: () => import('../modules/client/ClientListView.vue'),
    },
    {
      path: '/klienci/:id(\d+)',
      name: 'client-detail',
      component: () => import('../modules/client/ClientDetailView.vue'),
    },
    { path: '/aml', name: 'aml', component: () => import('../modules/aml/AmlView.vue') },
    {
      path: '/delegacje',
      name: 'delegation',
      component: () => import('../modules/delegation/DelegationView.vue'),
    },
    {
      path: '/komunikacja',
      name: 'communication',
      component: () => import('../modules/communication/CommunicationView.vue'),
    },
    {
      path: '/kalendarz',
      name: 'calendar',
      component: () => import('../modules/calendar/CalendarView.vue'),
    },
    {
      path: '/finanse',
      name: 'finance',
      component: () => import('../modules/finance/FinanceView.vue'),
    },
    { path: '/zespol', name: 'team', component: () => import('../modules/team/TeamView.vue') },
    {
      path: '/sygnalisci',
      name: 'whistleblower',
      component: () => import('../modules/whistleblower/WhistleblowerView.vue'),
    },
    {
      path: '/ustawienia',
      name: 'settings',
      component: () => import('../modules/settings/SettingsView.vue'),
    },
    {
      /*
       * Panel administratora CALEGO systemu, nie pojedynczego biura.
       * `platformAdmin` w meta wlacza dodatkowy warunek w strazniku ponizej.
       */
      path: '/admin/cennik',
      name: 'admin-pricing',
      component: () => import('../modules/admin/PricingAdminView.vue'),
      meta: { platformAdmin: true },
    },
    {
      path: '/admin/biura',
      name: 'admin-tenants',
      component: () => import('../modules/admin/TenantsListView.vue'),
      meta: { platformAdmin: true },
    },
    {
      path: '/admin/biura/:id(\\d+)',
      name: 'admin-tenant-detail',
      component: () => import('../modules/admin/TenantDetailView.vue'),
      meta: { platformAdmin: true },
    },
  ],
})

/**
 * Straznik tras. Access token zyje 15 minut i jest trzymany tylko w pamieci,
 * wiec po odswiezeniu strony probujemy najpierw cicho odnowic sesje z
 * ciasteczka refresh - dopiero jego brak przekierowuje na logowanie.
 */
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.public) {
    return true
  }

  if (!auth.isAuthenticated) {
    const restored = await auth.restoreSession()
    if (!restored) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }
  }

  /*
   * Ukrycie trasy przed nie-administratorem jest wygoda, nie zabezpieczeniem -
   * prawdziwa granica stoi w API (PlatformAdminMiddleware). Ktos, kto wpisze
   * adres recznie, zobaczylby tu tylko puste bledy 403.
   */
  if (to.meta.platformAdmin === true && !auth.isPlatformAdmin) {
    return { name: 'mission-control' }
  }

  return true
})

/**
 * Gdy odnowienie sesji przez interceptor HTTP zawiedzie (refresh token wygasł
 * lub został unieważniony), czyścimy stan i wracamy na logowanie - inaczej
 * użytkownik zostałby na ekranie, który nie potrafi już pobrać danych.
 *
 * Rejestracja jest tutaj, a nie w store, żeby store nie musiał importować
 * routera - router już importuje store.
 */
setSessionExpiredHandler(() => {
  useAuthStore().clear()
  void router.push({ name: 'login' })
})

export default router
