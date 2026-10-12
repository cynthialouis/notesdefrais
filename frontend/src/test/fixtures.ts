import type { CurrentUser } from '../features/auth/sessionStore'

export const employee: CurrentUser = { id: 4, name: 'Léa Bernard', email: 'lea@demo.test', role: 'employee', accessibleRoles: ['employee'] }
export const manager: CurrentUser = { id: 2, name: 'Claire Dubois', email: 'claire@demo.test', role: 'manager', accessibleRoles: ['manager', 'employee'] }
export const admin: CurrentUser = { id: 1, name: 'Alice Martin', email: 'alice@demo.test', role: 'admin', accessibleRoles: ['admin', 'manager', 'employee'] }

export function jsonResponse(status: number, body?: unknown): Response {
  return new Response(body === undefined ? null : JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

export const categories = [
  { slug: 'repas', label: 'Repas' },
  { slug: 'transport', label: 'Transport' },
]

export const draftExpense = {
  id: 2,
  owner: { id: 4, name: 'Léa Bernard' },
  label: 'Train Paris – Lyon',
  amountCents: 8900,
  date: '2026-10-06',
  category: { slug: 'transport', label: 'Transport' },
  status: 'draft',
  createdAt: '2026-10-06T10:00:00+02:00',
  submittedAt: null,
  editable: true,
  decidable: false,
}

export const submittedExpense = {
  ...draftExpense,
  id: 1,
  label: 'Déjeuner client',
  amountCents: 4250,
  date: '2026-09-29',
  category: { slug: 'repas', label: 'Repas' },
  status: 'submitted',
  submittedAt: '2026-09-30T09:00:00+02:00',
  editable: false,
}

export const rejectedExpense = {
  ...draftExpense,
  id: 3,
  label: 'Taxi aéroport',
  status: 'rejected',
  submittedAt: '2026-09-28T10:00:00+02:00',
  decidedAt: '2026-09-29T10:00:00+02:00',
  decidedBy: { id: 2, name: 'Claire Dubois' },
  rejectionReason: 'Précisez le trajet.',
  editable: true,
}

/** Note soumise de Léa, vue par sa responsable Claire : décidable. */
export const teamSubmittedExpense = { ...submittedExpense, editable: false, decidable: true }
