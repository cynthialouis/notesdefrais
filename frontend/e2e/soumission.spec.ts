import { expect, test } from './fixtures.ts'

// Parcours 1 (specs 002 et 005) : brouillon, justificatif obligatoire, envoi du fichier, soumission

/** Date locale au format AAAA-MM-JJ, quelques jours avant aujourd'hui (le champ refuse une date future). */
function daysAgo(days: number): string {
  const date = new Date()
  date.setDate(date.getDate() - days)
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

test('un salarié soumet une note d’hébergement après avoir joint le justificatif', async ({ page, loginAs }) => {
  await loginAs('Léa Bernard')

  await page.getByRole('link', { name: 'Nouvelle note' }).click()
  await page.getByLabel('Libellé').fill('Hôtel salon de Lyon')
  await page.getByLabel('Montant (€)').fill('120,00')
  await page.getByLabel('Date').fill(daysAgo(3))
  await page.getByLabel('Catégorie').selectOption({ label: 'Hébergement' })
  await page.getByRole('button', { name: 'Enregistrer le brouillon' }).click()

  // Détail du brouillon : la soumission est bloquée tant que le justificatif manque
  await expect(page).toHaveURL(/\/notes\/\d+$/)
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Hôtel salon de Lyon')
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Brouillon')
  const submit = page.getByRole('button', { name: 'Soumettre la note' })
  await expect(submit).toBeDisabled()

  // Fichier envoyé en multipart à la vraie API, qui en détecte le type d'après le contenu
  await page.getByLabel('Fichier').setInputFiles({
    name: 'facture-hotel.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.from('%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n'),
  })
  await page.getByRole('button', { name: 'Joindre' }).click()
  await expect(page.getByText('facture-hotel.pdf')).toBeVisible()

  // Le fichier stocké est relu tel quel par l'API, avec la session du navigateur
  const id = new URL(page.url()).pathname.split('/').pop()
  const receipt = await page.request.get(`/api/expenses/${id}/receipt`)
  expect(receipt.status()).toBe(200)
  expect(receipt.headers()['content-type']).toContain('application/pdf')

  await expect(submit).toBeEnabled()
  await submit.click()

  await expect(page).toHaveURL('/notes')
  await expect(page.getByRole('row', { name: /Hôtel salon de Lyon/ })).toContainText('Soumise')
})
