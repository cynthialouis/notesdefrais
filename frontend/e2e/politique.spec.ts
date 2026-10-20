import { expect, openNotesPage, test } from './fixtures.ts'

// Parcours 3 (spec 004) : l'admin baisse un plafond, les notes en cours au-dessus sont signalées hors politique.
// Part des notes de démo : « Déjeuner client Dupont » (42,50 €, repas, soumise), sous le plafond repas de 50 €.

test('un plafond baissé par l’admin signale les notes de salariés qui le dépassent', async ({ page, loginAs }) => {
  // Avant : la note est conforme
  await loginAs('Léa Bernard')
  await openNotesPage(page, 'Mes notes')
  await expect(page.getByRole('row', { name: /Déjeuner client Dupont/ })).not.toContainText('Hors politique')

  // Alice baisse le plafond repas à 40 €
  await loginAs('Alice Martin')
  await openNotesPage(page, 'Politique')
  const repas = page.getByRole('group', { name: 'Repas' })
  await repas.getByLabel('Plafond (€)').fill('40')
  await repas.getByRole('button', { name: 'Enregistrer' }).click()
  await expect(page.getByText('Politique enregistrée pour Repas.')).toBeVisible()

  // Après : la note dépasse le nouveau plafond de 2,50 €, dans la liste et sur le détail
  await loginAs('Léa Bernard')
  await openNotesPage(page, 'Mes notes')
  await expect(page.getByRole('row', { name: /Déjeuner client Dupont/ })).toContainText(/Hors politique · \+2,50\s€/)
  await page.getByRole('link', { name: 'Déjeuner client Dupont' }).click()
  await expect(page.getByText(/Hors politique : le plafond de 40,00\s€ pour la catégorie Repas est dépassé de 2,50\s€/)).toBeVisible()
})
