import { readFile } from 'node:fs/promises'
import { expect, test } from './fixtures.ts'

// Parcours 4 (spec 006) : tableau de bord d'un manager et export CSV des notes validées de son équipe.
// Part des notes de démo : « Formation sécurité » (Léa, validée par Claire).

test('un manager télécharge l’export CSV des notes validées de son équipe', async ({ page, loginAs }) => {
  await loginAs('Claire Dubois')
  await expect(page.getByRole('heading', { name: 'Équipe : notes validées' })).toBeVisible()

  const downloadPromise = page.waitForEvent('download')
  await page.getByRole('link', { name: 'Exporter en CSV' }).click()
  const download = await downloadPromise

  expect(download.suggestedFilename()).toMatch(/^notes-validees-equipe-\d{4}-\d{2}-\d{2}\.csv$/)
  const csv = await readFile(await download.path(), 'utf8')
  // Pour tableur français : BOM UTF-8, « ; » et CRLF
  expect(csv.startsWith('﻿Date;Salarié;Libellé;Catégorie;Montant;Date de validation;Validée par\r\n')).toBe(true)
  expect(csv).toContain(';Léa Bernard;Formation sécurité;Autre;250,00;')
})
