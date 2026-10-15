import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { configDefaults, defineConfig } from 'vitest/config'

// https://vite.dev/config/ — defineConfig importé de vitest/config pour typer le bloc `test`
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    // Les appels /api sont redirigés vers l'API Symfony (symfony serve, port 8000 par défaut).
    // Les tests e2e pointent vers leur propre API (port 8001) avec API_PROXY_TARGET.
    proxy: {
      '/api': process.env.API_PROXY_TARGET ?? 'http://127.0.0.1:8000',
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    // Les tests e2e/ sont lancés par Playwright, pas par Vitest
    exclude: [...configDefaults.exclude, 'e2e/**'],
  },
})
