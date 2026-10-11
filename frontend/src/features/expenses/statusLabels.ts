import type { ExpenseStatus } from './expensesStore'

export const statusLabels: Record<ExpenseStatus, string> = {
  draft: 'Brouillon',
  submitted: 'Soumise',
  approved: 'Validée',
  rejected: 'Rejetée',
}

export const allStatuses: readonly ExpenseStatus[] = ['draft', 'submitted', 'approved', 'rejected']
