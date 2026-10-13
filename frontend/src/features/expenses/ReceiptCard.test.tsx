import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { json, stubApi } from '../../test/api'
import { categories, draftExpense, employee, manager, teamSubmittedExpense } from '../../test/fixtures'
import { renderAsync } from '../../test/render'
import { useSessionStore } from '../auth/sessionStore'
import { ExpenseDetailRoute } from './ExpenseDetailPage'
import { useExpensesStore } from './expensesStore'

const receipt = { name: 'facture-hotel.pdf', mimeType: 'application/pdf', sizeBytes: 350_400 }
const hotelDraft = {
  ...draftExpense,
  category: { slug: 'hebergement', label: 'Hébergement' },
  policy: { limitCents: 15000, overLimitByCents: null, receiptRequired: true, receiptMissing: true },
}
const hotelDraftWithReceipt = { ...hotelDraft, receipt, policy: { ...hotelDraft.policy, receiptMissing: false } }

function renderAt(path: string, user: typeof employee) {
  useSessionStore.setState({ session: { status: 'authenticated', user } })
  return renderAsync(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="notes/:id" element={<ExpenseDetailRoute back={{ to: '/notes', label: 'Mes notes' }} />} />
        <Route path="equipe/:id" element={<ExpenseDetailRoute back={{ to: '/equipe', label: 'Équipe' }} />} />
      </Routes>
    </MemoryRouter>,
  )
}

function card() {
  return within(screen.getByRole('heading', { name: 'Justificatif' }).closest('section') ?? document.body)
}

const pdf = (size = 1200) => new File([new Uint8Array(size)], 'facture-hotel.pdf', { type: 'application/pdf' })

afterEach(() => {
  vi.unstubAllGlobals()
  useExpensesStore.setState(useExpensesStore.getInitialState(), true)
})

describe('Justificatif sur le détail', () => {
  it('C5 – le responsable voit le justificatif et peut l\'ouvrir dans un nouvel onglet, sans pouvoir le changer', async () => {
    stubApi({
      'GET /api/expenses/1': json(200, { ...teamSubmittedExpense, receipt }),
      'GET /api/expenses/1/events': json(200, []),
      'GET /api/categories': json(200, categories),
    })
    await renderAt('/equipe/1', manager)

    await screen.findByRole('heading', { name: 'Justificatif' })
    expect(card().getByText('facture-hotel.pdf')).toBeInTheDocument()
    expect(card().getByText(/PDF · 350\sKo/)).toBeInTheDocument()
    const open = card().getByRole('link', { name: 'Ouvrir le justificatif facture-hotel.pdf (nouvel onglet)' })
    expect(open).toHaveAttribute('href', '/api/expenses/1/receipt')
    expect(open).toHaveAttribute('target', '_blank')
    expect(open).toHaveAttribute('rel', expect.stringContaining('noopener'))
    expect(card().queryByRole('button')).not.toBeInTheDocument()
    expect(card().queryByLabelText(/Fichier|Remplacer/)).not.toBeInTheDocument()
  })

  it('C5 – sans justificatif, l\'indique, avec l\'obligation de la catégorie', async () => {
    stubApi({
      'GET /api/expenses/2': json(200, hotelDraft),
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/categories': json(200, categories),
    })
    await renderAt('/notes/2', employee)

    await screen.findByRole('heading', { name: 'Justificatif' })
    expect(card().getByText('Aucun justificatif. Il est obligatoire pour la catégorie Hébergement.')).toBeInTheDocument()
  })

  it('C1 – le propriétaire joint un fichier : la carte montre le justificatif renvoyé par le serveur', async () => {
    const fetchMock = stubApi({
      'GET /api/expenses/2': json(200, hotelDraft),
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/categories': json(200, categories),
      'POST /api/expenses/2/receipt': json(200, hotelDraftWithReceipt),
      'GET /api/expenses': json(200, [hotelDraftWithReceipt]),
    })
    await renderAt('/notes/2', employee)
    await screen.findByRole('heading', { name: 'Justificatif' })

    await userEvent.upload(card().getByLabelText('Fichier'), pdf())
    await userEvent.click(card().getByRole('button', { name: 'Joindre' }))

    expect(await card().findByText('facture-hotel.pdf')).toBeInTheDocument()
    expect(card().getByRole('button', { name: 'Remplacer' })).toBeInTheDocument()
    const post = fetchMock.mock.calls.find(([url, init]) => url === '/api/expenses/2/receipt' && init?.method === 'POST')
    const body = post?.[1]?.body
    expect(body instanceof FormData && body.get('file')).toBeInstanceOf(File)
  })

  it('C4 – un fichier de plus de 5 Mo est signalé sous le champ, sans rien envoyer', async () => {
    const fetchMock = stubApi({
      'GET /api/expenses/2': json(200, hotelDraft),
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/categories': json(200, categories),
    })
    await renderAt('/notes/2', employee)
    await screen.findByRole('heading', { name: 'Justificatif' })

    await userEvent.upload(card().getByLabelText('Fichier'), pdf(5_000_001))
    await userEvent.click(card().getByRole('button', { name: 'Joindre' }))

    expect(await card().findByText('Le fichier dépasse 5 Mo.')).toBeInTheDocument()
    expect(card().getByLabelText('Fichier')).toHaveAttribute('aria-invalid', 'true')
    expect(fetchMock.mock.calls.some(([url]) => url === '/api/expenses/2/receipt')).toBe(false)
  })

  it('C4 – un type non accepté est signalé sans rien envoyer', async () => {
    const fetchMock = stubApi({
      'GET /api/expenses/2': json(200, hotelDraft),
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/categories': json(200, categories),
    })
    await renderAt('/notes/2', employee)
    await screen.findByRole('heading', { name: 'Justificatif' })

    // Le navigateur filtre selon accept, mais l'utilisateur peut choisir « Tous les fichiers »
    const user = userEvent.setup({ applyAccept: false })
    await user.upload(card().getByLabelText('Fichier'), new File(['GIF89a'], 'photo.gif', { type: 'image/gif' }))
    await user.click(card().getByRole('button', { name: 'Joindre' }))

    expect(await card().findByText('Formats acceptés : JPEG, PNG ou PDF.')).toBeInTheDocument()
    expect(fetchMock.mock.calls.some(([url]) => url === '/api/expenses/2/receipt')).toBe(false)
  })

  it('C2 – un refus du serveur sur le fichier est affiché sous le champ', async () => {
    stubApi({
      'GET /api/expenses/2': json(200, hotelDraft),
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/categories': json(200, categories),
      'POST /api/expenses/2/receipt': json(400, {
        error: { code: 'validation_failed', message: 'Données invalides.', fields: { file: 'Formats acceptés : JPEG, PNG ou PDF.' } },
      }),
    })
    await renderAt('/notes/2', employee)
    await screen.findByRole('heading', { name: 'Justificatif' })

    await userEvent.upload(card().getByLabelText('Fichier'), pdf())
    await userEvent.click(card().getByRole('button', { name: 'Joindre' }))

    expect(await card().findByText('Formats acceptés : JPEG, PNG ou PDF.')).toBeInTheDocument()
    expect(card().getByText(/Aucun justificatif/)).toBeInTheDocument()
  })

  it('C6 – le propriétaire retire le justificatif', async () => {
    stubApi({
      'GET /api/expenses/2': json(200, hotelDraftWithReceipt),
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/categories': json(200, categories),
      'DELETE /api/expenses/2/receipt': json(200, hotelDraft),
      'GET /api/expenses': json(200, [hotelDraft]),
    })
    await renderAt('/notes/2', employee)
    await screen.findByRole('heading', { name: 'Justificatif' })

    await userEvent.click(card().getByRole('button', { name: 'Retirer' }))

    expect(await card().findByText(/Aucun justificatif/)).toBeInTheDocument()
    expect(card().getByRole('button', { name: 'Joindre' })).toBeInTheDocument()
  })

  it('C7 – un refus du serveur au dépôt (409) est affiché, sans changer le justificatif', async () => {
    stubApi({
      'GET /api/expenses/2': json(200, hotelDraft),
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/categories': json(200, categories),
      'POST /api/expenses/2/receipt': json(409, { error: { code: 'conflict', message: 'Cette note n\'est plus modifiable.' } }),
    })
    await renderAt('/notes/2', employee)
    await screen.findByRole('heading', { name: 'Justificatif' })

    await userEvent.upload(card().getByLabelText('Fichier'), pdf())
    await userEvent.click(card().getByRole('button', { name: 'Joindre' }))

    expect(await card().findByRole('alert')).toHaveTextContent('Cette note n\'est plus modifiable.')
    expect(card().getByLabelText('Fichier')).not.toHaveAttribute('aria-invalid')
    expect(card().getByText(/Aucun justificatif/)).toBeInTheDocument()
  })

  it('C7 – un refus du serveur au retrait (409) est affiché', async () => {
    stubApi({
      'GET /api/expenses/2': json(200, hotelDraftWithReceipt),
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/categories': json(200, categories),
      'DELETE /api/expenses/2/receipt': json(409, { error: { code: 'conflict', message: 'Cette note n\'est plus modifiable.' } }),
    })
    await renderAt('/notes/2', employee)
    await screen.findByRole('heading', { name: 'Justificatif' })

    await userEvent.click(card().getByRole('button', { name: 'Retirer' }))

    expect(await card().findByRole('alert')).toHaveTextContent('Cette note n\'est plus modifiable.')
    expect(card().getByText('facture-hotel.pdf')).toBeInTheDocument()
  })
})

describe('Soumission et justificatif obligatoire', () => {
  it('C10 – sans le justificatif obligatoire, le bouton de soumission est désactivé et expliqué', async () => {
    stubApi({
      'GET /api/expenses/2': json(200, hotelDraft),
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/categories': json(200, categories),
    })
    await renderAt('/notes/2', employee)

    const submit = await screen.findByRole('button', { name: 'Soumettre la note' })
    expect(submit).toBeDisabled()
    expect(submit).toHaveAccessibleDescription('Joignez le justificatif, obligatoire pour la catégorie Hébergement, avant de soumettre la note.')
  })

  it('C10 – une fois le justificatif joint, la note peut être soumise', async () => {
    stubApi({
      'GET /api/expenses/2': json(200, hotelDraft),
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/categories': json(200, categories),
      'POST /api/expenses/2/receipt': json(200, hotelDraftWithReceipt),
      'GET /api/expenses': json(200, [hotelDraftWithReceipt]),
    })
    await renderAt('/notes/2', employee)
    await screen.findByRole('heading', { name: 'Justificatif' })

    await userEvent.upload(card().getByLabelText('Fichier'), pdf())
    await userEvent.click(card().getByRole('button', { name: 'Joindre' }))

    await card().findByText('facture-hotel.pdf')
    const submit = screen.getByRole('button', { name: 'Soumettre la note' })
    expect(submit).toBeEnabled()
    expect(submit).not.toHaveAccessibleDescription()
  })

  it('C10 – une catégorie sans justificatif obligatoire ne bloque pas la soumission', async () => {
    stubApi({
      'GET /api/expenses/2': json(200, draftExpense),
      'GET /api/expenses/2/events': json(200, []),
      'GET /api/categories': json(200, categories),
    })
    await renderAt('/notes/2', employee)

    expect(await screen.findByRole('button', { name: 'Soumettre la note' })).toBeEnabled()
  })
})

