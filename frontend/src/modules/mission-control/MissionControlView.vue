<script setup lang="ts">
import { onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useClientsStore } from '../../stores/clients'
import GlassCard from '../../components/ui/GlassCard.vue'
import SectionTitle from '../../components/ui/SectionTitle.vue'
import StatCard from '../../components/ui/StatCard.vue'
import Badge from '../../components/ui/Badge.vue'

/**
 * Centrum Dowodzenia.
 *
 * Liczba klientow jest prawdziwa - pochodzi z API. Pozostale wskazniki sa
 * zaslepkami z makiety i beda podpiete, gdy powstana moduly Finanse i AML.
 */
const clients = useClientsStore()
const { total } = storeToRefs(clients)

onMounted(() => clients.fetchAll())

const teamPerformance = [
  { name: 'Anna Kowalska', value: 94, tone: 'from-emerald to-cyan', text: 'text-emerald-bright' },
  { name: 'Jan Nowak', value: 61, tone: 'from-magenta to-amber', text: 'text-magenta-bright' },
  { name: 'Alex Wójcik', value: 88, tone: 'from-cyan to-cyan-bright', text: 'text-cyan-bright' },
]
</script>

<template>
  <div class="animate-fade-in">
    <SectionTitle eyebrow="Przegląd Ogólny" title="Centrum Dowodzenia" />

    <GlassCard accent="emerald" padding="sm" class="mb-5 flex flex-wrap items-center gap-3.5">
      <div
        class="relative flex h-11 w-11 items-center justify-center rounded-xl border border-[rgba(0,226,139,0.35)] bg-[rgba(0,226,139,0.12)]"
      >
        <span class="material-symbols-outlined text-emerald-bright">dns</span>
        <span
          class="absolute -right-[3px] -top-[3px] h-[11px] w-[11px] rounded-full border-2 border-surface bg-emerald-bright"
        />
      </div>
      <div>
        <div class="flex items-center gap-2 text-sm font-bold">
          Połączenie z KSeF
          <Badge variant="low">DO WPIĘCIA</Badge>
        </div>
        <div class="mt-0.5 text-xs text-content-variant">
          Integracja z KSeF jest zaplanowana na kolejny sprint - tu pojawi się stan synchronizacji.
        </div>
      </div>
    </GlassCard>

    <div class="grid gap-5 md:grid-cols-3">
      <StatCard label="Obrót w tym miesiącu" value="—" tone="cyan" trend="Czeka na moduł Finanse" icon="hourglass_empty" />
      <StatCard label="Aktywni klienci" :value="String(total)" trend="Dane z modułu Klienci" />
      <StatCard
        label="Anomalie AML"
        value="—"
        tone="magenta"
        trend="Czeka na aplikację AML"
        trend-tone="negative"
      />
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-[1.7fr_1fr]">
      <GlassCard padding="lg">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="m-0 text-[19px] font-bold">Wydajność zespołu</h3>
          <RouterLink to="/zespol" class="text-[13px] text-cyan-bright">Zobacz zespół →</RouterLink>
        </div>
        <div class="flex flex-col gap-4">
          <div v-for="member in teamPerformance" :key="member.name">
            <div class="mb-1.5 flex justify-between text-sm font-semibold">
              <span>{{ member.name }}</span>
              <span :class="member.text">{{ member.value }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-surface-highest">
              <div
                class="h-full rounded-full bg-linear-to-r"
                :class="member.tone"
                :style="{ width: `${member.value}%` }"
              />
            </div>
          </div>
        </div>
        <p class="mt-4 text-xs text-content-variant">
          Dane przykładowe z makiety - podepniemy je przy module Zespół.
        </p>
      </GlassCard>

      <GlassCard accent="magenta" padding="lg" class="flex flex-col items-center text-center">
        <div class="flex w-full items-center gap-1.5 font-bold text-magenta-bright">
          <span class="material-symbols-outlined text-[18px]!">warning</span> Ryzyko AML
        </div>
        <svg width="150" height="150" viewBox="0 0 150 150" class="my-3">
          <circle cx="75" cy="75" r="62" fill="none" stroke="var(--color-surface-highest)" stroke-width="12" />
          <text x="75" y="70" text-anchor="middle" font-size="34" font-weight="800" fill="var(--color-content-variant)">
            —
          </text>
          <text x="75" y="92" text-anchor="middle" font-size="11" fill="var(--color-content-variant)">
            WSKAŹNIK
          </text>
        </svg>
        <div class="text-xs text-content-variant">
          Wskaźnik liczy osobna aplikacja AML - jeszcze niepodpięta.
        </div>
      </GlassCard>
    </div>
  </div>
</template>
