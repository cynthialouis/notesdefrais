import type { LeaveStatus } from './leavesStore'

export const leaveStatusLabels: Record<LeaveStatus, string> = {
  pending: 'En attente',
  accepted: 'Acceptée',
  refused: 'Refusée',
  withdrawn: 'Retirée',
  cancelled: 'Annulée',
}
