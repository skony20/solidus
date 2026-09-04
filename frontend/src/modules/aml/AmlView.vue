<script setup lang="ts">
import ModulePlaceholder from '../../components/layout/ModulePlaceholder.vue'

/** SZKIELET modulu. Docelowa logika powstanie w kolejnych sprintach. */
const plannedFeatures = [
      'Lista klientów posortowana według punktacji ryzyka',
      'Alerty wymagające decyzji analityka',
      'Historia ocen jednego klienta w czasie',
      'Zlecenie ponownego przeliczenia ryzyka',
]
</script>

<template>
  <ModulePlaceholder
    eyebrow="Zgodność"
    title="Ryzyko AML"
    description="Ocena ryzyka prania pieniędzy dla klientów biura wraz z alertami wymagającymi weryfikacji."
    endpoint="/aml"
    external-app="Analiza AML"
    :planned-features="plannedFeatures"
  />
</template>
