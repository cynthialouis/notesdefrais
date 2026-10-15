import { expect, test } from './fixtures.ts'

// Premier parcours, minimal : il vérifie la chaîne complète (proxy Vite, API e2e, cookie de session)

test('sans session, la page de connexion liste les comptes de démo', async ({ page }) => {
  await page.goto('/')

  await expect(page).toHaveURL('/login')
  await expect(page.getByRole('radio')).toHaveCount(7)
  await expect(page.getByRole('radio', { name: /Léa Bernard/ })).toBeVisible()
})

test('la session ouverte survit au rechargement de la page', async ({ page, loginAs }) => {
  await loginAs('Léa Bernard')

  await page.reload()

  await expect(page).toHaveURL('/')
  await expect(page.getByRole('heading', { name: 'Tableau de bord' })).toBeVisible()
  await expect(page.getByText('Connecté en tant que Léa Bernard, salarié.')).toBeVisible()
})
