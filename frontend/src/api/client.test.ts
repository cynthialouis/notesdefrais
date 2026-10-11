import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { jsonResponse } from '../test/fixtures'
import { ApiError, apiFetch } from './client'

const fetchMock = vi.fn<typeof fetch>()

beforeEach(() => {
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => {
  fetchMock.mockReset()
  vi.unstubAllGlobals()
})

function sentHeaders(): Headers {
  return new Headers(fetchMock.mock.calls[0]?.[1]?.headers)
}

describe('apiFetch', () => {
  it('envoie le corps en JSON et conserve les en-têtes fournis, quelle que soit leur forme', async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, {}))

    await apiFetch('/auth/login', { method: 'POST', body: '{}', headers: new Headers({ 'X-Trace': 'abc' }) })

    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/auth/login')
    expect(sentHeaders().get('Content-Type')).toBe('application/json')
    expect(sentHeaders().get('Accept')).toBe('application/json')
    expect(sentHeaders().get('X-Trace')).toBe('abc')
  })

  it('transforme une erreur au format unique en ApiError avec ses champs', async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse(400, { error: { code: 'validation_failed', message: 'Données invalides.', fields: { userId: 'Type invalide.' } } }),
    )

    const error = await apiFetch('/auth/login').catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({ status: 400, code: 'validation_failed', fields: { userId: 'Type invalide.' } })
  })

  it('signale une réponse d\'erreur qui ne respecte pas le format', async () => {
    fetchMock.mockResolvedValueOnce(new Response('<html>502</html>', { status: 502 }))

    await expect(apiFetch('/me')).rejects.toMatchObject({ status: 502, code: 'unexpected_response' })
  })

  it('signale une panne réseau', async () => {
    fetchMock.mockRejectedValueOnce(new TypeError('Failed to fetch'))

    await expect(apiFetch('/me')).rejects.toMatchObject({ status: 0, code: 'network_error' })
  })
})
