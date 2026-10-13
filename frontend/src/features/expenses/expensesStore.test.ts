import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../api/client'
import { jsonResponse } from '../../test/fixtures'
import { useExpensesStore, type ExpenseInput } from './expensesStore'

const draft = {
  id: 2, owner: { id: 4, name: 'Léa' }, decidable: false, policy: { limitCents: null, overLimitByCents: null, receiptRequired: false, receiptMissing: false }, receipt: null, label: 'Train', amountCents: 8900, date: '2026-10-06', category: { slug: 'transport', label: 'Transport' },
  status: 'draft', createdAt: '2026-10-06T10:00:00+02:00', submittedAt: null, decidedAt: null, decidedBy: null, rejectionReason: null, editable: true,
}
const submitted = { ...draft, id: 1, label: 'Déjeuner', status: 'submitted', submittedAt: '2026-10-07T09:00:00+02:00', editable: false }
const input: ExpenseInput = { label: 'Train', amountCents: 8900, date: '2026-10-06', categorySlug: 'transport' }

const fetchMock = vi.fn<typeof fetch>()

beforeEach(() => {
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => {
  fetchMock.mockReset()
  vi.unstubAllGlobals()
  useExpensesStore.setState(useExpensesStore.getInitialState(), true)
})

describe('expensesStore', () => {
  it('C8 – charge les notes dans l\'ordre du serveur', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, [draft, submitted]))

    await useExpensesStore.getState().fetchExpenses()

    const list = useExpensesStore.getState().list
    expect(list.status).toBe('ready')
    expect(list.status === 'ready' && list.expenses.map((e) => e.id)).toEqual([2, 1])
  })

  it('refuse une note soumise sans date de soumission (union discriminée)', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, [{ ...submitted, submittedAt: null }]))

    await useExpensesStore.getState().fetchExpenses()

    expect(useExpensesStore.getState().list).toEqual({ status: 'error', message: 'Réponse inattendue du serveur.' })
  })

  it('refuse une note à qui manque un champ nullable, toujours envoyé par le serveur', async () => {
    const { decidedAt: _omitted, ...withoutDecidedAt } = draft
    fetchMock.mockResolvedValueOnce(jsonResponse(200, [withoutDecidedAt]))

    await useExpensesStore.getState().fetchExpenses()

    expect(useExpensesStore.getState().list).toEqual({ status: 'error', message: 'Réponse inattendue du serveur.' })
  })

  it('signale une liste impossible à charger', async () => {
    fetchMock.mockRejectedValueOnce(new TypeError('Failed to fetch'))

    await useExpensesStore.getState().fetchExpenses()

    expect(useExpensesStore.getState().list).toEqual({ status: 'error', message: 'Serveur injoignable.' })
  })

  it('C1 – création : envoie le corps, renvoie la note et relit la liste', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(201, draft)).mockResolvedValueOnce(jsonResponse(200, [draft]))

    const created = await useExpensesStore.getState().create(input)

    expect(created).toEqual(draft)
    const [url, init] = fetchMock.mock.calls[0] ?? []
    expect(url).toBe('/api/expenses')
    expect(init?.method).toBe('POST')
    expect(JSON.parse(String(init?.body))).toEqual(input)
    expect(fetchMock.mock.calls[1]?.[0]).toBe('/api/expenses')
  })

  it('C5 – modification en PATCH avec tous les champs', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, draft)).mockResolvedValueOnce(jsonResponse(200, [draft]))

    await useExpensesStore.getState().update(2, input)

    const [url, init] = fetchMock.mock.calls[0] ?? []
    expect(url).toBe('/api/expenses/2')
    expect(init?.method).toBe('PATCH')
  })

  it('C6 – soumission', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { ...draft, status: 'submitted', submittedAt: '2026-10-11T10:00:00+02:00', editable: false }))
      .mockResolvedValueOnce(jsonResponse(200, []))

    const result = await useExpensesStore.getState().submit(2)

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/expenses/2/submit')
    expect(result.status).toBe('submitted')
  })

  it('C7 – une soumission refusée (409) remonte une ApiError et ne relit pas la liste', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(409, { error: { code: 'conflict', message: 'Action impossible.' } }))

    const error = await useExpensesStore.getState().submit(1).catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({ status: 409 })
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('C11 – suppression en DELETE puis relecture de la liste', async () => {
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 204 })).mockResolvedValueOnce(jsonResponse(200, []))

    await useExpensesStore.getState().remove(2)

    const [url, init] = fetchMock.mock.calls[0] ?? []
    expect(url).toBe('/api/expenses/2')
    expect(init?.method).toBe('DELETE')
    expect(useExpensesStore.getState().list).toEqual({ status: 'ready', expenses: [] })
  })
})

describe('expensesStore – décisions', () => {
  const submittedByLea = { ...submitted, decidable: true }
  const approved = { ...submittedByLea, status: 'approved', decidedAt: '2026-10-08T09:00:00+02:00', decidedBy: { id: 2, name: 'Claire' }, decidable: false }

  it('C1 – charge la liste à traiter dans l\'ordre du serveur', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, [submittedByLea]))

    await useExpensesStore.getState().fetchTeamExpenses()

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/team/expenses')
    expect(useExpensesStore.getState().teamList).toEqual({ status: 'ready', expenses: [submittedByLea] })
  })

  it('C3/C7 – validation puis relecture de la liste à traiter', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, approved)).mockResolvedValueOnce(jsonResponse(200, []))

    const result = await useExpensesStore.getState().approve(1)

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/expenses/1/approve')
    expect(result.status).toBe('approved')
    expect(fetchMock.mock.calls[1]?.[0]).toBe('/api/team/expenses')
    expect(useExpensesStore.getState().teamList).toEqual({ status: 'ready', expenses: [] })
  })

  it('C4 – rejet avec le motif dans le corps', async () => {
    const rejected = { ...approved, status: 'rejected', rejectionReason: 'Reçu illisible' }
    fetchMock.mockResolvedValueOnce(jsonResponse(200, rejected)).mockResolvedValueOnce(jsonResponse(200, []))

    await useExpensesStore.getState().reject(1, 'Reçu illisible')

    const [url, init] = fetchMock.mock.calls[0] ?? []
    expect(url).toBe('/api/expenses/1/reject')
    expect(JSON.parse(String(init?.body))).toEqual({ reason: 'Reçu illisible' })
  })

  it('refuse une note rejetée sans motif (union discriminée)', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, [{ ...approved, status: 'rejected', rejectionReason: null }]))

    await useExpensesStore.getState().fetchTeamExpenses()

    expect(useExpensesStore.getState().teamList).toEqual({ status: 'error', message: 'Réponse inattendue du serveur.' })
  })

  it('C11 – lit l\'historique d\'une note', async () => {
    const events = [
      { type: 'created', actor: { id: 4, name: 'Léa' }, comment: null, createdAt: '2026-10-01T10:00:00+00:00' },
      { type: 'rejected', actor: { id: 2, name: 'Claire' }, comment: 'Reçu illisible', createdAt: '2026-10-02T10:00:00+00:00' },
    ]
    fetchMock.mockResolvedValueOnce(jsonResponse(200, events))

    expect(await useExpensesStore.getState().fetchEvents(1)).toEqual(events)
    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/expenses/1/events')
  })
})

describe('expensesStore – justificatif', () => {
  const withReceipt = { ...draft, receipt: { name: 'recu.pdf', mimeType: 'application/pdf', sizeBytes: 1200 } }

  it('C1 – envoie le fichier en multipart, sans forcer le JSON, et renvoie la note à jour', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, withReceipt)).mockResolvedValueOnce(jsonResponse(200, [withReceipt]))
    const file = new File(['%PDF-1.4'], 'recu.pdf', { type: 'application/pdf' })

    const updated = await useExpensesStore.getState().uploadReceipt(2, file)

    expect(updated.receipt).toEqual({ name: 'recu.pdf', mimeType: 'application/pdf', sizeBytes: 1200 })
    const [url, init] = fetchMock.mock.calls[0] ?? []
    expect(url).toBe('/api/expenses/2/receipt')
    expect(init?.method).toBe('POST')
    const body = init?.body
    expect(body instanceof FormData && body.get('file')).toBe(file)
    expect(new Headers(init?.headers).has('Content-Type')).toBe(false)
  })

  it('C6 – retire le justificatif', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, draft)).mockResolvedValueOnce(jsonResponse(200, [draft]))

    const updated = await useExpensesStore.getState().removeReceipt(2)

    expect(updated.receipt).toBeNull()
    const [url, init] = fetchMock.mock.calls[0] ?? []
    expect(url).toBe('/api/expenses/2/receipt')
    expect(init?.method).toBe('DELETE')
  })
})
