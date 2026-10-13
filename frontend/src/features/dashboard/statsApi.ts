import { z } from 'zod'
import { ApiError, apiFetch, type Schemas } from '../../api/client'

const total = { count: z.number().int(), totalCents: z.number().int() }

const myStatsSchema = z.object({
  byStatus: z.array(z.object({ status: z.enum(['draft', 'submitted', 'approved', 'rejected']), ...total })),
})

const teamStatsSchema = z.object({
  from: z.string(),
  to: z.string(),
  ...total,
  byCategory: z.array(z.object({ category: z.object({ slug: z.string(), label: z.string() }), ...total })),
  byMonth: z.array(z.object({ month: z.string(), ...total })),
})

export type MyStats = z.infer<typeof myStatsSchema>
export type TeamStats = z.infer<typeof teamStatsSchema>

// Contrôle à la compilation : les types déduits restent compatibles avec le contrat généré
const myStatsMatchContract: (stats: MyStats) => Schemas['MyStatsView'] = (stats) => stats
const teamStatsMatchContract: (stats: TeamStats) => Schemas['TeamStatsView'] = (stats) => stats
void myStatsMatchContract
void teamStatsMatchContract

export type StatsResult<T> = { ok: true; stats: T } | { ok: false; message: string }

/** Totaux calculés par le serveur (spec 006, C5), vérifiés à la réception, sans erreur levée. */
function load<T>(path: `/${string}`, schema: z.ZodType<T>): Promise<StatsResult<T>> {
  return apiFetch<unknown>(path)
    .then((body): StatsResult<T> => {
      const parsed = schema.safeParse(body)
      return parsed.success ? { ok: true, stats: parsed.data } : { ok: false, message: 'Réponse inattendue du serveur.' }
    })
    .catch((error: unknown): StatsResult<T> => ({
      ok: false,
      message: error instanceof ApiError ? error.message : 'Impossible de charger le tableau de bord.',
    }))
}

/** Ses propres totaux par état (C1, C2). Lus par le seul tableau de bord : chargés localement. */
export const loadMyStats = (): Promise<StatsResult<MyStats>> => load('/stats/me', myStatsSchema)

/** Notes validées de l'équipe directe sur la période (C3, C4), pour un manager. */
export const loadTeamStats = (): Promise<StatsResult<TeamStats>> => load('/stats/team', teamStatsSchema)

/** Export CSV, téléchargé par le navigateur (C6). */
export const teamExportUrl = '/api/exports/team-expenses.csv'
