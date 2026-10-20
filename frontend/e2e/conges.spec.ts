import { expect, test } from './fixtures.ts'

// Parcours de la spec 007 (SC-002) : Tom demande un congé, Claire, sa responsable, l'accepte, Inès, d'une
// autre équipe, le voit dans les congés à venir, puis Alice (admin) l'annule et il disparaît.
// Période à 60 jours d'aujourd'hui : aucune demande de démo ne la chevauche.

/** Jour J + offset, au format Y-m-d du champ date et au format affiché. */
function day(offset: number): { iso: string; shown: string } {
  const date = new Date()
  date.setDate(date.getDate() + offset)
  const [y, m, d] = [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')]
  return { iso: `${y}-${m}-${d}`, shown: `${d}/${m}/${y}` }
}

test('un congé demandé, accepté, visible de tous, puis annulé par l\'admin', async ({ page, loginAs }) => {
  const [start, end] = [day(60), day(62)]
  const period = `Du ${start.shown} au ${end.shown}`
  const reason = 'Dates saisies par erreur, à redemander.'
  const openLeaves = () => page.getByRole('navigation').getByRole('link', { name: 'Congés' }).click()
  const upcoming = () => page.getByRole('table', { name: 'Congés à venir' })

  // Tom demande le congé : il attend la décision de Claire
  await loginAs('Tom Petit')
  await openLeaves()
  await page.getByLabel('Premier jour').fill(start.iso)
  await page.getByLabel('Dernier jour').fill(end.iso)
  await page.getByRole('button', { name: 'Demander ce congé' }).click()
  await expect(page.getByText('Demande envoyée : elle attend la décision de votre responsable.')).toBeVisible()
  await expect(page.getByRole('table', { name: 'Mes demandes' }).getByRole('row', { name: new RegExp(period) })).toContainText('En attente')

  // Claire l'accepte : il passe dans les congés à venir
  await loginAs('Claire Dubois')
  await openLeaves()
  await page.getByRole('button', { name: `Accepter la demande de Tom Petit (${period})` }).click()
  await expect(upcoming().getByRole('row', { name: `Tom Petit ${period}` })).toBeVisible()

  // Inès, de l'équipe de Hugo, le voit
  await loginAs('Inès Moreau')
  await openLeaves()
  await expect(upcoming().getByRole('row', { name: `Tom Petit ${period}` })).toBeVisible()

  // Alice l'annule avec un motif : il disparaît des congés à venir
  await loginAs('Alice Martin')
  await openLeaves()
  await page.getByRole('button', { name: `Annuler le congé de Tom Petit (${period})` }).click()
  await page.getByLabel('Motif de l\'annulation pour Tom Petit').fill(reason)
  await page.getByRole('button', { name: 'Annuler le congé', exact: true }).click()
  await expect(upcoming().getByRole('row', { name: new RegExp(`Tom Petit ${period}`) })).toHaveCount(0)

  // Tom voit l'annulation et son motif
  await loginAs('Tom Petit')
  await openLeaves()
  const row = page.getByRole('table', { name: 'Mes demandes' }).getByRole('row', { name: new RegExp(period) })
  await expect(row).toContainText('Annulée')
  await expect(row).toContainText(`Annulée par Alice Martin : « ${reason} »`)
})
