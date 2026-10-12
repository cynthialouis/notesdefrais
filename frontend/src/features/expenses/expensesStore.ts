import { z } from 'zod'
import { create } from 'zustand'
import { ApiError, apiFetch, type Schemas } from '../../api/client'

const personSchema = z.object({ id: z.number().int(), name: z.string() })

const expenseBaseSchema = z.object({
  id: z.number().int(),
  owner: personSchema,
  label: z.string(),
  amountCents: z.number().int(),
  date: z.string(),
  category: z.object({ slug: z.string(), label: z.string() }),
  createdAt: z.string(),
  /** Ce que le lecteur peut faire, décidé par le serveur : le front ne recalcule pas ces règles. */
  editable: z.boolean(),
  decidable: z.boolean(),
})

const undecided = { decidedAt: z.null().optional(), decidedBy: z.null().optional(), rejectionReason: z.null().optional() }

/**
 * Note de frais en union discriminée sur l'état : une note soumise a une date de soumission, une note
 * décidée un auteur et une date de décision, une note rejetée un motif. Le contrat OpenAPI ne sait pas
 * l'exprimer, Zod le vérifie à la réception.
 */
const expenseSchema = z.discriminatedUnion('status', [
  expenseBaseSchema.extend({ status: z.literal('draft'), submittedAt: z.null().optional(), ...undecided }),
  expenseBaseSchema.extend({ status: z.literal('submitted'), submittedAt: z.string(), ...undecided }),
  expenseBaseSchema.extend({
    status: z.literal('approved'),
    submittedAt: z.string(),
    decidedAt: z.string(),
    decidedBy: personSchema,
    rejectionReason: z.null().optional(),
  }),
  expenseBaseSchema.extend({
    status: z.literal('rejected'),
    submittedAt: z.string(),
    decidedAt: z.string(),
    decidedBy: personSchema,
    rejectionReason: z.string(),
  }),
])

const expenseEventSchema = z.object({
  type: z.enum(['created', 'submitted', 'approved', 'rejected']),
  actor: personSchema,
  comment: z.string().nullable().optional(),
  createdAt: z.string(),
})

export type ExpenseEvent = z.infer<typeof expenseEventSchema>

export type Expense = z.infer<typeof expenseSchema>
export type ExpenseStatus = Expense['status']

// Contrôle à la compilation : les types déduits restent compatibles avec le contrat généré
const matchesContract: (expense: Expense) => Schemas['ExpenseView'] = (expense) => expense
const eventMatchesContract: (event: ExpenseEvent) => Schemas['ExpenseEventView'] = (event) => event
void matchesContract
void eventMatchesContract

/** Corps de création et de modification : le DTO généré, tous champs renseignés. */
export type ExpenseInput = { [K in keyof Schemas['ExpenseInput']]-?: NonNullable<Schemas['ExpenseInput'][K]> }

function parseOrThrow<T>(schema: z.ZodType<T>, data: unknown): T {
  const parsed = schema.safeParse(data)
  if (!parsed.success) {
    throw new ApiError(0, 'unexpected_response', 'Réponse inattendue du serveur.')
  }
  return parsed.data
}

const toExpense = (data: unknown): Expense => parseOrThrow(expenseSchema, data)

type ExpenseList =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'ready'; expenses: Expense[] }
  | { status: 'error'; message: string }

type ExpensesStore = {
  /** Cache des notes de l'utilisateur, dans l'ordre du serveur (les plus récentes d'abord). */
  list: ExpenseList
  /** Cache des notes à traiter par le manager, dans l'ordre du serveur (les plus anciennes d'abord). */
  teamList: ExpenseList
  fetchExpenses: () => Promise<void>
  fetchExpense: (id: number) => Promise<Expense>
  create: (input: ExpenseInput) => Promise<Expense>
  update: (id: number, input: ExpenseInput) => Promise<Expense>
  submit: (id: number) => Promise<Expense>
  remove: (id: number) => Promise<void>
  fetchTeamExpenses: () => Promise<void>
  approve: (id: number) => Promise<Expense>
  reject: (id: number, reason: string) => Promise<Expense>
  /** Historique d'une note, du plus ancien au plus récent (non mis en cache). */
  fetchEvents: (id: number) => Promise<ExpenseEvent[]>
}

export const useExpensesStore = create<ExpensesStore>()((set, get) => {
  /** Après une écriture, la liste est relue : l'ordre reste celui du serveur, sans le recalculer ici. */
  async function afterWrite<T>(result: T): Promise<T> {
    await get().fetchExpenses()
    return result
  }

  /** Après une décision, la liste à traiter est relue : la note décidée en sort (C7). */
  async function afterDecision(expense: Expense): Promise<Expense> {
    await get().fetchTeamExpenses()
    return expense
  }

  async function load(path: `/${string}`): Promise<ExpenseList> {
    try {
      const data = await apiFetch<unknown[]>(path)
      return { status: 'ready', expenses: data.map(toExpense) }
    } catch (error) {
      return { status: 'error', message: error instanceof ApiError ? error.message : 'Impossible de charger les notes.' }
    }
  }

  return {
    list: { status: 'idle' },
    teamList: { status: 'idle' },

    fetchExpenses: async () => {
      if (get().list.status !== 'ready') {
        set({ list: { status: 'loading' } })
      }
      set({ list: await load('/expenses') })
    },

    fetchTeamExpenses: async () => {
      if (get().teamList.status !== 'ready') {
        set({ teamList: { status: 'loading' } })
      }
      set({ teamList: await load('/team/expenses') })
    },

    approve: async (id) => afterDecision(toExpense(await apiFetch<unknown>(`/expenses/${id}/approve`, { method: 'POST' }))),

    reject: async (id, reason) =>
      afterDecision(toExpense(await apiFetch<unknown>(`/expenses/${id}/reject`, { method: 'POST', body: JSON.stringify({ reason }) }))),

    fetchEvents: async (id) => {
      const data = await apiFetch<unknown[]>(`/expenses/${id}/events`)
      return data.map((event) => parseOrThrow(expenseEventSchema, event))
    },

    fetchExpense: async (id) => toExpense(await apiFetch<unknown>(`/expenses/${id}`)),

    create: async (input) =>
      afterWrite(toExpense(await apiFetch<unknown>('/expenses', { method: 'POST', body: JSON.stringify(input) }))),

    update: async (id, input) =>
      afterWrite(toExpense(await apiFetch<unknown>(`/expenses/${id}`, { method: 'PATCH', body: JSON.stringify(input) }))),

    submit: async (id) => afterWrite(toExpense(await apiFetch<unknown>(`/expenses/${id}/submit`, { method: 'POST' }))),

    remove: async (id) => {
      await apiFetch<void>(`/expenses/${id}`, { method: 'DELETE' })
      await afterWrite(undefined)
    },
  }
})
