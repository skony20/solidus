<script setup lang="ts">
import ModulePlaceholder from '../../components/layout/ModulePlaceholder.vue'

/** SZKIELET modulu. Docelowa logika powstanie w kolejnych sprintach. */
const plannedFeatures = [
      'Lista pracowników biura z rolami',
      'Obciążenie pracą i przypisani klienci',
      'Zarządzanie uprawnieniami',
      'Aktywne sesje i urządzenia pracownika',
]
</script>

<template>
  <ModulePlaceholder
    eyebrow="Organizacja"
    title="Zespół"
    description="Pracownicy biura, ich obciążenie pracą i uprawnienia w systemie."
    endpoint="/zespol"
    :planned-features="plannedFeatures"
  />
</template>
