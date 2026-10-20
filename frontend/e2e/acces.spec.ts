import { expect, test } from './fixtures.ts'

// Parcours 5 (spec 001) : un salarié n'accède ni aux pages ni aux routes réservées aux managers et à l'admin

test('un salarié voit « Accès refusé » sur les pages manager et admin, et l’API lui répond 403', async ({ page, loginAs }) => {
  await loginAs('Léa Bernard')

  // Liens absents de la navigation
  const navigation = page.getByRole('navigation')
  await navigation.getByRole('button', { name: 'Notes de frais' }).click()
  await expect(navigation.getByRole('link', { name: 'Mes notes' })).toBeVisible()
  await expect(navigation.getByRole('link', { name: 'Équipe' })).toHaveCount(0)
  await expect(navigation.getByRole('link', { name: 'Politique' })).toHaveCount(0)

  // Adresse saisie à la main : page refusée
  for (const path of ['/equipe', '/politique']) {
    await page.goto(path)
    await expect(page.getByRole('heading', { level: 1, name: 'Accès refusé' })).toBeVisible()
  }

  // Le serveur reste la source de vérité : refus aussi en appelant l'API avec la session du navigateur
  expect((await page.request.get('/api/team/expenses')).status()).toBe(403)
  expect((await page.request.get('/api/exports/team-expenses.csv')).status()).toBe(403)
  const policy = await page.request.put('/api/policies/repas', { data: { maxAmountCents: 100, receiptRequired: false } })
  expect(policy.status()).toBe(403)
})
