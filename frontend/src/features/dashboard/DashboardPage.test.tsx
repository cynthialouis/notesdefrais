import { screen, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { json, stubApi } from '../../test/api'
import { employee } from '../../test/fixtures'
import { renderAsync } from '../../test/render'
import { useSessionStore } from '../auth/sessionStore'
import { DashboardPage } from './DashboardPage'

const myStats = {
  byStatus: [
    { status: 'draft', count: 3, totalCents: 24799 },
    { status: 'submitted', count: 1, totalCents: 4250 },
    { status: 'approved', count: 0, totalCents: 0 },
    { status: 'rejected', count: 1, totalCents: 6200 },
  ],
}

function renderDashboard(user = employee) {
  useSessionStore.setState({ session: { status: 'authenticated', user } })
  return renderAsync(
    <MemoryRouter>
      <DashboardPage />
    </MemoryRouter>,
  )
}

/** Valeurs affichées pour un état : [montant, nombre]. */
function totalsOf(label: string): string[] {
  const term = screen.getByText(label, { selector: 'dt' })
  const group = term.parentElement
  return group ? within(group).getAllByRole('definition').map((element) => element.textContent ?? '') : []
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('DashboardPage – mes notes', () => {
  it('C1 – affiche le nombre et le montant de ses notes pour chaque état', async () => {
    stubApi({ 'GET /api/stats/me': json(200, myStats) })
    await renderDashboard()

    await screen.findByText('Brouillon', { selector: 'dt' })
    expect(totalsOf('Brouillon')).toEqual([expect.stringMatching(/^247,99\s€$/), '3 notes'])
    expect(totalsOf('Soumise')).toEqual([expect.stringMatching(/^42,50\s€$/), '1 note'])
    expect(totalsOf('Rejetée')).toEqual([expect.stringMatching(/^62,00\s€$/), '1 note'])
  })

  it('C2 – un état sans note s\'affiche à zéro', async () => {
    stubApi({ 'GET /api/stats/me': json(200, myStats) })
    await renderDashboard()

    await screen.findByText('Validée', { selector: 'dt' })
    expect(totalsOf('Validée')).toEqual([expect.stringMatching(/^0,00\s€$/), 'Aucune note'])
  })

  it('signale des totaux impossibles à charger', async () => {
    stubApi({ 'GET /api/stats/me': json(500, { error: { code: 'internal_error', message: 'Erreur interne.' } }) })
    await renderDashboard()

    expect(await screen.findByRole('alert')).toHaveTextContent('Erreur interne.')
  })

  it('garde les raccourcis vers ses notes', async () => {
    stubApi({ 'GET /api/stats/me': json(200, myStats) })
    await renderDashboard()

    expect(await screen.findByRole('link', { name: 'Voir mes notes' })).toHaveAttribute('href', '/notes')
    expect(screen.getByRole('link', { name: 'Nouvelle note' })).toHaveAttribute('href', '/notes/nouvelle')
  })
})
