import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { json, stubApi } from '../../test/api'
import { categories } from '../../test/fixtures'
import { renderAsync } from '../../test/render'
import { PolicyPage } from './PolicyPage'

function group(name: string) {
  return within(screen.getByRole('group', { name }))
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('PolicyPage', () => {
  it('C1 – affiche pour chaque catégorie son plafond (ou aucun) et le justificatif', async () => {
    stubApi({ 'GET /api/categories': json(200, [...categories, { slug: 'autre', label: 'Autre', maxAmountCents: null, receiptRequired: false }]) })
    await renderAsync(<PolicyPage />)

    await screen.findByRole('group', { name: 'Repas' })
    expect(group('Repas').getByLabelText('Plafond (€)')).toHaveValue('50,00')
    expect(group('Autre').getByLabelText('Plafond (€)')).toHaveValue('')
    expect(group('Hébergement').getByLabelText('Justificatif obligatoire')).toBeChecked()
    expect(group('Repas').getByLabelText('Justificatif obligatoire')).not.toBeChecked()
  })

  it('C2 – enregistre la politique d\'une catégorie et le confirme', async () => {
    const fetchMock = stubApi({
      'GET /api/categories': json(200, categories),
      'PUT /api/policies/repas': json(200, { slug: 'repas', label: 'Repas', maxAmountCents: 6000, receiptRequired: true }),
    })
    await renderAsync(<PolicyPage />)
    await screen.findByRole('group', { name: 'Repas' })

    const plafond = group('Repas').getByLabelText('Plafond (€)')
    await userEvent.clear(plafond)
    await userEvent.type(plafond, '60')
    await userEvent.click(group('Repas').getByLabelText('Justificatif obligatoire'))
    await userEvent.click(group('Repas').getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findByText('Politique enregistrée pour Repas.')).toBeInTheDocument()
    const put = fetchMock.mock.calls.find(([, init]) => init?.method === 'PUT')
    expect(JSON.parse(String(put?.[1]?.body))).toEqual({ maxAmountCents: 6000, receiptRequired: true })
  })

  it('C2 – un plafond vidé est envoyé comme « aucun plafond »', async () => {
    const fetchMock = stubApi({
      'GET /api/categories': json(200, categories),
      'PUT /api/policies/repas': json(200, { slug: 'repas', label: 'Repas', maxAmountCents: null, receiptRequired: false }),
    })
    await renderAsync(<PolicyPage />)
    await screen.findByRole('group', { name: 'Repas' })

    await userEvent.clear(group('Repas').getByLabelText('Plafond (€)'))
    await userEvent.click(group('Repas').getByRole('button', { name: 'Enregistrer' }))

    await screen.findByText('Politique enregistrée pour Repas.')
    const put = fetchMock.mock.calls.find(([, init]) => init?.method === 'PUT')
    expect(JSON.parse(String(put?.[1]?.body))).toEqual({ maxAmountCents: null, receiptRequired: false })
  })

  it('C3 – un plafond invalide est signalé sous le champ, sans rien envoyer', async () => {
    const fetchMock = stubApi({ 'GET /api/categories': json(200, categories) })
    await renderAsync(<PolicyPage />)
    await screen.findByRole('group', { name: 'Repas' })

    const plafond = group('Repas').getByLabelText('Plafond (€)')
    await userEvent.clear(plafond)
    await userEvent.type(plafond, '0')
    await userEvent.click(group('Repas').getByRole('button', { name: 'Enregistrer' }))

    expect(await group('Repas').findByText('Le plafond doit être positif.')).toBeInTheDocument()
    expect(group('Repas').getByLabelText('Plafond (€)')).toHaveAttribute('aria-invalid', 'true')
    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'PUT')).toBe(false)
  })
})
