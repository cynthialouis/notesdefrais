import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../../api/client'
import { canAccess, useSessionStore, type CurrentUser } from './sessionStore'

const sophie: CurrentUser = { id: 4, name: 'Sophie', email: 'sophie@demo.test', role: 'employee', accessibleRoles: ['employee'] }
const claire: CurrentUser = { id: 2, name: 'Claire', email: 'claire@demo.test', role: 'manager', accessibleRoles: ['manager', 'employee'] }

const fetchMock = vi.fn<typeof fetch>()

function jsonResponse(status: number, body?: unknown): Response {
  return new Response(body === undefined ? null : JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}
const unauthorized = () => jsonResponse(401, { error: { code: 'unauthorized', message: 'Authentification requise.' } })

beforeEach(() => {
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => {
  fetchMock.mockReset()
  vi.unstubAllGlobals()
  useSessionStore.setState(useSessionStore.getInitialState(), true)
})

describe('sessionStore', () => {
  it('commence en chargement', () => {
    expect(useSessionStore.getState().session).toEqual({ status: 'loading' })
  })

  it('C3 – fetchMe reconnaît une session ouverte côté serveur', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, sophie))

    await useSessionStore.getState().fetchMe()

    expect(fetchMock).toHaveBeenCalledWith('/api/me', expect.anything())
    expect(useSessionStore.getState().session).toEqual({ status: 'authenticated', user: sophie })
  })

  it('C3 – fetchMe sans session passe en anonyme', async () => {
    fetchMock.mockResolvedValueOnce(unauthorized())

    await useSessionStore.getState().fetchMe()

    expect(useSessionStore.getState().session).toEqual({ status: 'anonymous' })
  })

  it('C2 – login ouvre la session avec le compte choisi', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, sophie))

    await useSessionStore.getState().login(4)

    const [url, init] = fetchMock.mock.calls[0] ?? []
    expect(url).toBe('/api/auth/login')
    expect(init?.method).toBe('POST')
    expect(init?.body).toBe(JSON.stringify({ userId: 4 }))
    expect(useSessionStore.getState().session).toEqual({ status: 'authenticated', user: sophie })
  })

  it('C11 – login refusé lève une ApiError et ne connecte personne', async () => {
    useSessionStore.setState({ session: { status: 'anonymous' } })
    fetchMock.mockResolvedValueOnce(unauthorized())

    await expect(useSessionStore.getState().login(999)).rejects.toBeInstanceOf(ApiError)
    expect(useSessionStore.getState().session).toEqual({ status: 'anonymous' })
  })

  it('C12 – une nouvelle connexion remplace la session', async () => {
    useSessionStore.setState({ session: { status: 'authenticated', user: sophie } })
    fetchMock.mockResolvedValueOnce(jsonResponse(200, claire))

    await useSessionStore.getState().login(2)

    expect(useSessionStore.getState().session).toEqual({ status: 'authenticated', user: claire })
  })

  it('C4 – logout ferme la session sans laisser d\'utilisateur', async () => {
    useSessionStore.setState({ session: { status: 'authenticated', user: sophie } })
    fetchMock.mockResolvedValueOnce(new Response(null, { status: 204 }))

    await useSessionStore.getState().logout()

    expect(useSessionStore.getState().session).toEqual({ status: 'anonymous' })
  })

  it('C4 – logout sur une session déjà fermée côté serveur passe quand même en anonyme', async () => {
    useSessionStore.setState({ session: { status: 'authenticated', user: sophie } })
    fetchMock.mockResolvedValueOnce(unauthorized())

    await useSessionStore.getState().logout()

    expect(useSessionStore.getState().session).toEqual({ status: 'anonymous' })
  })
})

describe('canAccess', () => {
  it('C9 – suit les rôles accessibles renvoyés par le serveur', () => {
    const session = { status: 'authenticated', user: claire } as const
    expect(canAccess(session, 'employee')).toBe(true)
    expect(canAccess(session, 'manager')).toBe(true)
    expect(canAccess(session, 'admin')).toBe(false)
  })

  it('refuse tout accès sans session', () => {
    expect(canAccess({ status: 'anonymous' }, 'employee')).toBe(false)
    expect(canAccess({ status: 'loading' }, 'employee')).toBe(false)
  })
})
