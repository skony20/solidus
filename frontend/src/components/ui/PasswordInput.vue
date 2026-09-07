<script setup lang="ts">
import { ref } from 'vue'

/**
 * Pole hasla z "oczkiem" do podgladu wpisanej tresci.
 *
 * Zachowuje sie jak zwykly <input v-model>. Pozostale atrybuty (required,
 * minlength, autocomplete, placeholder, ...) sa przekazywane wprost na
 * element input dzieki dziedziczeniu atrybutow.
 */
defineOptions({ inheritAttrs: false })

defineProps<{ modelValue: string }>()
defineEmits<{ (e: 'update:modelValue', value: string): void }>()

const visible = ref(false)
</script>

<template>
  <div class="relative">
    <input
      :value="modelValue"
      :type="visible ? 'text' : 'password'"
      v-bind="$attrs"
      class="w-full rounded-glass-sm border border-outline bg-surface-low px-3.5 py-2.5 pr-10 text-sm text-content outline-hidden placeholder:text-content-variant focus:border-[rgba(0,219,231,0.5)]"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <button
      type="button"
      tabindex="-1"
      :aria-label="visible ? 'Ukryj hasło' : 'Pokaż hasło'"
      :aria-pressed="visible"
      class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-content-variant transition-colors hover:text-cyan-bright"
      @click="visible = !visible"
    >
      <svg
        v-if="!visible"
        width="18"
        height="18"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
        <circle cx="12" cy="12" r="3" />
      </svg>
      <svg
        v-else
        width="18"
        height="18"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M10.7 5.1A9.9 9.9 0 0 1 12 5c6.5 0 10 7 10 7a15.7 15.7 0 0 1-3.4 4.2M6.6 6.6A15.7 15.7 0 0 0 2 12s3.5 7 10 7a9.9 9.9 0 0 0 5.4-1.6" />
        <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
        <path d="m2 2 20 20" />
      </svg>
    </button>
  </div>
</template>
