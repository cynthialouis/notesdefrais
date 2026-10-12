import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { json, stubApi } from '../../test/api'
import { categories, draftExpense, submittedExpense } from '../../test/fixtures'
import { renderAsync } from '../../test/render'
import { ExpenseDetailRoute } from './ExpenseDetailPage'
import { useExpensesStore } from './expensesStore'

function renderDetail(id: number) {
  return renderAsync(
    <MemoryRouter initialEntries={[`/notes/${id}`]}>
      <Routes>
        <Route path="notes/:id" element={<ExpenseDetailRoute back={{ to: '/notes', label: 'Mes notes' }} />} />
        <Route path="notes" element={<h1>Liste des notes</h1>} />
      </Routes>
    </MemoryRouter>,
  )
}

afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
  useExpensesStore.setState(useExpensesStore.getInitialState(), true)
})

describe('ExpenseDetailPage', () => {
  it('C5 – un brouillon s\'affiche dans le formulaire prérempli', async () => {
    stubApi({ 'GET /api/expenses/2/events': json(200, []),
      'GET /api/expenses/2': json(200, draftExpense), 'GET /api/categories': json(200, categories) })
    await renderDetail(2)

    expect(await screen.findByLabelText('Libellé')).toHaveValue('Train Paris – Lyon')
    expect(screen.getByLabelText('Montant (€)')).toHaveValue('89,00')
    expect(screen.getByLabelText('Date')).toHaveValue('2026-10-06')
    expect(screen.getByLabelText('Catégorie')).toHaveValue('transport')
  })

  it('C6 – « Soumettre la note » soumet le brouillon puis revient à la liste', async () => {
    const fetchMock = stubApi({
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/expenses/2': json(200, draftExpense),
      'GET /api/categories': json(200, categories),
      'POST /api/expenses/2/submit': json(200, { ...draftExpense, status: 'submitted', submittedAt: '2026-10-11T10:00:00+02:00', editable: false }),
      'GET /api/expenses': json(200, []),
    })
    await renderDetail(2)

    await userEvent.click(await screen.findByRole('button', { name: 'Soumettre la note' }))

    expect(await screen.findByRole('heading', { name: 'Liste des notes' })).toBeInTheDocument()
    expect(fetchMock.mock.calls.some(([url, init]) => url === '/api/expenses/2/submit' && init?.method === 'POST')).toBe(true)
  })

  it('C11 – la suppression confirmée supprime le brouillon puis revient à la liste', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    const fetchMock = stubApi({
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/expenses/2': json(200, draftExpense),
      'GET /api/categories': json(200, categories),
      'DELETE /api/expenses/2': () => new Response(null, { status: 204 }),
      'GET /api/expenses': json(200, []),
    })
    await renderDetail(2)

    await userEvent.click(await screen.findByRole('button', { name: 'Supprimer le brouillon' }))

    expect(await screen.findByRole('heading', { name: 'Liste des notes' })).toBeInTheDocument()
    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'DELETE')).toBe(true)
  })

  it('C11 – abandonner la confirmation ne supprime rien', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false)
    const fetchMock = stubApi({ 'GET /api/expenses/2/events': json(200, []),
      'GET /api/expenses/2': json(200, draftExpense), 'GET /api/categories': json(200, categories) })
    await renderDetail(2)

    await userEvent.click(await screen.findByRole('button', { name: 'Supprimer le brouillon' }))

    expect(fetchMock.mock.calls.some(([, init]) => init?.method === 'DELETE')).toBe(false)
    expect(screen.getByRole('button', { name: 'Supprimer le brouillon' })).toBeInTheDocument()
  })

  it('C7 – une note soumise s\'affiche en lecture seule, sans actions', async () => {
    stubApi({ 'GET /api/expenses/1/events': json(200, []),
      'GET /api/expenses/1': json(200, submittedExpense), 'GET /api/categories': json(200, categories) })
    await renderDetail(1)

    expect(await screen.findByText('Soumise')).toBeInTheDocument()
    expect(screen.getByText(/42,50/)).toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Soumettre la note' })).not.toBeInTheDocument()
  })

  it('une note introuvable (ou d\'un autre) affiche « Note introuvable. »', async () => {
    stubApi({
      'GET /api/expenses/9/events': json(200, []),
      'GET /api/expenses/9': json(404, { error: { code: 'not_found', message: 'Ressource introuvable.' } }),
      'GET /api/categories': json(200, categories),
    })
    await renderDetail(9)

    expect(await screen.findByRole('alert')).toHaveTextContent('Note introuvable.')
  })
})

describe('ExpenseDetailPage – modifiable selon le serveur', () => {
  it('suit l\'indication editable du serveur, sans recalculer la règle à partir de l\'état', async () => {
    stubApi({ 'GET /api/expenses/2/events': json(200, []),
      'GET /api/expenses/2': json(200, { ...draftExpense, editable: false }), 'GET /api/categories': json(200, categories) })
    await renderDetail(2)

    expect(await screen.findByText('Brouillon')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Soumettre la note' })).not.toBeInTheDocument()
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument()
  })
})

describe('ExpenseDetailPage – modification et refus', () => {
  it('C5 – enregistre les modifications en PATCH avec tous les champs, puis revient à la liste', async () => {
    const fetchMock = stubApi({
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/expenses/2': json(200, draftExpense),
      'GET /api/categories': json(200, categories),
      'PATCH /api/expenses/2': json(200, { ...draftExpense, label: 'Train Paris – Marseille', amountCents: 12000 }),
      'GET /api/expenses': json(200, []),
    })
    await renderDetail(2)

    const label = await screen.findByLabelText('Libellé')
    await userEvent.clear(label)
    await userEvent.type(label, 'Train Paris – Marseille')
    await userEvent.clear(screen.getByLabelText('Montant (€)'))
    await userEvent.type(screen.getByLabelText('Montant (€)'), '120')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer les modifications' }))

    expect(await screen.findByRole('heading', { name: 'Liste des notes' })).toBeInTheDocument()
    const patch = fetchMock.mock.calls.find(([, init]) => init?.method === 'PATCH')
    expect(JSON.parse(String(patch?.[1]?.body))).toEqual({ label: 'Train Paris – Marseille', amountCents: 12000, date: '2026-10-06', categorySlug: 'transport' })
  })

  it('C7 – un refus du serveur à la soumission (409) est affiché et l\'utilisateur reste sur la note', async () => {
    stubApi({
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/expenses/2': json(200, draftExpense),
      'GET /api/categories': json(200, categories),
      'POST /api/expenses/2/submit': json(409, { error: { code: 'conflict', message: 'Seul un brouillon peut être soumis.' } }),
    })
    await renderDetail(2)

    await userEvent.click(await screen.findByRole('button', { name: 'Soumettre la note' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Seul un brouillon peut être soumis.')
    expect(screen.getByRole('button', { name: 'Soumettre la note' })).toBeEnabled()
  })
})
