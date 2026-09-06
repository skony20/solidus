<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import GlassCard from '../../components/ui/GlassCard.vue'
import GlowButton from '../../components/ui/GlowButton.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const tenantSlug = ref('')
const email = ref('')
const password = ref('')

async function submit(): Promise<void> {
  const ok = await auth.login(tenantSlug.value, email.value, password.value)

  if (ok) {
    // Wracamy tam, gdzie uzytkownik chcial wejsc przed przekierowaniem.
    await router.push((route.query.redirect as string) ?? '/mission-control')
    return
  }

  // Konto istnieje, ale adres e-mail czeka na potwierdzenie - przenosimy na
  // ekran rejestracji, ktory zacznie od razu od pola na kod.
  if (auth.pendingVerification) {
    await router.push({ name: 'register' })
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center p-6">
    <GlassCard padding="lg" class="w-full max-w-[420px]">
      <div
        class="bg-linear-to-r from-cyan-bright to-cyan bg-clip-text text-[26px] font-extrabold tracking-[-0.02em] text-transparent"
      >
        SOLIDUS
      </div>
      <p class="mb-6 mt-1 text-[11px] font-bold uppercase tracking-[0.12em] text-content-variant">
        System Biura Rachunkowego
      </p>

      <form class="flex flex-col gap-3.5" @submit.prevent="submit">
        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-content-variant">Identyfikator biura</span>
          <input
            v-model="tenantSlug"
            required
            autocomplete="organization"
            placeholder="np. biuro-nowak"
            class="rounded-glass-sm border border-outline bg-surface-low px-3.5 py-2.5 text-sm text-content outline-hidden placeholder:text-content-variant focus:border-[rgba(0,219,231,0.5)]"
          />
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-content-variant">E-mail</span>
          <input
            v-model="email"
            type="email"
            required
            autocomplete="username"
            class="rounded-glass-sm border border-outline bg-surface-low px-3.5 py-2.5 text-sm text-content outline-hidden focus:border-[rgba(0,219,231,0.5)]"
          />
        </label>

        <label class="flex flex-col gap-1.5">
          <span class="text-xs font-semibold text-content-variant">Hasło</span>
          <input
            v-model="password"
            type="password"
            required
            autocomplete="current-password"
            class="rounded-glass-sm border border-outline bg-surface-low px-3.5 py-2.5 text-sm text-content outline-hidden focus:border-[rgba(0,219,231,0.5)]"
          />
        </label>

        <p
          v-if="auth.error"
          class="rounded-glass-sm border border-[rgba(255,75,137,0.35)] bg-[rgba(255,75,137,0.1)] px-3 py-2 text-xs text-magenta-bright"
        >
          {{ auth.error }}
        </p>

        <GlowButton type="submit" :disabled="auth.isLoading" class="mt-1.5 w-full">
          {{ auth.isLoading ? 'Logowanie...' : 'Zaloguj się' }}
        </GlowButton>

        <p class="text-center text-xs text-content-variant">
          Nie masz konta?
          <RouterLink to="/rejestracja" class="font-semibold text-cyan-bright no-underline">
            Zarejestruj biuro
          </RouterLink>
        </p>
      </form>
    </GlassCard>
  </div>
</template>
