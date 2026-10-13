import { screen, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { json, stubApi } from '../../test/api'
import { employee, manager } from '../../test/fixtures'
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

const months = ['2025-11', '2025-12', '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09', '2026-10']
const teamStats = {
  from: '2025-11-01',
  to: '2026-10-31',
  count: 3,
  totalCents: 48100,
  byCategory: [
    { category: { slug: 'repas', label: 'Repas' }, count: 1, totalCents: 4200 },
    { category: { slug: 'transport', label: 'Transport' }, count: 0, totalCents: 0 },
    { category: { slug: 'materiel', label: 'Matériel' }, count: 2, totalCents: 43900 },
  ],
  byMonth: months.map((month) =>
    month === '2025-11' ? { month, count: 1, totalCents: 4200 } : month === '2026-02' ? { month, count: 2, totalCents: 43900 } : { month, count: 0, totalCents: 0 },
  ),
}

function rowsOf(name: string): string[][] {
  return within(screen.getByRole('table', { name }))
    .getAllByRole('row')
    .slice(1)
    .map((row) => within(row).getAllByRole('cell').map((cell) => cell.textContent ?? ''))
}

describe('DashboardPage – équipe', () => {
  it('C3 – le manager voit le total des notes validées de son équipe par catégorie', async () => {
    stubApi({ 'GET /api/stats/me': json(200, myStats), 'GET /api/stats/team': json(200, teamStats) })
    await renderDashboard(manager)

    await screen.findByRole('table', { name: 'Par catégorie' })
    expect(rowsOf('Par catégorie')).toEqual([
      ['Repas', '1', expect.stringMatching(/^42,00\s€$/)],
      ['Transport', '0', expect.stringMatching(/^0,00\s€$/)],
      ['Matériel', '2', expect.stringMatching(/^439,00\s€$/)],
    ])
    expect(screen.getByText(/De novembre 2025 à octobre 2026/)).toHaveTextContent(/481,00\s€, 3 notes/)
  })

  it('C4 – le manager voit les 12 mois de la période, y compris les mois sans note', async () => {
    stubApi({ 'GET /api/stats/me': json(200, myStats), 'GET /api/stats/team': json(200, teamStats) })
    await renderDashboard(manager)

    await screen.findByRole('table', { name: 'Par mois' })
    const rows = rowsOf('Par mois')
    expect(rows).toHaveLength(12)
    expect(rows[0]).toEqual(['novembre 2025', '1', expect.stringMatching(/^42,00\s€$/)])
    expect(rows[1]).toEqual(['décembre 2025', '0', expect.stringMatching(/^0,00\s€$/)])
    expect(rows[11]?.[0]).toBe('octobre 2026')
  })

  it('C6 – le manager peut télécharger l\'export CSV', async () => {
    stubApi({ 'GET /api/stats/me': json(200, myStats), 'GET /api/stats/team': json(200, teamStats) })
    await renderDashboard(manager)

    const link = await screen.findByRole('link', { name: 'Exporter en CSV' })
    expect(link).toHaveAttribute('href', '/api/exports/team-expenses.csv')
    expect(link).toHaveAttribute('download')
  })

  it('C9 – sans note validée sur la période, un message remplace les tableaux', async () => {
    const empty = { ...teamStats, count: 0, totalCents: 0, byCategory: teamStats.byCategory.map((entry) => ({ ...entry, count: 0, totalCents: 0 })), byMonth: months.map((month) => ({ month, count: 0, totalCents: 0 })) }
    stubApi({ 'GET /api/stats/me': json(200, myStats), 'GET /api/stats/team': json(200, empty) })
    await renderDashboard(manager)

    expect(await screen.findByRole('heading', { name: 'Aucune note validée sur la période' })).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('un salarié ne voit pas la partie équipe et ne la demande pas au serveur', async () => {
    const fetchMock = stubApi({ 'GET /api/stats/me': json(200, myStats) })
    await renderDashboard(employee)

    await screen.findByText('Brouillon', { selector: 'dt' })
    expect(screen.queryByRole('heading', { name: 'Équipe : notes validées' })).not.toBeInTheDocument()
    expect(fetchMock.mock.calls.some(([url]) => url === '/api/stats/team')).toBe(false)
  })
})

