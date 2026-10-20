import { z } from 'zod'
import { create } from 'zustand'
import { ApiError, apiFetch, type Schemas } from '../../api/client'

const personSchema = z.object({ id: z.number().int(), name: z.string() })

const leaveBaseSchema = z.object({
  id: z.number().int(),
  requester: personSchema,
  /** Premier et dernier jour, Y-m-d, bornes comprises. */
  startDate: z.string(),
  endDate: z.string(),
  createdAt: z.string(),
  /** Ce que le lecteur peut faire, décidé par le serveur : le front ne recalcule pas ces règles. */
  decidable: z.boolean(),
  withdrawable: z.boolean(),
  cancellable: z.boolean(),
})

// Les champs nullables sont toujours présents (null) : le contrat les déclare obligatoires
const undecided = { decidedAt: z.null(), decidedBy: z.null(), refusalReason: z.null() }
const notCancelled = { cancelledAt: z.null(), cancelledBy: z.null(), cancellationReason: z.null() }

/**
 * Demande de congé en union discriminée sur l'état : une demande décidée a un auteur et une date de
 * décision, une demande refusée un motif, une demande annulée son auteur, sa date et son motif (une
 * demande annulée en attente n'a pas de décision). Le contrat OpenAPI ne sait pas l'exprimer, Zod le
 * vérifie à la réception.
 */
const leaveSchema = z.discriminatedUnion('status', [
  leaveBaseSchema.extend({ status: z.literal('pending'), ...undecided, ...notCancelled }),
  leaveBaseSchema.extend({ status: z.literal('withdrawn'), ...undecided, ...notCancelled }),
  leaveBaseSchema.extend({ status: z.literal('accepted'), decidedAt: z.string(), decidedBy: personSchema, refusalReason: z.null(), ...notCancelled }),
  leaveBaseSchema.extend({ status: z.literal('refused'), decidedAt: z.string(), decidedBy: personSchema, refusalReason: z.string(), ...notCancelled }),
  leaveBaseSchema.extend({
    status: z.literal('cancelled'),
    decidedAt: z.string().nullable(),
    decidedBy: personSchema.nullable(),
    refusalReason: z.null(),
    cancelledAt: z.string(),
    cancelledBy: personSchema,
    cancellationReason: z.string(),
  }),
])

export type Leave = z.infer<typeof leaveSchema>
export type LeaveStatus = Leave['status']

// Contrôle à la compilation : le type déduit reste compatible avec le contrat généré
const matchesContract: (leave: Leave) => Schemas['LeaveRequestView'] = (leave) => leave
void matchesContract

/** Corps d'une demande : le DTO généré, tous champs renseignés. */
export type LeaveInput = { [K in keyof Schemas['LeaveRequestInput']]-?: NonNullable<Schemas['LeaveRequestInput'][K]> }

function toLeave(data: unknown): Leave {
  const parsed = leaveSchema.safeParse(data)
  if (!parsed.success) {
    throw new ApiError(0, 'unexpected_response', 'Réponse inattendue du serveur.')
  }
  return parsed.data
}

type LeaveList =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'ready'; leaves: Leave[] }
  | { status: 'error'; message: string }

/** Les quatre listes, chacune dans l'ordre du serveur et avec sa règle de visibilité. */
const lists = {
  /** Toutes ses demandes, début le plus récent d'abord (C16). */
  mine: '/me/leaves',
  /** Congés acceptés en cours ou à venir de tous, début croissant (C15). */
  accepted: '/leaves',
  /** Demandes en attente de l'équipe directe, pour un manager (C6). */
  team: '/team/leaves',
  /** Demandes en attente de tous, pour l'admin (C18). */
  allPending: '/leaves/pending',
} as const

type ListName = keyof typeof lists

type LeavesStore = Record<ListName, LeaveList> & {
  fetchMine: () => Promise<void>
  fetchAccepted: () => Promise<void>
  fetchTeam: () => Promise<void>
  fetchAllPending: () => Promise<void>
  request: (input: LeaveInput) => Promise<Leave>
  withdraw: (id: number) => Promise<Leave>
  accept: (id: number) => Promise<Leave>
  refuse: (id: number, reason: string) => Promise<Leave>
  cancel: (id: number, reason: string) => Promise<Leave>
}

export const useLeavesStore = create<LeavesStore>()((set, get) => {
  async function fetchList(name: ListName): Promise<void> {
    if (get()[name].status !== 'ready') {
      set({ [name]: { status: 'loading' } })
    }
    let list: LeaveList
    try {
      const data = await apiFetch<unknown[]>(lists[name])
      list = { status: 'ready', leaves: data.map(toLeave) }
    } catch (error) {
      list = { status: 'error', message: error instanceof ApiError ? error.message : 'Impossible de charger les demandes.' }
    }
    set({ [name]: list })
  }

  /**
   * Après une écriture, les listes déjà chargées sont relues : une demande change de liste avec son état
   * (acceptée, elle sort des demandes à traiter et entre dans les congés à venir), sans le recalculer ici.
   */
  async function afterWrite(leave: Leave): Promise<Leave> {
    const loaded = (Object.keys(lists) as ListName[]).filter((name) => get()[name].status !== 'idle')
    await Promise.all(loaded.map(fetchList))
    return leave
  }

  const post = async (path: `/${string}`, body?: unknown): Promise<Leave> =>
    afterWrite(toLeave(await apiFetch<unknown>(path, { method: 'POST', ...(body === undefined ? {} : { body: JSON.stringify(body) }) })))

  return {
    mine: { status: 'idle' },
    accepted: { status: 'idle' },
    team: { status: 'idle' },
    allPending: { status: 'idle' },

    fetchMine: () => fetchList('mine'),
    fetchAccepted: () => fetchList('accepted'),
    fetchTeam: () => fetchList('team'),
    fetchAllPending: () => fetchList('allPending'),

    request: (input) => post('/leaves', input),
    withdraw: (id) => post(`/leaves/${id}/withdraw`),
    accept: (id) => post(`/leaves/${id}/accept`),
    refuse: (id, reason) => post(`/leaves/${id}/refuse`, { reason }),
    cancel: (id, reason) => post(`/leaves/${id}/cancel`, { reason }),
  }
})
