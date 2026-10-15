import { expect, test } from './fixtures.ts'

// Parcours 2 (spec 003) : rejet motivé, correction, nouvelle soumission, validation, historique.
// Part d'une note de démo : « Déjeuner client Dupont », soumise par Léa à Claire, sa responsable.

test('une note rejetée est corrigée, soumise de nouveau puis validée', async ({ page, loginAs }) => {
  const reason = 'Précisez la société du client invité.'

  // Claire rejette la note avec un motif
  await loginAs('Claire Dubois')
  await page.getByRole('navigation').getByRole('link', { name: 'Équipe' }).click()
  await page.getByRole('link', { name: 'Déjeuner client Dupont' }).click()
  await page.getByLabel('Motif du rejet').fill(reason)
  await page.getByRole('button', { name: 'Rejeter la note' }).click()
  await expect(page).toHaveURL('/equipe')
  await expect(page.getByRole('link', { name: 'Déjeuner client Dupont' })).toHaveCount(0)

  // Léa voit le motif, corrige le libellé et soumet de nouveau
  await loginAs('Léa Bernard')
  await page.getByRole('navigation').getByRole('link', { name: 'Mes notes' }).click()
  await expect(page.getByRole('row', { name: /Déjeuner client Dupont/ })).toContainText('Rejetée')
  await page.getByRole('link', { name: 'Déjeuner client Dupont' }).click()
  await expect(page.getByText(`« ${reason} »`).first()).toBeVisible()
  await page.getByLabel('Libellé').fill('Déjeuner client Dupont (Dupont SA)')
  await page.getByRole('button', { name: 'Enregistrer les modifications' }).click()
  await expect(page).toHaveURL('/notes')
  await page.getByRole('link', { name: 'Déjeuner client Dupont (Dupont SA)' }).click()
  await page.getByRole('button', { name: 'Soumettre la note' }).click()
  await expect(page).toHaveURL('/notes')
  await expect(page.getByRole('row', { name: /Dupont SA/ })).toContainText('Soumise')

  // Claire valide la note corrigée
  await loginAs('Claire Dubois')
  await page.getByRole('navigation').getByRole('link', { name: 'Équipe' }).click()
  await page.getByRole('link', { name: 'Déjeuner client Dupont (Dupont SA)' }).click()
  const detailUrl = page.url()
  await page.getByRole('button', { name: 'Valider la note' }).click()
  await expect(page).toHaveURL('/equipe')

  // La note validée garde tout son historique, dans l'ordre
  await page.goto(detailUrl)
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Validée')
  const history = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Historique' }) }).getByRole('listitem')
  await expect(history).toContainText(['Note créée', 'Note soumise', 'Note rejetée', 'Note soumise', 'Note validée'])
  await expect(history.filter({ hasText: 'Note rejetée' })).toContainText(reason)
})
