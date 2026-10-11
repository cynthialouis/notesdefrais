import { z } from 'zod'
import type { components } from './schema'

export type Schemas = components['schemas']

// Format d'erreur unique de l'API : { error: { code, message, fields? } }
const apiErrorBodySchema = z.object({
  error: z.object({
    code: z.string(),
    message: z.string(),
    fields: z.record(z.string(), z.string()).optional(),
  }),
})

export class ApiError extends Error {
  readonly status: number
  readonly code: string
  readonly fields: Readonly<Record<string, string>>

  constructor(status: number, code: string, message: string, fields: Record<string, string> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.fields = fields
  }
}

async function toApiError(response: Response): Promise<ApiError> {
  const body: unknown = await response.json().catch(() => null)
  const parsed = apiErrorBodySchema.safeParse(body)
  if (!parsed.success) {
    return new ApiError(response.status, 'unexpected_response', 'Réponse inattendue du serveur.')
  }
  const { code, message, fields } = parsed.data.error
  return new ApiError(response.status, code, message, fields)
}

/**
 * Appelle l'API via le proxy Vite (/api). Lève une ApiError pour toute réponse en erreur
 * ou toute panne réseau.
 *
 * Le corps d'une réponse réussie est typé par le type généré depuis OpenAPI : le contrat
 * est garanti par le back, il n'est pas revalidé ici.
 */
export async function apiFetch<T>(path: `/${string}`, init: RequestInit = {}): Promise<T> {
  let response: Response
  try {
    response = await fetch(`/api${path}`, {
      ...init,
      headers: { Accept: 'application/json', ...(init.body ? { 'Content-Type': 'application/json' } : {}), ...init.headers },
    })
  } catch {
    throw new ApiError(0, 'network_error', 'Serveur injoignable.')
  }

  if (!response.ok) {
    throw await toApiError(response)
  }
  if (response.status === 204) {
    return undefined as T
  }
  return (await response.json()) as T
}
