import vue from '@vitejs/plugin-vue'
// defineConfig z 'vitest/config' rozumie sekcje `test` obok zwyklej konfiguracji Vite.
import { defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    // 0.0.0.0 jest potrzebne, gdy front dziala w kontenerze Dockera -
    // inaczej serwer sluchalby tylko wewnatrz kontenera.
    host: true,
  },
  test: {
    // Komponenty Vue potrzebuja DOM-u; jsdom go udaje w Node.
    environment: 'jsdom',
    globals: true,
    include: ['src/**/*.spec.ts'],
  },
})
