import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { json, stubApi } from '../../test/api'
import { draftExpense, overLimitExpense, submittedExpense } from '../../test/fixtures'
import { renderAsync } from '../../test/render'
import { useExpensesStore } from './expensesStore'
import { NotesPage } from './NotesPage'

function renderNotes() {
  return renderAsync(
    <MemoryRouter>
      <NotesPage />
    </MemoryRouter>,
  )
}

function rows(): string[][] {
  const [, ...bodyRows] = screen.getAllByRole('row')
  return bodyRows.map((row) => within(row).getAllByRole('cell').map((cell) => (cell.textContent ?? '').replace(/\s/g, ' ')))
}

afterEach(() => {
  vi.unstubAllGlobals()
  useExpensesStore.setState(useExpensesStore.getInitialState(), true)
})

describe('NotesPage', () => {
  it('C8 – liste les notes avec libellé, date, catégorie, montant en euros et état', async () => {
    stubApi({ 'GET /api/expenses': json(200, [draftExpense, submittedExpense]) })
    await renderNotes()

    expect(await screen.findByRole('table')).toBeInTheDocument()
    expect(rows()).toEqual([
      ['Train Paris – Lyon', '06/10/2026', 'Transport', '89,00 €', 'Brouillon'],
      ['Déjeuner client', '29/09/2026', 'Repas', '42,50 €', 'Soumise'],
    ])
    expect(screen.getByRole('link', { name: 'Train Paris – Lyon' })).toHaveAttribute('href', '/notes/2')
  })

  it('C9 – le filtre n\'affiche que les notes dans l\'état choisi, « Tous » les réaffiche', async () => {
    stubApi({ 'GET /api/expenses': json(200, [draftExpense, submittedExpense]) })
    await renderNotes()
    await screen.findByRole('table')

    await userEvent.selectOptions(screen.getByLabelText('État'), 'Soumise')
    expect(rows().map((row) => row[0])).toEqual(['Déjeuner client'])

    await userEvent.selectOptions(screen.getByLabelText('État'), 'Tous')
    expect(rows().map((row) => row[0])).toEqual(['Train Paris – Lyon', 'Déjeuner client'])
  })

  it('C9 – aucun résultat dans l\'état choisi : message explicite', async () => {
    stubApi({ 'GET /api/expenses': json(200, [draftExpense]) })
    await renderNotes()
    await screen.findByRole('table')

    await userEvent.selectOptions(screen.getByLabelText('État'), 'Validée')

    expect(screen.getByText('Aucune note dans cet état.')).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('aucune note : message explicite et lien de création', async () => {
    stubApi({ 'GET /api/expenses': json(200, []) })
    await renderNotes()

    expect(await screen.findByText('Vous n\'avez encore aucune note.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Nouvelle note' })).toHaveAttribute('href', '/notes/nouvelle')
  })

  it('signale une liste impossible à charger', async () => {
    stubApi({ 'GET /api/expenses': () => Promise.reject(new TypeError('Failed to fetch')) })
    await renderNotes()

    expect(await screen.findByRole('alert')).toHaveTextContent('Serveur injoignable.')
  })
})

describe('NotesPage – politique', () => {
  it('C6 – signale une note hors politique à côté de son état, et seulement elle', async () => {
    stubApi({ 'GET /api/expenses': json(200, [overLimitExpense, draftExpense]) })
    await renderNotes()
    await screen.findByRole('table')

    expect(screen.getAllByText('Hors politique')).toHaveLength(1)
    expect(rows()[0]?.[4]).toBe('SoumiseHors politique')
  })
})
