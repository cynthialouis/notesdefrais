import { vi } from 'vitest'
import { jsonResponse } from './fixtures'

type Handler = (init: RequestInit | undefined) => Response | Promise<Response>

/**
 * Faux serveur pour les tests : associe « MÉTHODE /api/chemin » à une réponse.
 * Une requête inattendue échoue bruyamment plutôt que de passer en silence.
 */
export function stubApi(routes: Record<string, Handler>) {
  const fetchMock = vi.fn<typeof fetch>(async (input, init) => {
    const key = `${init?.method ?? 'GET'} ${String(input)}`
    const handler = routes[key]
    if (!handler) {
      throw new Error(`Requête non prévue par le test : ${key}`)
    }
    return handler(init)
  })
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

export const json = (status: number, body?: unknown) => () => jsonResponse(status, body)
