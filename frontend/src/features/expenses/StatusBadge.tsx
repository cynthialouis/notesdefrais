import { Badge, type BadgeTone } from '../../ui/Badge'
import type { ExpenseStatus } from './expensesStore'
import { statusLabels } from './statusLabels'

function badgeTone(status: ExpenseStatus): BadgeTone {
  switch (status) {
    case 'draft':
      return 'neutral'
    case 'submitted':
      return 'info'
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
  return <Badge tone={badgeTone(status)}>{statusLabels[status]}</Badge>
}
