<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useTenantAdminStore, type TenantStatusValue } from '../../stores/tenants'
import GlassCard from '../../components/ui/GlassCard.vue'
import SectionTitle from '../../components/ui/SectionTitle.vue'
import Badge from '../../components/ui/Badge.vue'

/**
 * Lista biur widziana oczami operatora Solidusa.
 *
 * Ekran dostepny wylacznie dla roli `platform_admin` - straznik trasy chowa
 * go przed reszta (frontend/src/router/index.ts), a prawdziwa ochrona stoi
 * w API (PlatformAdminMiddleware). Dane pochodza z TenantAdminRepository,
 * ktore CELOWO nie filtruje po tenant_id - to jedyny ekran w calym Solidusie,
 * ktory ma pokazac wszystkie biura naraz.
 */
const store = useTenantAdminStore()
const { items, isLoading, error } = storeToRefs(store)

const search = ref('')
const statusFilter = ref<TenantStatusValue | ''>('')

onMounted(() => {
  void store.load()
})

async function applyFilters(): Promise<void> {
  await store.load(search.value, statusFilter.value)
}

const statusOptions: Array<{ value: TenantStatusValue | ''; label: string }> = [
  { value: '', label: 'Wszystkie' },
  { value: 'trial', label: 'Okres próbny' },
  { value: 'active', label: 'Aktywne' },
  { value: 'suspended', label: 'Zawieszone' },
  { value: 'cancelled', label: 'Zakończone' },
]

const statusBadgeVariant: Record<TenantStatusValue, 'cyan' | 'low' | 'critical' | 'neutral'> = {
  trial: 'cyan',
  active: 'low',
  suspended: 'critical',
  cancelled: 'neutral',
}

const trialCount = computed(() => items.value.filter((t) => t.status === 'trial').length)
const overdueCount = computed(
  () => items.value.filter((t) => t.status === 'active' && !t.isPaidUpToDate).length,
)
</script>

<template>
  <div class="animate-fade-in">
    <SectionTitle eyebrow="Administracja systemu" title="Biura">
      Wszystkie biura zarejestrowane w Solidusie. Widok operatora - nie pokazuje danych
      merytorycznych żadnego biura, wyłącznie to, kim jest i czy płaci.
    </SectionTitle>

    <div class="mb-5 grid gap-4 sm:grid-cols-3">
      <GlassCard padding="sm">
        <div class="text-[11px] font-bold uppercase tracking-[0.1em] text-content-variant">
          Biur łącznie
        </div>
        <div class="mt-1 text-2xl font-extrabold">{{ store.total }}</div>
      </GlassCard>
      <GlassCard padding="sm" accent="cyan">
        <div class="text-[11px] font-bold uppercase tracking-[0.1em] text-content-variant">
          W okresie próbnym
        </div>
        <div class="mt-1 text-2xl font-extrabold text-cyan-bright">{{ trialCount }}</div>
      </GlassCard>
      <GlassCard padding="sm" accent="magenta">
        <div class="text-[11px] font-bold uppercase tracking-[0.1em] text-content-variant">
          Aktywne, zaległe z płatnością
        </div>
        <div class="mt-1 text-2xl font-extrabold text-magenta-bright">{{ overdueCount }}</div>
      </GlassCard>
    </div>

    <GlassCard padding="sm" class="mb-5">
      <form class="flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
        <label class="flex-1 basis-[220px]">
          <span class="mb-1.5 block text-[12px] font-bold text-content-variant">Szukaj</span>
          <input
            v-model="search"
            placeholder="Nazwa albo slug biura…"
            class="w-full rounded-glass-sm border border-outline bg-surface-low px-3.5 py-2.5 text-[13px] text-content outline-none focus:border-[rgba(0,219,231,0.5)]"
          />
        </label>
        <label>
          <span class="mb-1.5 block text-[12px] font-bold text-content-variant">Stan</span>
          <select
            v-model="statusFilter"
            class="rounded-glass-sm border border-outline bg-surface-low px-3.5 py-2.5 text-[13px] text-content outline-none focus:border-[rgba(0,219,231,0.5)]"
          >
            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </label>
        <button
          type="submit"
          class="rounded-nav bg-linear-to-br from-cyan-bright to-cyan px-5 py-2.5 text-[13px] font-bold text-[#00363a] transition-all duration-150 hover:shadow-glow"
        >
          Filtruj
        </button>
      </form>
    </GlassCard>

    <p v-if="isLoading" class="text-sm text-content-variant">Wczytuję biura…</p>
    <p v-else-if="error" class="text-sm text-magenta-bright">{{ error }}</p>
    <p v-else-if="items.length === 0" class="text-sm text-content-variant">
      Brak biur spełniających kryteria.
    </p>

    <GlassCard v-else padding="none" class="overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] border-collapse text-sm">
          <thead>
            <tr class="border-b border-white/[0.07] text-left text-[11.5px] uppercase tracking-[0.06em] text-content-variant">
              <th class="px-5 py-3 font-bold">Biuro</th>
              <th class="px-5 py-3 font-bold">Stan</th>
              <th class="px-5 py-3 font-bold">Plan</th>
              <th class="px-5 py-3 font-bold">Konta</th>
              <th class="px-5 py-3 font-bold">Opłacone do</th>
              <th class="px-5 py-3 font-bold">Założone</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="tenant in items"
              :key="tenant.id"
              class="cursor-pointer border-b border-white/[0.05] transition-colors hover:bg-white/[0.03]"
              @click="$router.push(`/admin/biura/${tenant.id}`)"
            >
              <td class="px-5 py-3.5">
                <div class="font-bold">{{ tenant.name }}</div>
                <div class="text-[12px] text-content-variant">{{ tenant.slug }}</div>
              </td>
              <td class="px-5 py-3.5">
                <Badge :variant="statusBadgeVariant[tenant.status]">{{ tenant.statusLabel }}</Badge>
              </td>
              <td class="px-5 py-3.5 text-content-variant">
                {{ tenant.planName ?? tenant.plan }}
              </td>
              <td class="px-5 py-3.5 text-content-variant">{{ tenant.userCount }}</td>
              <td class="px-5 py-3.5">
                <span v-if="tenant.paidUntil === null" class="text-content-variant">—</span>
                <span
                  v-else
                  :class="tenant.isPaidUpToDate ? 'text-emerald-bright' : 'text-magenta-bright'"
                >
                  {{ tenant.paidUntil }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-content-variant">
                {{ new Date(tenant.createdAt).toLocaleDateString('pl-PL') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </GlassCard>
  </div>
</template>
