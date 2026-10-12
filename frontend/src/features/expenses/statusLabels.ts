import type { ExpenseEvent, ExpenseStatus } from './expensesStore'

export const statusLabels: Record<ExpenseStatus, string> = {
  draft: 'Brouillon',
  submitted: 'Soumise',
  approved: 'Validée',
  rejected: 'Rejetée',
}

export const allStatuses: readonly ExpenseStatus[] = ['draft', 'submitted', 'approved', 'rejected']

export const eventLabels: Record<ExpenseEvent['type'], string> = {
  created: 'Note créée',
  submitted: 'Note soumise',
  approved: 'Note validée',
  rejected: 'Note rejetée',
}
