import { execSync } from 'node:child_process'
import { resolve } from 'node:path'
import { expect, test as base } from '@playwright/test'

const repoRoot = resolve(import.meta.dirname, '../..')

type Fixtures = {
  resetDatabase: void
  loginAs: (name: string) => Promise<void>
}

export const test = base.extend<Fixtures>({
  // Avant chaque test : base e2e remise dans l'état des données de démo, pour partir d'un état connu
  resetDatabase: [
    // oxlint-disable-next-line no-empty-pattern -- Playwright exige un objet déstructuré en premier argument
    async ({}, use) => {
      execSync('make e2e-db', { cwd: repoRoot, stdio: 'pipe' })
      await use()
    },
    { auto: true },
  ],

  // Connexion par la vraie page de connexion, sous le compte de démo dont le nom est donné.
  // Le cookie de session est d'abord effacé : un même test peut passer d'un compte à l'autre.
  loginAs: async ({ page }, use) => {
    await use(async (name) => {
      await page.context().clearCookies()
      await page.goto('/login')
      await page.getByRole('radio', { name: new RegExp(name) }).check()
      await page.getByRole('button', { name: 'Se connecter' }).click()
      await expect(page.getByRole('heading', { name: 'Tableau de bord' })).toBeVisible()
    })
  },
})

export { expect }
