import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../api/client'
import { acceptedLeave, cancelledLeave, jsonResponse, pendingLeave, refusedLeave } from '../../test/fixtures'
import { useLeavesStore } from './leavesStore'

const fetchMock = vi.fn<typeof fetch>()

beforeEach(() => {
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => {
  fetchMock.mockReset()
  vi.unstubAllGlobals()
  useLeavesStore.setState(useLeavesStore.getInitialState(), true)
})

const ids = (list: ReturnType<typeof useLeavesStore.getState>['mine']) => (list.status === 'ready' ? list.leaves.map((l) => l.id) : list)

describe('leavesStore', () => {
  it('C16 – charge ses demandes dans l\'ordre du serveur, dans tous les états', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, [cancelledLeave, acceptedLeave, pendingLeave, refusedLeave]))

    await useLeavesStore.getState().fetchMine()

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/me/leaves')
    expect(ids(useLeavesStore.getState().mine)).toEqual([13, 11, 10, 12])
  })

  it.each([
    ['C15', 'fetchAccepted', 'accepted', '/api/leaves'],
    ['C6', 'fetchTeam', 'team', '/api/team/leaves'],
    ['C18', 'fetchAllPending', 'allPending', '/api/leaves/pending'],
  ] as const)('%s – %s lit %s sur sa route', async (_criterion, action, list, url) => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, [acceptedLeave]))

    await useLeavesStore.getState()[action]()

    expect(fetchMock.mock.calls[0]?.[0]).toBe(url)
    expect(ids(useLeavesStore.getState()[list])).toEqual([11])
  })

  it.each([
    ['refusée sans motif', { ...refusedLeave, refusalReason: null }],
    ['annulée sans motif', { ...cancelledLeave, cancellationReason: null }],
    ['acceptée sans auteur de la décision', { ...acceptedLeave, decidedBy: null }],
    ['en attente avec un motif de refus', { ...pendingLeave, refusalReason: 'Motif' }],
  ])('refuse une demande %s (union discriminée)', async (_case, leave) => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, [leave]))

    await useLeavesStore.getState().fetchMine()

    expect(useLeavesStore.getState().mine).toEqual({ status: 'error', message: 'Réponse inattendue du serveur.' })
  })

  it('accepte une demande annulée alors qu\'elle était en attente, donc sans décision', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, [{ ...cancelledLeave, decidedAt: null, decidedBy: null }]))

    await useLeavesStore.getState().fetchMine()

    expect(ids(useLeavesStore.getState().mine)).toEqual([13])
  })

  it('refuse une demande à qui manque un champ nullable, toujours envoyé par le serveur', async () => {
    const { cancelledAt: _omitted, ...withoutCancelledAt } = pendingLeave
    fetchMock.mockResolvedValueOnce(jsonResponse(200, [withoutCancelledAt]))

    await useLeavesStore.getState().fetchMine()

    expect(useLeavesStore.getState().mine).toEqual({ status: 'error', message: 'Réponse inattendue du serveur.' })
  })

  it('signale une liste impossible à charger', async () => {
    fetchMock.mockRejectedValueOnce(new TypeError('Failed to fetch'))

    await useLeavesStore.getState().fetchMine()

    expect(useLeavesStore.getState().mine).toEqual({ status: 'error', message: 'Serveur injoignable.' })
  })

  it('C1 – demande : envoie les dates, renvoie la demande et relit seulement les listes déjà chargées', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, []))
    await useLeavesStore.getState().fetchMine()
    fetchMock.mockReset()
    fetchMock.mockResolvedValueOnce(jsonResponse(201, pendingLeave)).mockResolvedValueOnce(jsonResponse(200, [pendingLeave]))

    const created = await useLeavesStore.getState().request({ startDate: '2026-11-03', endDate: '2026-11-07' })

    expect(created).toEqual(pendingLeave)
    const [url, init] = fetchMock.mock.calls[0] ?? []
    expect(url).toBe('/api/leaves')
    expect(init?.method).toBe('POST')
    expect(JSON.parse(String(init?.body))).toEqual({ startDate: '2026-11-03', endDate: '2026-11-07' })
    expect(fetchMock.mock.calls.map((call) => call[0])).toEqual(['/api/leaves', '/api/me/leaves'])
    expect(ids(useLeavesStore.getState().mine)).toEqual([10])
  })

  it('C7 – une acceptation relit les demandes à traiter et les congés à venir', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, [pendingLeave])).mockResolvedValueOnce(jsonResponse(200, []))
    await useLeavesStore.getState().fetchTeam()
    await useLeavesStore.getState().fetchAccepted()
    fetchMock.mockReset()
    fetchMock
      .mockResolvedValueOnce(jsonResponse(200, { ...acceptedLeave, id: 10 }))
      .mockResolvedValueOnce(jsonResponse(200, [{ ...acceptedLeave, id: 10 }]))
      .mockResolvedValueOnce(jsonResponse(200, []))

    await useLeavesStore.getState().accept(10)

    const [url, init] = fetchMock.mock.calls[0] ?? []
    expect(url).toBe('/api/leaves/10/accept')
    expect(init?.method).toBe('POST')
    expect(init?.body).toBeUndefined()
    expect(fetchMock.mock.calls.slice(1).map((call) => call[0]).sort()).toEqual(['/api/leaves', '/api/team/leaves'])
  })

  it.each([
    ['C8', 'refuse', '/api/leaves/10/refuse'],
    ['C13', 'cancel', '/api/leaves/10/cancel'],
  ] as const)('%s – %s envoie le motif dans le corps', async (_criterion, action, url) => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, action === 'refuse' ? { ...refusedLeave, id: 10 } : { ...cancelledLeave, id: 10 }))

    await useLeavesStore.getState()[action](10, 'Motif valable')

    const [calledUrl, init] = fetchMock.mock.calls[0] ?? []
    expect(calledUrl).toBe(url)
    expect(init?.method).toBe('POST')
    expect(JSON.parse(String(init?.body))).toEqual({ reason: 'Motif valable' })
  })

  it('C11 – retrait', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { ...pendingLeave, status: 'withdrawn', withdrawable: false }))

    const leave = await useLeavesStore.getState().withdraw(10)

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/leaves/10/withdraw')
    expect(leave.status).toBe('withdrawn')
  })

  it('C4 – un refus du serveur (409) remonte une ApiError avec son message et ne relit rien', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, []))
    await useLeavesStore.getState().fetchMine()
    fetchMock.mockReset()
    fetchMock.mockResolvedValueOnce(jsonResponse(409, { error: { code: 'conflict', message: 'Cette période chevauche une autre de vos demandes, en attente ou acceptée.' } }))

    const error = await useLeavesStore.getState().request({ startDate: '2026-11-03', endDate: '2026-11-07' }).catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect(error instanceof ApiError && error.message).toBe('Cette période chevauche une autre de vos demandes, en attente ou acceptée.')
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })
})
