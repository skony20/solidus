<script setup lang="ts">
import GlassCard from './GlassCard.vue'

/**
 * Kafelek wskaznika (KPI) z pulpitu: etykieta, duza liczba i zmiana
 * wzgledem poprzedniego okresu.
 */
withDefaults(
  defineProps<{
    label: string
    value: string
    trend?: string
    tone?: 'default' | 'cyan' | 'magenta'
    trendTone?: 'positive' | 'negative'
    icon?: string
  }>(),
  { tone: 'default', trendTone: 'positive' },
)

const valueTones: Record<string, string> = {
  default: 'text-content',
  cyan: 'text-cyan-bright',
  magenta: 'text-magenta-bright',
}
</script>

<template>
  <GlassCard :accent="tone === 'magenta' ? 'magenta' : 'none'">
    <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-content-variant">
      {{ label }}
    </div>
    <div class="mt-1.5 text-[38px] font-extrabold leading-tight" :class="valueTones[tone]">
      {{ value }}
    </div>
    <div
      v-if="trend"
      class="mt-1.5 flex items-center gap-1 text-[13px]"
      :class="trendTone === 'positive' ? 'text-emerald-bright' : 'text-magenta-bright'"
    >
      <span class="material-symbols-outlined text-base!">
        {{ icon ?? (trendTone === 'positive' ? 'trending_up' : 'warning') }}
      </span>
      {{ trend }}
    </div>
  </GlassCard>
</template>
