<script setup lang="ts">
import ModulePlaceholder from '../../components/layout/ModulePlaceholder.vue'

/** SZKIELET modulu. Docelowa logika powstanie w kolejnych sprintach. */
const plannedFeatures = [
      'Liczba otwartych zgłoszeń i te po terminie reakcji',
      'Statusy spraw bez dostępu do ich treści',
      'Przypomnienia o ustawowym terminie odpowiedzi',
      'Raport zgodności dla klienta biura',
]
</script>

<template>
  <ModulePlaceholder
    eyebrow="Zgodność"
    title="Sygnaliści"
    description="Metadane zgłoszeń z kanału dla sygnalistów. Treść zgłoszeń pozostaje w osobnej aplikacji."
    endpoint="/sygnalisci"
    external-app="Kanał sygnalistów"
    :planned-features="plannedFeatures"
  />
</template>
