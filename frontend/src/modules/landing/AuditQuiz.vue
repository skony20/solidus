<script setup lang="ts">
import { computed, ref } from 'vue'
import { AUDIT_QUESTIONS, MAX_AUDIT_SCORE } from './questions'

/**
 * Audyt Gotowosci AML - lead magnet ze strony informacyjnej.
 *
 * Trzy kroki: pytania -> prosba o e-mail -> wynik. Odtworzone z makiety
 * (docs/design/landing.html), ale jako stan Vue zamiast przepisywania
 * innerHTML - dzieki temu cofniecie sie do pytan nie gubi odpowiedzi.
 *
 * WYNIK LICZY SIE W PRZEGLADARCE i nie jest nigdzie wysylany. E-mail tez
 * jeszcze nigdzie nie leci - modul Komunikacja nie istnieje, a wysylka
 * czeka na decyzje o kolejce zadan (patrz docs/ARCHITECTURE.md, sekcja 2.9).
 * Do tego czasu formularz jest szczery: mowi, ze zapisuje zgloszenie, a nie
 * ze wysyla raport.
 */
type Step = 'questions' | 'gate' | 'result'

const step = ref<Step>('questions')
const answers = ref<Record<number, boolean>>({})
const email = ref('')
const emailError = ref('')

const answeredCount = computed(() => Object.keys(answers.value).length)
const isComplete = computed(() => answeredCount.value === AUDIT_QUESTIONS.length)
const progress = computed(() => (answeredCount.value / AUDIT_QUESTIONS.length) * 100)

const score = computed(() =>
  AUDIT_QUESTIONS.reduce((sum, q) => (answers.value[q.id] ? sum + q.weight : sum), 0),
)
const percent = computed(() => Math.round((score.value / MAX_AUDIT_SCORE) * 100))

/** Trzy najciezsze braki - tyle, ile czlowiek jest w stanie przyjac naraz. */
const weakPoints = computed(() =>
  AUDIT_QUESTIONS.filter((q) => !answers.value[q.id])
    .sort((a, b) => b.weight - a.weight)
    .slice(0, 3),
)

const tier = computed(() => {
  if (percent.value >= 85) {
    return {
      label: 'Gotowi na kontrolę',
      color: 'var(--color-emerald-bright)',
      message:
        'Twoje biuro jest dobrze przygotowane. Zostały drobne luki — zobacz, co jeszcze warto dopiąć.',
    }
  }

  if (percent.value >= 60) {
    return {
      label: 'Częściowo przygotowani',
      color: 'var(--color-amber)',
      message: 'Masz solidne podstawy, ale kilka realnych luk może kosztować przy kontroli GIIF.',
    }
  }

  return {
    label: 'Wysokie ryzyko kary',
    color: 'var(--color-magenta-bright)',
    message:
      'W obecnym stanie biuro jest narażone na realne ryzyko kary administracyjnej (ustawowo do ok. 4 mln zł). To się da naprawić.',
  }
})

// Pierscien postepu: obwod kola o promieniu 70 px, przesuniecie proporcjonalne
// do wyniku. Te dwie liczby musza pochodzic z jednego miejsca, inaczej
// pierscien rozjedzie sie z liczba w srodku.
const RING_CIRCUMFERENCE = 2 * Math.PI * 70
const ringOffset = computed(() => RING_CIRCUMFERENCE * (1 - percent.value / 100))

function answer(id: number, value: boolean): void {
  answers.value = { ...answers.value, [id]: value }
}

function submitGate(): void {
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
    emailError.value = 'Podaj poprawny adres e-mail.'
    return
  }

  emailError.value = ''
  step.value = 'result'
}

function restart(): void {
  answers.value = {}
  email.value = ''
  emailError.value = ''
  step.value = 'questions'
}
</script>

<template>
  <div class="glass mx-auto max-w-[760px] p-11 max-[820px]:px-[22px] max-[820px]:py-7">
    <!-- KROK 1: pytania -->
    <template v-if="step === 'questions'">
      <div class="mb-8 h-1.5 overflow-hidden rounded-full bg-surface-highest">
        <div
          class="h-full rounded-full bg-linear-to-r from-cyan to-cyan-bright transition-[width] duration-[250ms]"
          :style="{ width: `${progress}%` }"
        />
      </div>

      <div
        v-for="question in AUDIT_QUESTIONS"
        :key="question.id"
        class="border-t border-white/[0.07] py-5 first:border-t-0 first:pt-0"
      >
        <div class="mb-3.5 text-[15px] leading-relaxed">{{ question.text }}</div>
        <div class="flex gap-2.5">
          <button
            type="button"
            class="flex-1 rounded-xl border-[1.5px] p-2.5 text-[13.5px] font-bold transition-all duration-150"
            :class="
              answers[question.id] === true
                ? 'border-[rgba(0,226,139,0.5)] bg-[rgba(0,226,139,0.12)] text-emerald-bright'
                : 'border-outline text-content-variant hover:text-content'
            "
            @click="answer(question.id, true)"
          >
            Tak
          </button>
          <button
            type="button"
            class="flex-1 rounded-xl border-[1.5px] p-2.5 text-[13.5px] font-bold transition-all duration-150"
            :class="
              answers[question.id] === false
                ? 'border-[rgba(255,75,137,0.5)] bg-[rgba(255,75,137,0.12)] text-magenta-bright'
                : 'border-outline text-content-variant hover:text-content'
            "
            @click="answer(question.id, false)"
          >
            Nie
          </button>
        </div>
      </div>

      <div class="mt-8 flex flex-wrap items-center justify-between gap-4">
        <span class="text-[13px] text-content-variant">
          {{ answeredCount }} / {{ AUDIT_QUESTIONS.length }} odpowiedzi
        </span>
        <button
          type="button"
          class="rounded-nav bg-linear-to-br from-cyan-bright to-cyan px-[26px] py-3.5 text-sm font-bold text-[#00363a] transition-all duration-150 hover:shadow-glow disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:shadow-none"
          :disabled="!isComplete"
          @click="step = 'gate'"
        >
          Zobacz wynik
        </button>
      </div>
    </template>

    <!-- KROK 2: e-mail -->
    <form v-else-if="step === 'gate'" class="py-5 text-center" @submit.prevent="submitGate">
      <span class="material-symbols-outlined text-[38px]! text-cyan-bright">mail</span>
      <h3 class="mb-2 mt-4 text-[22px] font-extrabold">Prawie gotowe</h3>
      <p class="mx-auto mb-6 max-w-[420px] text-sm text-content-variant">
        Podaj e-mail, żebyśmy mogli wysłać pełny raport z rekomendacjami, gdy tylko będzie gotowy.
        Wynik zobaczysz od razu, bez czekania.
      </p>
      <div class="mx-auto flex max-w-[420px] flex-wrap justify-center gap-2.5">
        <input
          v-model="email"
          type="email"
          placeholder="twoj@email.pl"
          class="hairline min-w-[220px] flex-1 rounded-full bg-surface-low px-[18px] py-3.5 text-sm text-content outline-none focus:border-[rgba(0,219,231,0.5)]"
        />
        <button
          type="submit"
          class="rounded-nav bg-linear-to-br from-cyan-bright to-cyan px-6 py-3.5 text-sm font-bold text-[#00363a] transition-all duration-150 hover:shadow-glow"
        >
          Pokaż mój wynik
        </button>
      </div>
      <div class="mt-2.5 min-h-4 text-[12.5px] text-magenta-bright">{{ emailError }}</div>
      <button
        type="button"
        class="mt-2 cursor-pointer border-0 bg-transparent text-[12.5px] text-content-variant underline"
        @click="step = 'questions'"
      >
        Wróć do pytań
      </button>
    </form>

    <!-- KROK 3: wynik -->
    <div v-else class="text-center">
      <svg width="170" height="170" viewBox="0 0 170 170" class="mx-auto">
        <circle cx="85" cy="85" r="70" fill="none" stroke="var(--color-surface-highest)" stroke-width="14" />
        <circle
          cx="85"
          cy="85"
          r="70"
          fill="none"
          :stroke="tier.color"
          stroke-width="14"
          stroke-linecap="round"
          :stroke-dasharray="RING_CIRCUMFERENCE"
          :stroke-dashoffset="ringOffset"
          transform="rotate(-90 85 85)"
        />
        <text
          x="85"
          y="80"
          text-anchor="middle"
          font-size="34"
          font-weight="800"
          fill="var(--color-content)"
          font-family="Inter"
        >
          {{ percent }}%
        </text>
        <text
          x="85"
          y="102"
          text-anchor="middle"
          font-size="10"
          fill="var(--color-content-variant)"
          font-family="Inter"
        >
          GOTOWOŚĆ
        </text>
      </svg>

      <div class="mt-3.5 text-sm font-bold tracking-[0.02em]" :style="{ color: tier.color }">
        {{ tier.label }}
      </div>
      <p class="mx-auto mt-2.5 max-w-[440px] text-sm text-content-variant">{{ tier.message }}</p>

      <div v-if="weakPoints.length" class="mx-auto mt-7 flex max-w-[520px] flex-col gap-2.5 text-left">
        <div
          v-for="question in weakPoints"
          :key="question.id"
          class="hairline flex items-start gap-3 rounded-2xl px-4 py-3.5"
        >
          <span class="material-symbols-outlined shrink-0 text-[18px]! text-magenta-bright">
            priority_high
          </span>
          <span class="text-[13px] text-content-variant">{{ question.risk }}</span>
        </div>
      </div>

      <div class="mt-8 flex flex-wrap justify-center gap-3">
        <a
          href="#cennik"
          class="rounded-nav px-[26px] py-3.5 text-sm font-bold no-underline transition-all duration-150 hover:-translate-y-px"
          :class="
            percent < 60
              ? 'bg-linear-to-br from-magenta-bright to-magenta text-[#3f0019] hover:shadow-glow-magenta'
              : 'bg-linear-to-br from-cyan-bright to-cyan text-[#00363a] hover:shadow-glow'
          "
        >
          {{ percent < 60 ? 'Zobacz, ile kosztuje naprawa' : 'Zobacz cennik' }}
        </a>
        <RouterLink
          to="/login"
          class="hairline rounded-nav border-outline px-[22px] py-3.5 text-sm font-bold text-content no-underline transition-all duration-150 hover:border-[rgba(0,219,231,0.4)] hover:text-cyan-bright"
        >
          Mam już konto
        </RouterLink>
      </div>

      <button
        type="button"
        class="mx-auto mt-6 block cursor-pointer border-0 bg-transparent text-[12.5px] text-content-variant underline"
        @click="restart"
      >
        Zacznij od nowa
      </button>
    </div>
  </div>
</template>
