<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from './components/layout/AppLayout.vue'

/**
 * Ekran logowania jest jedynym widokiem poza rama aplikacji - nie ma sensu
 * pokazywac menu bocznego komus, kto nie jest jeszcze zalogowany.
 */
const route = useRoute()
const isBare = computed(() => route.meta.public === true)
</script>

<template>
  <RouterView v-if="isBare" />
  <AppLayout v-else>
    <RouterView />
  </AppLayout>
</template>
