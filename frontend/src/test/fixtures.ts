import type { CurrentUser } from '../features/auth/sessionStore'

export const employee: CurrentUser = { id: 4, name: 'Léa Bernard', email: 'lea@demo.test', role: 'employee', accessibleRoles: ['employee'] }
export const manager: CurrentUser = { id: 2, name: 'Claire Dubois', email: 'claire@demo.test', role: 'manager', accessibleRoles: ['manager', 'employee'] }
export const admin: CurrentUser = { id: 1, name: 'Alice Martin', email: 'alice@demo.test', role: 'admin', accessibleRoles: ['admin', 'manager', 'employee'] }

export function jsonResponse(status: number, body?: unknown): Response {
  return new Response(body === undefined ? null : JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

export const categories = [
  { slug: 'repas', label: 'Repas', maxAmountCents: 5000, receiptRequired: false },
  { slug: 'transport', label: 'Transport', maxAmountCents: 30000, receiptRequired: false },
  { slug: 'hebergement', label: 'Hébergement', maxAmountCents: 15000, receiptRequired: true },
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
  decidedAt: null,
  decidedBy: null,
  rejectionReason: null,
  editable: true,
  decidable: false,
  policy: { limitCents: 30000, overLimitByCents: null, receiptRequired: false, receiptMissing: false },
  receipt: null,
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

/** Note de repas de 62 € pour un plafond de 50 € : hors politique de 12 €. */
export const overLimitExpense = {
  ...submittedExpense,
  id: 5,
  label: 'Dîner client Martin',
  amountCents: 6200,
  policy: { limitCents: 5000, overLimitByCents: 1200, receiptRequired: false, receiptMissing: false },
}

export const pendingLeave = {
  id: 10,
  requester: { id: 4, name: 'Léa Bernard' },
  startDate: '2026-11-03',
  endDate: '2026-11-07',
  status: 'pending',
  createdAt: '2026-10-17T10:00:00+02:00',
  decidedAt: null,
  decidedBy: null,
  refusalReason: null,
  cancelledAt: null,
  cancelledBy: null,
  cancellationReason: null,
  decidable: false,
  withdrawable: true,
  cancellable: false,
}

export const acceptedLeave = {
  ...pendingLeave,
  id: 11,
  startDate: '2026-11-19',
  endDate: '2026-11-23',
  status: 'accepted',
  decidedAt: '2026-10-11T10:00:00+02:00',
  decidedBy: { id: 2, name: 'Claire Dubois' },
  withdrawable: false,
}

export const refusedLeave = {
  ...acceptedLeave,
  id: 12,
  startDate: '2026-10-27',
  endDate: '2026-10-28',
  status: 'refused',
  refusalReason: 'Période de clôture des comptes',
}

export const cancelledLeave = {
  ...acceptedLeave,
  id: 13,
  startDate: '2026-11-29',
  endDate: '2026-12-03',
  status: 'cancelled',
  cancelledAt: '2026-10-07T10:00:00+02:00',
  cancelledBy: { id: 1, name: 'Alice Martin' },
  cancellationReason: 'Dates saisies par erreur',
}
