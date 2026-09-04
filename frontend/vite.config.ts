import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
// defineConfig z 'vitest/config' rozumie sekcję `test` obok zwykłej konfiguracji Vite.
import { defineConfig } from 'vitest/config'

export default defineConfig({
  // Tailwind 4 wchodzi jako plugin Vite, a nie przez PostCSS. Dzięki temu
  // nie ma już postcss.config.js ani autoprefixera - prefiksowanie robi
  // wbudowany Lightning CSS.
  plugins: [vue(), tailwindcss()],
  server: {
    port: 5173,
    // 0.0.0.0 jest potrzebne, gdy front działa w kontenerze Dockera -
    // inaczej serwer słuchałby tylko wewnątrz kontenera.
    host: true,
    watch: {
      // W kontenerze pliki przychodzą przez bind-mount z hosta, a powiadomienia
      // o zmianach (inotify) nie przekraczają tej granicy - Vite po prostu nie
      // zauważa edycji i serwuje stary kod. Odpytywanie co sekundę to jedyny
      // sposób, żeby hot reload działał w Dockerze. Poza kontenerem polling
      // jest zbędnym obciążeniem, więc włącza je zmienna z docker-compose.yml.
      usePolling: process.env.VITE_USE_POLLING === 'true',
      interval: 1000,
    },
  },
  test: {
    // Komponenty Vue potrzebują DOM-u; jsdom go udaje w Node.
    environment: 'jsdom',
    globals: true,
    include: ['src/**/*.spec.ts'],
  },
})
