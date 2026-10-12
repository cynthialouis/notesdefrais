import { screen, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { json, stubApi } from '../../test/api'
import { overLimitExpense, teamSubmittedExpense } from '../../test/fixtures'
import { renderAsync } from '../../test/render'
import { useExpensesStore } from '../expenses/expensesStore'
import { TeamPage } from './TeamPage'

function renderTeam() {
  return renderAsync(
    <MemoryRouter>
      <TeamPage />
    </MemoryRouter>,
  )
}

afterEach(() => {
  vi.unstubAllGlobals()
  useExpensesStore.setState(useExpensesStore.getInitialState(), true)
})

describe('TeamPage', () => {
  it('C1/C2 – liste les notes à traiter avec auteur, libellé, date, catégorie, montant et date de soumission', async () => {
    stubApi({ 'GET /api/team/expenses': json(200, [teamSubmittedExpense]) })
    await renderTeam()

    const [, row] = await screen.findAllByRole('row')
    expect(within(row!).getAllByRole('cell').map((cell) => (cell.textContent ?? '').replace(/\s/g, ' '))).toEqual([
      'Léa Bernard',
      'Déjeuner client',
      '29/09/2026',
      'Repas',
      '42,50 €',
      '30/09/2026',
    ])
    expect(screen.getByRole('link', { name: 'Déjeuner client' })).toHaveAttribute('href', '/equipe/1')
  })

  it('C7 – aucune note à traiter : message explicite', async () => {
    stubApi({ 'GET /api/team/expenses': json(200, []) })
    await renderTeam()

    expect(await screen.findByText('Aucune note à traiter.')).toBeInTheDocument()
  })

  it('signale une liste impossible à charger', async () => {
    stubApi({ 'GET /api/team/expenses': json(403, { error: { code: 'forbidden', message: 'Accès refusé.' } }) })
    await renderTeam()

    expect(await screen.findByRole('alert')).toHaveTextContent('Accès refusé.')
  })
})

describe('TeamPage – politique', () => {
  it('C6 – le responsable voit le signalement hors politique', async () => {
    stubApi({ 'GET /api/team/expenses': json(200, [{ ...overLimitExpense, editable: false, decidable: true }]) })
    await renderTeam()

    expect(await screen.findByText('Hors politique')).toBeInTheDocument()
  })
})
