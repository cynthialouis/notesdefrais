import { Badge, type BadgeTone } from '../../ui/Badge'
import { leaveStatusLabels } from './leaveStatusLabels'
import type { LeaveStatus } from './leavesStore'

function badgeTone(status: LeaveStatus): BadgeTone {
  switch (status) {
    case 'pending':
      return 'info'
    case 'accepted':
      return 'success'
    case 'refused':
      return 'danger'
    case 'withdrawn':
      return 'neutral'
    case 'cancelled':
      return 'warning'
    default: {
      // Un nouvel état non traité ici casse la compilation
      const unhandled: never = status
      return unhandled
    }
  }
}

export function LeaveStatusBadge({ status }: { status: LeaveStatus }) {
  return <Badge tone={badgeTone(status)}>{leaveStatusLabels[status]}</Badge>
}
