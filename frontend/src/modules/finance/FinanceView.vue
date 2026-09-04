<script setup lang="ts">
import ModulePlaceholder from '../../components/layout/ModulePlaceholder.vue'

/** SZKIELET modulu. Docelowa logika powstanie w kolejnych sprintach. */
const plannedFeatures = [
      'Rejestr dokumentów księgowych',
      'Rozrachunki i stan rozliczeń klienta',
      'Generator pism do urzędów',
      'Zestawienia okresowe',
]
</script>

<template>
  <ModulePlaceholder
    eyebrow="Księgowość"
    title="Finanse"
    description="Dokumenty księgowe, rozrachunki i generator pism do urzędów."
    endpoint="/finanse"
    :planned-features="plannedFeatures"
  />
</template>
