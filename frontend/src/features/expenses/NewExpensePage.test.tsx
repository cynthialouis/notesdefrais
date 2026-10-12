import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { json, stubApi } from '../../test/api'
import { categories, draftExpense } from '../../test/fixtures'
import { renderAsync } from '../../test/render'
import { useExpensesStore } from './expensesStore'
import { NewExpensePage } from './NewExpensePage'

function renderPage() {
  return renderAsync(
    <MemoryRouter initialEntries={['/notes/nouvelle']}>
      <Routes>
        <Route path="notes/nouvelle" element={<NewExpensePage />} />
        <Route path="notes" element={<h1>Liste des notes</h1>} />
      </Routes>
    </MemoryRouter>,
  )
}

async function fill({ label, amount, date, category }: { label: string; amount: string; date: string; category: string }) {
  await userEvent.type(screen.getByLabelText('Libellé'), label)
  await userEvent.type(screen.getByLabelText('Montant (€)'), amount)
  await userEvent.type(screen.getByLabelText('Date'), date)
  await userEvent.selectOptions(screen.getByLabelText('Catégorie'), category)
}

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date(2026, 9, 11, 12, 0, 0))
})

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
  useExpensesStore.setState(useExpensesStore.getInitialState(), true)
})

describe('NewExpensePage', () => {
  it('C10 – propose les catégories du serveur', async () => {
    stubApi({ 'GET /api/categories': json(200, categories) })
    await renderPage()

    const select = await screen.findByLabelText('Catégorie')
    expect([...select.querySelectorAll('option')].map((option) => option.textContent)).toEqual(['Choisir une catégorie', 'Repas', 'Transport', 'Hébergement'])
  })

  it('C4 – affiche chaque erreur sous son champ, sans rien envoyer', async () => {
    const fetchMock = stubApi({ 'GET /api/categories': json(200, categories) })
    await renderPage()
    await screen.findByLabelText('Catégorie')

    await fill({ label: 'a', amount: '12,505', date: '2026-10-12', category: 'Repas' })
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer le brouillon' }))

    expect(screen.getByLabelText('Libellé')).toHaveAccessibleDescription('Le libellé doit faire au moins 2 caractères.')
    expect(screen.getByLabelText('Montant (€)')).toHaveAccessibleDescription(/au plus deux décimales/)
    expect(screen.getByLabelText('Date')).toHaveAccessibleDescription('La date ne peut pas être dans le futur.')
    expect(screen.getByLabelText('Montant (€)')).toHaveValue('12,505') // la saisie est conservée
    expect(fetchMock).toHaveBeenCalledTimes(1) // seulement le chargement des catégories
  })

  it('C1/C2 – enregistre le brouillon avec le montant en centimes puis revient à la liste', async () => {
    const fetchMock = stubApi({
      'GET /api/categories': json(200, categories),
      'POST /api/expenses': json(201, draftExpense),
      'GET /api/expenses': json(200, [draftExpense]),
    })
    await renderPage()
    await screen.findByLabelText('Catégorie')

    await fill({ label: '  Train Paris – Lyon  ', amount: '89', date: '2026-10-06', category: 'Transport' })
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer le brouillon' }))

    expect(await screen.findByRole('heading', { name: 'Liste des notes' })).toBeInTheDocument()
    const post = fetchMock.mock.calls.find(([, init]) => init?.method === 'POST')
    expect(JSON.parse(String(post?.[1]?.body))).toEqual({ label: 'Train Paris – Lyon', amountCents: 8900, date: '2026-10-06', categorySlug: 'transport' })
  })

  it('C3 – une erreur du serveur s\'affiche sous le champ concerné', async () => {
    stubApi({
      'GET /api/categories': json(200, categories),
      'POST /api/expenses': json(400, { error: { code: 'validation_failed', message: 'Données invalides.', fields: { date: 'La date ne peut pas être dans le futur.' } } }),
    })
    await renderPage()
    await screen.findByLabelText('Catégorie')

    await fill({ label: 'Taxi', amount: '20', date: '2026-10-11', category: 'Transport' })
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer le brouillon' }))

    expect(await screen.findByText('La date ne peut pas être dans le futur.')).toBeInTheDocument()
    expect(screen.getByLabelText('Date')).toHaveAccessibleDescription('La date ne peut pas être dans le futur.')
  })
})
