import { defineConfig, devices } from '@playwright/test'

// Tests de bout en bout : un vrai navigateur, le vrai front et la vraie API, sur des ports à part
// (front 5174, API 8001) pour tourner pendant `make dev`. Lancement : `make test-e2e` depuis la racine.
const API_URL = 'http://127.0.0.1:8001'
const FRONT_URL = 'http://127.0.0.1:5174'

export default defineConfig({
  testDir: './e2e',
  // Un seul test à la fois : tous partagent la base e2e, remise à zéro avant chaque test (e2e/fixtures.ts)
  workers: 1,
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  use: {
    baseURL: FRONT_URL,
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: [
    {
      // API en environnement e2e (base var/data_e2e.db), avec les limites d'upload de backend/php.ini
      command: 'APP_ENV=e2e php -d upload_max_filesize=6M -d post_max_size=8M -S 127.0.0.1:8001 -t public public/index.php',
      cwd: '../backend',
      url: `${API_URL}/api/doc.json`,
      reuseExistingServer: false,
    },
    {
      command: `API_PROXY_TARGET=${API_URL} npm run dev -- --host 127.0.0.1 --port 5174 --strictPort`,
      url: FRONT_URL,
      reuseExistingServer: false,
    },
  ],
})
