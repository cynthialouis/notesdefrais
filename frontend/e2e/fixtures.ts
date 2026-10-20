import { execSync } from 'node:child_process'
import { resolve } from 'node:path'
import { expect, test as base, type Page } from '@playwright/test'
import { z } from 'zod'

const repoRoot = resolve(import.meta.dirname, '../..')

type Fixtures = {
  resetDatabase: void
  loginAs: (name: string) => Promise<void>
}

type WorkerFixtures = {
  apiUsesE2eDatabase: void
}

/** Identifiants des comptes de la base e2e, lus directement en console. */
function e2eUserIds(): number[] {
  const output = execSync('php bin/console dbal:run-sql "SELECT id FROM user ORDER BY id" --env=e2e', { cwd: resolve(repoRoot, 'backend'), encoding: 'utf8' })
  return [...output.matchAll(/^\s*(\d+)\s*$/gm)].map((match) => Number(match[1]))
}

export const test = base.extend<Fixtures, WorkerFixtures>({
  // Garde-fou, une fois par worker : l'API jointe par le proxy du front doit lire la base e2e.
  // Sinon (APP_ENV non transmis, proxy vers l'API de dev), les tests écriraient dans la base de dev.
  apiUsesE2eDatabase: [
    async ({ playwright }, use, workerInfo) => {
      execSync('make e2e-db', { cwd: repoRoot, stdio: 'pipe' })
      const api = await playwright.request.newContext({ baseURL: workerInfo.project.use.baseURL })
      const response = await api.get('/api/auth/demo-users')
      const users = z.array(z.object({ id: z.number() })).parse(await response.json())
      await api.dispose()
      const apiIds = users.map((user) => user.id).sort((a, b) => a - b)
      if (JSON.stringify(apiIds) !== JSON.stringify(e2eUserIds())) {
        throw new Error("L'API des tests ne lit pas la base e2e (var/data_e2e.db) : arrêt avant toute écriture.")
      }
      await use()
    },
    { auto: true, scope: 'worker' },
  ],

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

/** Ouvre une page du groupe « Notes de frais » du menu (Mes notes, Équipe, Politique). */
export async function openNotesPage(page: Page, name: string): Promise<void> {
  const navigation = page.getByRole('navigation')
  await navigation.getByRole('button', { name: 'Notes de frais' }).click()
  await navigation.getByRole('link', { name }).click()
}

export { expect }
