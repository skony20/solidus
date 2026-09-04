<script setup lang="ts">
import ModulePlaceholder from '../../components/layout/ModulePlaceholder.vue'

/** SZKIELET modulu. Docelowa logika powstanie w kolejnych sprintach. */
const plannedFeatures = [
      'Dane biura i plan abonamentowy',
      'Integracja z KSeF',
      'Integracja z Fakturownią',
      'Połączenia z aplikacjami AML, DelegoApp i kanałem sygnalistów',
]
</script>

<template>
  <ModulePlaceholder
    eyebrow="Konfiguracja"
    title="Ustawienia"
    description="Dane biura, plan abonamentowy i połączenia z systemami zewnętrznymi."
    endpoint="/ustawienia"
    :planned-features="plannedFeatures"
  />
</template>
