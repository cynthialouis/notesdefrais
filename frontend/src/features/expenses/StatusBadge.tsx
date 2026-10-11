import type { ExpenseStatus } from './expensesStore'
import { statusLabels } from './statusLabels'

function badgeTone(status: ExpenseStatus): string {
  switch (status) {
    case 'draft':
      return 'neutral'
    case 'submitted':
      return 'pending'
    case 'approved':
      return 'success'
    case 'rejected':
      return 'danger'
    default: {
      // Un nouvel état non traité ici casse la compilation
      const unhandled: never = status
      return unhandled
    }
  }
}

export function StatusBadge({ status }: { status: ExpenseStatus }) {
  return <span className={`status-badge status-badge--${badgeTone(status)}`}>{statusLabels[status]}</span>
}
