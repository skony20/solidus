<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiErrorMessage, http } from '../../api/http'
import GlassCard from '../ui/GlassCard.vue'
import SectionTitle from '../ui/SectionTitle.vue'
import Badge from '../ui/Badge.vue'

/**
 * Wspolny widok modulow, ktore sa na razie szkieletami.
 *
 * Odpytuje swoj endpoint w API, wiec od razu widac, czy trasa, token
 * i tenant dzialaja - to jest test polaczenia, nie atrapa.
 */
const props = defineProps<{
  eyebrow: string
  title: string
  description: string
  endpoint: string
  /** Ustaw, gdy logika modulu zyje w osobnej aplikacji. */
  externalApp?: string
  plannedFeatures: string[]
}>()

const status = ref<'loading' | 'ok' | 'error'>('loading')
const message = ref('')

onMounted(async () => {
  try {
    const { data } = await http.get<{ status: string; module: string }>(props.endpoint)
    status.value = 'ok'
    message.value = `API odpowiada: ${data.status} / ${data.module}`
  } catch (e) {
    status.value = 'error'
    message.value = apiErrorMessage(e, 'Brak połączenia z API.')
  }
})
</script>

<template>
  <div class="animate-fade-in">
    <SectionTitle :eyebrow="eyebrow" :title="title">{{ description }}</SectionTitle>

    <GlassCard
      v-if="externalApp"
      accent="cyan"
      padding="sm"
      class="mb-5 flex items-center gap-3.5"
    >
      <span class="material-symbols-outlined text-cyan-bright">lan</span>
      <div>
        <div class="text-sm font-bold">Moduł oparty o osobną aplikację</div>
        <div class="mt-0.5 text-xs text-content-variant">
          Logika działa w aplikacji <strong>{{ externalApp }}</strong
          >. Solidus tylko pyta ją o dane przez API i pokazuje wynik.
        </div>
      </div>
    </GlassCard>

    <div class="grid gap-5 lg:grid-cols-[1.5fr_1fr]">
      <GlassCard padding="lg">
        <h3 class="m-0 mb-4 text-[19px] font-bold">Co się tu pojawi</h3>
        <ul class="m-0 flex list-none flex-col gap-2.5 p-0 text-sm text-content-variant">
          <li v-for="feature in plannedFeatures" :key="feature" class="flex items-start gap-2.5">
            <span class="material-symbols-outlined mt-px !text-base text-cyan-bright">
              chevron_right
            </span>
            {{ feature }}
          </li>
        </ul>
      </GlassCard>

      <GlassCard padding="lg">
        <h3 class="m-0 mb-4 text-[19px] font-bold">Stan połączenia</h3>
        <div class="flex items-center gap-2.5">
          <Badge :variant="status === 'ok' ? 'low' : status === 'error' ? 'critical' : 'neutral'">
            {{ status === 'ok' ? 'DZIAŁA' : status === 'error' ? 'BŁĄD' : 'SPRAWDZAM' }}
          </Badge>
          <code class="text-xs text-content-variant">GET /api{{ endpoint }}</code>
        </div>
        <p class="mt-3.5 text-xs text-content-variant">{{ message }}</p>
      </GlassCard>
    </div>
  </div>
</template>
