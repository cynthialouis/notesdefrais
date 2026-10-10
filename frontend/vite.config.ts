import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // Les appels /api sont redirigés vers l'API Symfony (symfony serve, port 8000 par défaut)
    proxy: {
      '/api': 'http://127.0.0.1:8000',
    },
  },
})
