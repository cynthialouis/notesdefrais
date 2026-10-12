import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

// https://vite.dev/config/ — defineConfig importé de vitest/config pour typer le bloc `test`
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    // Les appels /api sont redirigés vers l'API Symfony (symfony serve, port 8000 par défaut)
    proxy: {
      '/api': 'http://127.0.0.1:8000',
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
  },
})
