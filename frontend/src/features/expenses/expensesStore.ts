import { z } from 'zod'
import { create } from 'zustand'
import { ApiError, apiFetch, type Schemas } from '../../api/client'

const expenseBaseSchema = z.object({
  id: z.number().int(),
  label: z.string(),
  amountCents: z.number().int(),
  date: z.string(),
  category: z.object({ slug: z.string(), label: z.string() }),
  createdAt: z.string(),
})

/**
 * Note de frais en union discriminée sur l'état : une note soumise (ou décidée) a forcément
 * une date de soumission. Le contrat OpenAPI ne sait pas l'exprimer, Zod le vérifie à la réception.
 */
const expenseSchema = z.discriminatedUnion('status', [
  expenseBaseSchema.extend({ status: z.literal('draft'), submittedAt: z.null().optional() }),
  expenseBaseSchema.extend({ status: z.literal('submitted'), submittedAt: z.string() }),
  expenseBaseSchema.extend({ status: z.literal('approved'), submittedAt: z.string() }),
  expenseBaseSchema.extend({ status: z.literal('rejected'), submittedAt: z.string() }),
])

export type Expense = z.infer<typeof expenseSchema>
export type ExpenseStatus = Expense['status']

// Contrôle à la compilation : une Expense reste une ExpenseView valide du contrat généré
const matchesContract: (expense: Expense) => Schemas['ExpenseView'] = (expense) => expense
void matchesContract

/** Corps de création et de modification : le DTO généré, tous champs renseignés. */
export type ExpenseInput = { [K in keyof Schemas['ExpenseInput']]-?: NonNullable<Schemas['ExpenseInput'][K]> }

function toExpense(data: unknown): Expense {
  const parsed = expenseSchema.safeParse(data)
  if (!parsed.success) {
    throw new ApiError(0, 'unexpected_response', 'Réponse inattendue du serveur.')
  }
  return parsed.data
}

type ExpenseList =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'ready'; expenses: Expense[] }
  | { status: 'error'; message: string }

type ExpensesStore = {
  /** Cache des notes de l'utilisateur, dans l'ordre du serveur (les plus récentes d'abord). */
  list: ExpenseList
  fetchExpenses: () => Promise<void>
  fetchExpense: (id: number) => Promise<Expense>
  create: (input: ExpenseInput) => Promise<Expense>
  update: (id: number, input: ExpenseInput) => Promise<Expense>
  submit: (id: number) => Promise<Expense>
  remove: (id: number) => Promise<void>
}

export const useExpensesStore = create<ExpensesStore>()((set, get) => {
  /** Après une écriture, la liste est relue : l'ordre reste celui du serveur, sans le recalculer ici. */
  async function afterWrite<T>(result: T): Promise<T> {
    await get().fetchExpenses()
    return result
  }

  return {
    list: { status: 'idle' },

    fetchExpenses: async () => {
      if (get().list.status !== 'ready') {
        set({ list: { status: 'loading' } })
      }
      try {
        const data = await apiFetch<unknown[]>('/expenses')
        set({ list: { status: 'ready', expenses: data.map(toExpense) } })
      } catch (error) {
        set({ list: { status: 'error', message: error instanceof ApiError ? error.message : 'Impossible de charger les notes.' } })
      }
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
