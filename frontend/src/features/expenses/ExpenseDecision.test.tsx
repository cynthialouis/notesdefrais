import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { json, stubApi } from '../../test/api'
import { categories, rejectedExpense, teamSubmittedExpense } from '../../test/fixtures'
import { renderAsync } from '../../test/render'
import { useSessionStore } from '../auth/sessionStore'
import { ExpenseDetailRoute } from './ExpenseDetailPage'
import { useExpensesStore } from './expensesStore'

const claire = { id: 2, name: 'Claire Dubois', email: 'claire@demo.test', role: 'manager' as const, accessibleRoles: ['manager' as const, 'employee' as const] }
const lea = { id: 4, name: 'Léa Bernard', email: 'lea@demo.test', role: 'employee' as const, accessibleRoles: ['employee' as const] }

const history = [
  { type: 'created', actor: { id: 4, name: 'Léa Bernard' }, comment: null, createdAt: '2026-09-29T08:00:00+00:00' },
  { type: 'submitted', actor: { id: 4, name: 'Léa Bernard' }, comment: null, createdAt: '2026-09-30T07:00:00+00:00' },
]

function renderAt(path: string, user: typeof claire | typeof lea) {
  useSessionStore.setState({ session: { status: 'authenticated', user } })
  return renderAsync(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="equipe/:id" element={<ExpenseDetailRoute back={{ to: '/equipe', label: 'Équipe' }} />} />
        <Route path="notes/:id" element={<ExpenseDetailRoute back={{ to: '/notes', label: 'Mes notes' }} />} />
        <Route path="equipe" element={<h1>Liste à traiter</h1>} />
        <Route path="notes" element={<h1>Liste des notes</h1>} />
      </Routes>
    </MemoryRouter>,
  )
}

afterEach(() => {
  vi.unstubAllGlobals()
  useExpensesStore.setState(useExpensesStore.getInitialState(), true)
  useSessionStore.setState(useSessionStore.getInitialState(), true)
})

describe('Décision du manager', () => {
  it('C12 – le manager voit la note de son équipe, son auteur, et ne peut pas la modifier', async () => {
    stubApi({ 'GET /api/expenses/1': json(200, teamSubmittedExpense), 'GET /api/expenses/1/events': json(200, history), 'GET /api/categories': json(200, categories) })
    await renderAt('/equipe/1', claire)

    expect(await screen.findByText('Note de Léa Bernard')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Valider la note' })).toBeInTheDocument()
    expect(screen.queryByLabelText('Libellé')).not.toBeInTheDocument()
  })

  it('C3 – « Valider la note » valide puis revient à la liste à traiter', async () => {
    const fetchMock = stubApi({
      'GET /api/expenses/1': json(200, teamSubmittedExpense),
      'GET /api/expenses/1/events': json(200, history),
      'GET /api/categories': json(200, categories),
      'POST /api/expenses/1/approve': json(200, { ...teamSubmittedExpense, status: 'approved', decidedAt: '2026-10-01T08:00:00+00:00', decidedBy: { id: 2, name: 'Claire Dubois' }, decidable: false }),
      'GET /api/team/expenses': json(200, []),
    })
    await renderAt('/equipe/1', claire)

    await userEvent.click(await screen.findByRole('button', { name: 'Valider la note' }))

    expect(await screen.findByRole('heading', { name: 'Liste à traiter' })).toBeInTheDocument()
    expect(fetchMock.mock.calls.some(([url, init]) => url === '/api/expenses/1/approve' && init?.method === 'POST')).toBe(true)
  })

  it('C5 – un motif trop court est signalé sous le champ, sans rien envoyer', async () => {
    const fetchMock = stubApi({ 'GET /api/expenses/1': json(200, teamSubmittedExpense), 'GET /api/expenses/1/events': json(200, history), 'GET /api/categories': json(200, categories) })
    await renderAt('/equipe/1', claire)

    await userEvent.type(await screen.findByLabelText('Motif du rejet'), ' Non ')
    await userEvent.click(screen.getByRole('button', { name: 'Rejeter la note' }))

    expect(await screen.findByText('Le motif doit faire au moins 5 caractères.')).toBeInTheDocument()
    expect(screen.getByLabelText('Motif du rejet')).toHaveAttribute('aria-invalid', 'true')
    expect(fetchMock.mock.calls.some(([url]) => String(url).endsWith('/reject'))).toBe(false)
  })

  it('C4 – rejeter envoie le motif sans les espaces autour puis revient à la liste', async () => {
    const fetchMock = stubApi({
      'GET /api/expenses/1': json(200, teamSubmittedExpense),
      'GET /api/expenses/1/events': json(200, history),
      'GET /api/categories': json(200, categories),
      'POST /api/expenses/1/reject': json(200, { ...rejectedExpense, id: 1, decidable: false, editable: false }),
      'GET /api/team/expenses': json(200, []),
    })
    await renderAt('/equipe/1', claire)

    await userEvent.type(await screen.findByLabelText('Motif du rejet'), '  Reçu illisible  ')
    await userEvent.click(screen.getByRole('button', { name: 'Rejeter la note' }))

    expect(await screen.findByRole('heading', { name: 'Liste à traiter' })).toBeInTheDocument()
    const call = fetchMock.mock.calls.find(([url]) => String(url).endsWith('/reject'))
    expect(JSON.parse(String(call?.[1]?.body))).toEqual({ reason: 'Reçu illisible' })
  })

  it('C6 – une décision refusée par le serveur (409) est affichée', async () => {
    stubApi({
      'GET /api/expenses/1': json(200, teamSubmittedExpense),
      'GET /api/expenses/1/events': json(200, history),
      'GET /api/categories': json(200, categories),
      'POST /api/expenses/1/approve': json(409, { error: { code: 'conflict', message: 'Seule une note soumise peut être validée ou rejetée.' } }),
    })
    await renderAt('/equipe/1', claire)

    await userEvent.click(await screen.findByRole('button', { name: 'Valider la note' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Seule une note soumise peut être validée ou rejetée.')
  })
})

describe('Note rejetée et historique', () => {
  it('C8 – le propriétaire voit le motif et peut corriger puis resoumettre', async () => {
    stubApi({ 'GET /api/expenses/3': json(200, rejectedExpense), 'GET /api/expenses/3/events': json(200, []), 'GET /api/categories': json(200, categories) })
    await renderAt('/notes/3', lea)

    expect(await screen.findByText(/Rejetée par Claire Dubois/)).toHaveTextContent('« Précisez le trajet. »')
    expect(screen.getByLabelText('Libellé')).toHaveValue('Taxi aéroport')
    expect(screen.getByRole('button', { name: 'Soumettre la note' })).toBeInTheDocument()
  })

  it('C11 – affiche l\'historique du plus ancien au plus récent, avec le motif d\'un rejet', async () => {
    const withRejection = [...history, { type: 'rejected', actor: { id: 2, name: 'Claire Dubois' }, comment: 'Précisez le trajet.', createdAt: '2026-10-01T08:00:00+00:00' }]
    stubApi({ 'GET /api/expenses/3': json(200, rejectedExpense), 'GET /api/expenses/3/events': json(200, withRejection), 'GET /api/categories': json(200, categories) })
    await renderAt('/notes/3', lea)

    const items = await screen.findAllByRole('listitem')
    expect(within(items[0]!).getByText('Note créée')).toBeInTheDocument()
    expect(within(items[1]!).getByText('Note soumise')).toBeInTheDocument()
    expect(within(items[2]!).getByText('Note rejetée')).toBeInTheDocument()
    expect(items[2]).toHaveTextContent('Claire Dubois, le 01/10/2026 10:00')
    expect(items[2]).toHaveTextContent('« Précisez le trajet. »')
  })
})

describe('Resoumission d\'une note rejetée', () => {
  it('C8 – « Soumettre la note » resoumet la note rejetée puis revient à la liste', async () => {
    const fetchMock = stubApi({
      'GET /api/expenses/3': json(200, rejectedExpense),
      'GET /api/expenses/3/events': json(200, []),
      'GET /api/categories': json(200, categories),
      'POST /api/expenses/3/submit': json(200, { ...rejectedExpense, status: 'submitted', decidedAt: null, decidedBy: null, rejectionReason: null, editable: false }),
      'GET /api/expenses': json(200, []),
    })
    await renderAt('/notes/3', lea)

    await userEvent.click(await screen.findByRole('button', { name: 'Soumettre la note' }))

    expect(await screen.findByRole('heading', { name: 'Liste des notes' })).toBeInTheDocument()
    expect(fetchMock.mock.calls.some(([url, init]) => url === '/api/expenses/3/submit' && init?.method === 'POST')).toBe(true)
  })
})
