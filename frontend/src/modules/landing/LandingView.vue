<script setup lang="ts">
import heroImage from '../../assets/landing-hero.jpg'
import AuditQuiz from './AuditQuiz.vue'
import PricingSection from './PricingSection.vue'

/**
 * Strona informacyjna Solidusa - jedyny widok dostepny bez logowania poza
 * ekranem logowania.
 *
 * Uklad odtworzony z makiety docs/design/landing.html. Tak jak przy makiecie
 * aplikacji: przepisany na komponenty i tokeny designu, a nie skopiowany -
 * kolory i promienie pochodza z tokens.css, wiec zmiana palety obejmuje takze
 * te strone.
 *
 * ROZNICA WOBEC MAKIETY: doszla sekcja cennika (kotwica #cennik), a jej tresc
 * pochodzi z bazy przez publiczne GET /api/pricing.
 */
const stats = [
  {
    eyebrow: 'Górna granica kary',
    value: 'do 4 mln zł',
    valueClass: 'text-magenta-bright',
    accent: true,
    note: 'Równowartość 1 mln EUR, gdy nie da się ustalić korzyści lub straty — plus osobna kara do 1 mln zł dla osoby odpowiedzialnej w biurze.',
  },
  {
    eyebrow: 'Kogo dotyczy',
    value: '100% biur',
    valueClass: 'text-cyan-bright',
    accent: false,
    note: 'Art. 50 ustawy AML obejmuje każde biuro prowadzące księgi — także jednoosobowe działalności.',
  },
  {
    eyebrow: 'Ile to dziś zajmuje ręcznie',
    value: 'kilkanaście godz./mies.',
    valueClass: 'text-emerald-bright',
    accent: false,
    note: 'Ocena ryzyka, szkolenia, dokumentacja i archiwizacja — rozrzucone po segregatorach, plikach i głowie kierownika.',
  },
]

const today = [
  'Ocena ryzyka klienta w Excelu, aktualizowana „jak będzie czas"',
  'Szkolenia AML pilnowane w głowie kierownika zespołu',
  'Dokumentacja rozrzucona po segregatorach i osobnych plikach',
  'Nikt nie spisał, kto dokładnie obsługuje którego klienta',
]

const withSolidus = [
  'Ocena ryzyka liczona automatycznie, z przypomnieniem o terminie aktualizacji',
  'Panel certyfikatów z alertem, zanim szkolenie wygaśnie',
  'Wszystko w jednym miejscu, gotowe do pokazania w 24 godziny',
  'Jasne przypisania klientów i uprawnienia dla każdego pracownika',
]

const teasers = [
  {
    icon: 'grid_view',
    title: 'Stan Biura',
    text: 'KSeF, terminy podatkowe i ryzyko AML w jednym miejscu — bez przełączania się między pięcioma zakładkami.',
    iconClass: 'bg-[rgba(0,219,231,0.1)] border-[rgba(0,219,231,0.3)] text-cyan-bright',
  },
  {
    icon: 'shield',
    title: 'Ryzyko AML',
    text: 'Scoring klienta, harmonogram przeglądów, rejestr zgłoszeń sygnalistów i szkolenia zespołu — zawsze aktualne.',
    iconClass: 'bg-[rgba(255,75,137,0.1)] border-[rgba(255,75,137,0.3)] text-magenta-bright',
  },
  {
    icon: 'groups',
    title: 'Zespół',
    text: 'Kto obsługuje którego klienta, kto może podpisywać dokumenty, czyj certyfikat wygasa — jedno miejsce, nie zgadywanie.',
    iconClass: 'bg-[rgba(0,226,139,0.1)] border-[rgba(0,226,139,0.3)] text-emerald-bright',
  },
]

function scrollTo(id: string): void {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' })
}
</script>

<template>
  <div class="min-h-screen">
    <!-- ===== NAWIGACJA ===== -->
    <nav
      class="sticky top-0 z-30 border-b border-white/[0.06] bg-[rgba(5,11,24,0.65)] backdrop-blur-[18px]"
    >
      <div class="mx-auto flex max-w-[1180px] items-center justify-between px-8 py-4">
        <div
          class="bg-linear-to-r from-cyan-bright to-cyan bg-clip-text text-xl font-extrabold tracking-[-0.02em] text-transparent"
        >
          SOLIDUS
        </div>
        <div class="flex items-center gap-7">
          <a
            href="#dlaczego"
            class="text-sm font-semibold text-content-variant no-underline hover:text-content max-[720px]:hidden"
          >
            Dlaczego Solidus
          </a>
          <a
            href="#cennik"
            class="text-sm font-semibold text-content-variant no-underline hover:text-content max-[720px]:hidden"
          >
            Cennik
          </a>
          <a
            href="#audyt"
            class="text-sm font-semibold text-content-variant no-underline hover:text-content max-[720px]:hidden"
          >
            Audyt AML
          </a>
          <RouterLink
            to="/login"
            class="text-sm font-semibold text-content-variant no-underline hover:text-content"
          >
            Zaloguj się
          </RouterLink>
          <button
            type="button"
            class="rounded-nav bg-linear-to-br from-cyan-bright to-cyan px-5 py-2.5 text-[13px] font-bold text-[#00363a] transition-all duration-150 hover:shadow-glow"
            @click="scrollTo('audyt')"
          >
            Wczesny dostęp
          </button>
        </div>
      </div>
    </nav>

    <!-- ===== HERO ===== -->
    <header class="relative flex min-h-[640px] items-center overflow-hidden">
      <img
        :src="heroImage"
        alt=""
        aria-hidden="true"
        class="absolute inset-0 h-full w-full object-cover object-[62%_26%] brightness-[0.6] grayscale-[0.1] saturate-[1.05]"
      />
      <div
        class="absolute inset-0 bg-[linear-gradient(100deg,rgba(5,11,24,0.97)_0%,rgba(5,11,24,0.86)_38%,rgba(5,11,24,0.35)_68%,rgba(0,219,231,0.08)_100%)]"
      />
      <div class="relative mx-auto w-full max-w-[1180px] px-8 pb-[90px] pt-[100px]">
        <div class="max-w-[640px]">
          <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-content-variant">
            Dla biur rachunkowych
          </div>
          <h1
            class="mb-[22px] mt-[18px] text-[clamp(34px,5vw,54px)] font-extrabold leading-[1.08] tracking-[-0.02em]"
          >
            Kup sobie spokoju od AML.
          </h1>
          <p class="mb-8 max-w-[520px] text-[17px] leading-relaxed text-content-variant">
            Solidus prowadzi ocenę ryzyka, szkolenia i dokumentację AML za Ciebie — żebyś nie
            szukał/a odpowiedzi w segregatorze, kiedy zadzwoni GIIF.
          </p>
          <div class="flex flex-wrap items-center gap-[18px]">
            <button
              type="button"
              class="rounded-nav bg-linear-to-br from-cyan-bright to-cyan px-7 py-[15px] text-[15px] font-bold text-[#00363a] transition-all duration-150 hover:-translate-y-px hover:shadow-glow"
              @click="scrollTo('audyt')"
            >
              Sprawdź swoje ryzyko — 2 minuty
            </button>
            <a href="#cennik" class="text-sm font-semibold text-cyan-bright no-underline">
              Zobacz cennik
            </a>
          </div>
          <div class="mt-9 flex flex-wrap gap-2.5">
            <span
              v-for="trust in [
                'Zgodne z ustawą AML',
                'Kara do ok. 4 mln zł za brak procedur',
                'Obowiązkowe dla każdego biura',
              ]"
              :key="trust"
              class="rounded-full border border-outline bg-surface-highest px-3.5 py-1 text-[11.5px] font-bold tracking-[0.03em] text-content-variant"
            >
              {{ trust }}
            </span>
          </div>
        </div>
      </div>
    </header>

    <!-- ===== SKALA PROBLEMU ===== -->
    <section id="dlaczego" class="py-[74px]">
      <div class="mx-auto max-w-[1180px] px-8">
        <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-content-variant">
          Skala problemu
        </div>
        <h2 class="mb-3.5 mt-1.5 text-[clamp(24px,3vw,32px)] font-extrabold tracking-[-0.01em]">
          To nie jest teoretyczne ryzyko
        </h2>
        <p class="mb-10 max-w-[560px] text-[15.5px] leading-relaxed text-content-variant">
          Ustawa AML dotyczy każdego biura rachunkowego — niezależnie od tego, czy prowadzisz je
          sam/a, czy zatrudniasz zespół.
        </p>

        <div class="grid gap-5 md:grid-cols-3">
          <div
            v-for="stat in stats"
            :key="stat.eyebrow"
            class="glass p-[26px]"
            :class="stat.accent && 'border-[rgba(255,75,137,0.3)]'"
          >
            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-content-variant">
              {{ stat.eyebrow }}
            </div>
            <div class="mt-2 text-[32px] font-extrabold" :class="stat.valueClass">
              {{ stat.value }}
            </div>
            <div class="mt-1.5 text-[12.5px] leading-relaxed text-content-variant">
              {{ stat.note }}
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== DZIS KONTRA Z SOLIDUSEM ===== -->
    <section class="py-[74px]">
      <div class="mx-auto max-w-[1180px] px-8">
        <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-content-variant">
          Zmiana
        </div>
        <h2 class="mb-3.5 mt-1.5 text-[clamp(24px,3vw,32px)] font-extrabold tracking-[-0.01em]">
          Jak to wygląda dziś — i jak może wyglądać
        </h2>

        <div class="grid overflow-hidden rounded-glass border border-white/[0.08] lg:grid-cols-2">
          <div
            class="border-b border-white/[0.07] bg-[rgba(255,75,137,0.04)] px-9 py-[34px] lg:border-b-0 lg:border-r"
          >
            <div class="mb-5 flex items-center gap-2.5 text-[15px] font-bold">
              <span class="material-symbols-outlined text-magenta-bright">folder_off</span>
              Dziś
            </div>
            <div
              v-for="row in today"
              :key="row"
              class="border-t border-white/[0.06] py-3.5 text-sm leading-relaxed text-content-variant first-of-type:border-t-0"
            >
              {{ row }}
            </div>
          </div>
          <div class="bg-[rgba(0,219,231,0.045)] px-9 py-[34px]">
            <div class="mb-5 flex items-center gap-2.5 text-[15px] font-bold">
              <span class="material-symbols-outlined text-cyan-bright">verified_user</span>
              Z Solidusem
            </div>
            <div
              v-for="row in withSolidus"
              :key="row"
              class="border-t border-white/[0.06] py-3.5 text-sm leading-relaxed text-content first-of-type:border-t-0"
            >
              {{ row }}
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== ZAJRZYJ DO SRODKA ===== -->
    <section class="py-[74px]">
      <div class="mx-auto max-w-[720px] px-8">
        <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-content-variant">
          Zerknij do środka
        </div>
        <h2 class="mb-3.5 mt-1.5 text-[clamp(24px,3vw,32px)] font-extrabold tracking-[-0.01em]">
          Budujemy narzędzie, którego sami byśmy chcieli używać
        </h2>

        <div class="glass px-[34px] py-2">
          <div
            v-for="teaser in teasers"
            :key="teaser.title"
            class="flex items-start gap-4 border-t border-white/[0.07] py-5 first-of-type:border-t-0"
          >
            <div
              class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-xl border"
              :class="teaser.iconClass"
            >
              <span class="material-symbols-outlined">{{ teaser.icon }}</span>
            </div>
            <div>
              <h4 class="mb-1 text-[15.5px] font-bold">{{ teaser.title }}</h4>
              <p class="text-[13.5px] leading-relaxed text-content-variant">{{ teaser.text }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== CENNIK (z bazy) ===== -->
<!--    <PricingSection />-->

    <!-- ===== AUDYT AML ===== -->
    <section id="audyt" class="py-[74px]">
      <div class="mx-auto max-w-[1180px] px-8">
        <div class="mx-auto mb-10 max-w-[560px] text-center">
          <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-content-variant">
            Audyt Gotowości AML
          </div>
          <h2 class="my-1.5 text-[clamp(24px,3vw,32px)] font-extrabold tracking-[-0.01em]">
            Sprawdź, czy Twoje biuro przetrwa kontrolę GIIF
          </h2>
          <p class="text-[15.5px] leading-relaxed text-content-variant">
            12 pytań. Szczery wynik. Zero zobowiązań.
          </p>
        </div>

        <AuditQuiz />
      </div>
    </section>

    <!-- ===== STOPKA ===== -->
    <footer class="border-t border-white/[0.07] py-9">
      <div class="mx-auto flex max-w-[1180px] flex-wrap items-start justify-between gap-6 px-8">
        <div
          class="bg-linear-to-r from-cyan-bright to-cyan bg-clip-text text-base font-extrabold tracking-[-0.02em] text-transparent"
        >
          SOLIDUS
        </div>
        <p class="max-w-[640px] text-[11.5px] leading-relaxed text-content-variant">
          Ustawa o przeciwdziałaniu praniu pieniędzy przewiduje karę do dwukrotności korzyści
          osiągniętej lub straty unikniętej, a gdy nie da się jej ustalić — do równowartości
          1 000 000 EUR (ok. 4 mln zł) dla instytucji obowiązanej. Osoba odpowiedzialna w biurze
          może otrzymać dodatkową karę do 1 000 000 zł. Powyższe informacje mają charakter
          poglądowy i nie stanowią porady prawnej.
        </p>
      </div>
    </footer>
  </div>
</template>
