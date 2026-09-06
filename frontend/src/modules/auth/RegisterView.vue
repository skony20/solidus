<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import GlassCard from '../../components/ui/GlassCard.vue'
import GlowButton from '../../components/ui/GlowButton.vue'

/**
 * Rejestracja nowego biura w dwoch krokach:
 *  1. formularz (nazwa biura, osoba, e-mail, haslo),
 *  2. kod z wiadomosci e-mail - potwierdza adres i od razu loguje.
 *
 * Do kroku 2 mozna trafic takze z ekranu logowania: gdy ktos probuje sie
 * zalogowac na niepotwierdzone konto, store zapisuje `pendingVerification`,
 * a ten widok zaczyna od razu od pola na kod.
 */
const auth = useAuthStore()
const router = useRouter()

const RESEND_SECONDS = 60

const step = ref<'form' | 'code'>('form')

const tenantName = ref('')
const name = ref('')
const email = ref('')
const password = ref('')

const tenantSlug = ref('')
const code = ref('')
const notice = ref<string | null>(null)

const cooldown = ref(0)
let cooldownTimer: ReturnType<typeof setInterval> | undefined

function fieldError(field: string): string | undefined {
  return auth.validationErrors[field]?.[0]
}

function startCooldown(): void {
  cooldown.value = RESEND_SECONDS
  window.clearInterval(cooldownTimer)
  cooldownTimer = setInterval(() => {
    cooldown.value -= 1
    if (cooldown.value <= 0) {
      window.clearInterval(cooldownTimer)
    }
  }, 1000)
}

onMounted(() => {
  if (auth.pendingVerification) {
    tenantSlug.value = auth.pendingVerification.tenantSlug
    email.value = auth.pendingVerification.email
    step.value = 'code'
    notice.value = 'To konto czeka na potwierdzenie adresu. Wpisz kod z wiadomości e-mail.'
    startCooldown()
  }
})

onBeforeUnmount(() => window.clearInterval(cooldownTimer))

async function submitForm(): Promise<void> {
  const result = await auth.register(tenantName.value, name.value, email.value, password.value)

  if (!result.ok) {
    return
  }

  tenantSlug.value = result.tenantSlug ?? ''
  step.value = 'code'
  notice.value = result.emailSent
    ? `Wysłaliśmy 6-cyfrowy kod na adres ${email.value}.`
    : 'Konto powstało, ale nie udało się wysłać wiadomości. Kliknij „Wyślij kod ponownie”.'
  startCooldown()
}

async function submitCode(): Promise<void> {
  const ok = await auth.verifyEmail(tenantSlug.value, email.value, code.value)

  if (ok) {
    await router.push('/mission-control')
  }
}

async function resend(): Promise<void> {
  if (cooldown.value > 0) {
    return
  }

  const ok = await auth.resendCode(tenantSlug.value, email.value)
  if (ok) {
    notice.value = `Nowy kod poleciał na ${email.value}.`
    code.value = ''
    startCooldown()
  }
}

const resendLabel = computed(() =>
  cooldown.value > 0 ? `Wyślij kod ponownie (${cooldown.value} s)` : 'Wyślij kod ponownie',
)
</script>

<template>
  <div class="flex min-h-screen items-center justify-center p-6">
    <GlassCard padding="lg" class="w-full max-w-[440px]">
      <div
        class="bg-linear-to-r from-cyan-bright to-cyan bg-clip-text text-[26px] font-extrabold tracking-[-0.02em] text-transparent"
      >
        SOLIDUS
      </div>
      <p class="mb-6 mt-1 text-[11px] font-bold uppercase tracking-[0.12em] text-content-variant">
        {{ step === 'form' ? 'Załóż konto biura' : 'Potwierdź adres e-mail' }}
      </p>

      <!-- KROK 1: dane biura -->
      <form v-if="step === 'form'" class="flex flex-col gap-3.5" @submit.prevent="submitForm">
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-content-variant">Nazwa biura</span>
          <input
            v-model="tenantName"
            required
            autocomplete="organization"
            placeholder="np. Biuro Rachunkowe Nowak"
            class="rounded-glass-sm border border-outline bg-surface-low px-3.5 py-2.5 text-sm text-content outline-hidden placeholder:text-content-variant focus:border-[rgba(0,219,231,0.5)]"
          />
          <span v-if="fieldError('tenantName')" class="text-xs text-magenta-bright">
            {{ fieldError('tenantName') }}
          </span>
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-content-variant">Imię i nazwisko</span>
          <input
            v-model="name"
            required
            autocomplete="name"
            class="rounded-glass-sm border border-outline bg-surface-low px-3.5 py-2.5 text-sm text-content outline-hidden focus:border-[rgba(0,219,231,0.5)]"
          />
          <span v-if="fieldError('name')" class="text-xs text-magenta-bright">
            {{ fieldError('name') }}
          </span>
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-content-variant">E-mail</span>
          <input
            v-model="email"
            type="email"
            required
            autocomplete="email"
            class="rounded-glass-sm border border-outline bg-surface-low px-3.5 py-2.5 text-sm text-content outline-hidden focus:border-[rgba(0,219,231,0.5)]"
          />
          <span v-if="fieldError('email')" class="text-xs text-magenta-bright">
            {{ fieldError('email') }}
          </span>
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-content-variant">Hasło (min. 10 znaków)</span>
          <input
            v-model="password"
            type="password"
            required
            minlength="10"
            autocomplete="new-password"
            class="rounded-glass-sm border border-outline bg-surface-low px-3.5 py-2.5 text-sm text-content outline-hidden focus:border-[rgba(0,219,231,0.5)]"
          />
          <span v-if="fieldError('password')" class="text-xs text-magenta-bright">
            {{ fieldError('password') }}
          </span>
        </label>

        <p
          v-if="auth.error"
          class="rounded-glass-sm border border-[rgba(255,75,137,0.35)] bg-[rgba(255,75,137,0.1)] px-3 py-2 text-xs text-magenta-bright"
        >
          {{ auth.error }}
        </p>

        <GlowButton type="submit" :disabled="auth.isLoading" class="mt-1.5 w-full">
          {{ auth.isLoading ? 'Zakładanie...' : 'Załóż konto' }}
        </GlowButton>

        <p class="text-center text-xs text-content-variant">
          Masz już konto?
          <RouterLink to="/login" class="font-semibold text-cyan-bright no-underline">
            Zaloguj się
          </RouterLink>
        </p>
      </form>

      <!-- KROK 2: kod z maila -->
      <form v-else class="flex flex-col gap-3.5" @submit.prevent="submitCode">
        <p v-if="notice" class="text-[13px] leading-relaxed text-content-variant">
          {{ notice }}
        </p>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-content-variant">Kod z wiadomości</span>
          <input
            v-model="code"
            required
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="7"
            placeholder="123456"
            class="rounded-glass-sm border border-outline bg-surface-low px-3.5 py-2.5 text-center text-lg tracking-[0.4em] text-content outline-hidden placeholder:tracking-normal placeholder:text-content-variant focus:border-[rgba(0,219,231,0.5)]"
          />
        </label>

        <p
          v-if="auth.error"
          class="rounded-glass-sm border border-[rgba(255,75,137,0.35)] bg-[rgba(255,75,137,0.1)] px-3 py-2 text-xs text-magenta-bright"
        >
          {{ auth.error }}
        </p>

        <GlowButton type="submit" :disabled="auth.isLoading" class="mt-1.5 w-full">
          {{ auth.isLoading ? 'Sprawdzanie...' : 'Potwierdź i zaloguj' }}
        </GlowButton>

        <button
          type="button"
          :disabled="cooldown > 0"
          class="text-xs font-semibold text-cyan-bright no-underline disabled:cursor-not-allowed disabled:text-content-variant"
          @click="resend"
        >
          {{ resendLabel }}
        </button>
      </form>
    </GlassCard>
  </div>
</template>
