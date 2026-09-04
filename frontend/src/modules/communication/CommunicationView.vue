<script setup lang="ts">
import ModulePlaceholder from '../../components/layout/ModulePlaceholder.vue'

/** SZKIELET modulu. Docelowa logika powstanie w kolejnych sprintach. */
const plannedFeatures = [
      'Kreator masowej wysyłki z segmentacją klientów',
      'Szablony wiadomości z polami do podstawienia',
      'Skrzynka rozmów 1:1 z klientem',
      'Kolejka wysyłek działająca w tle (Redis)',
]
</script>

<template>
  <ModulePlaceholder
    eyebrow="Kontakt"
    title="Komunikacja"
    description="Masowe wysyłki e-mail do klientów biura oraz rozmowy jeden na jeden."
    endpoint="/komunikacja"
    :planned-features="plannedFeatures"
  />
</template>
