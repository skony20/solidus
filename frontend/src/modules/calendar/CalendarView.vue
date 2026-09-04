<script setup lang="ts">
import ModulePlaceholder from '../../components/layout/ModulePlaceholder.vue'

/** SZKIELET modulu. Docelowa logika powstanie w kolejnych sprintach. */
const plannedFeatures = [
      'Kalendarz terminów podatkowych per klient',
      'Radar Zmian - monitoring nowelizacji przepisów',
      'Przypomnienia o zbliżających się terminach',
      'Synchronizacja z kalendarzem pracownika',
]
</script>

<template>
  <ModulePlaceholder
    eyebrow="Terminy"
    title="Kalendarz"
    description="Terminy podatkowe klientów oraz Radar Zmian monitorujący zmiany w przepisach."
    endpoint="/kalendarz"
    :planned-features="plannedFeatures"
  />
</template>
