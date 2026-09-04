<script setup lang="ts">
import ModulePlaceholder from '../../components/layout/ModulePlaceholder.vue'

/** SZKIELET modulu. Docelowa logika powstanie w kolejnych sprintach. */
const plannedFeatures = [
      'Lista rozliczeń delegacji per klient',
      'Zatwierdzanie rozliczeń przed księgowaniem',
      'Podgląd pozycji: diety, noclegi, przejazdy',
      'Eksport zatwierdzonych rozliczeń do modułu Finanse',
]
</script>

<template>
  <ModulePlaceholder
    eyebrow="Ewidencja"
    title="Delegacje"
    description="Rozliczenia podróży służbowych pracowników obsługiwanych firm."
    endpoint="/delegacje"
    external-app="DelegoApp"
    :planned-features="plannedFeatures"
  />
</template>
