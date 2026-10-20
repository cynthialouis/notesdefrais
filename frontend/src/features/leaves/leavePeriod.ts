import { formatIsoDate } from '../expenses/format'
import type { Leave } from './leavesStore'

/** « Du 03/11/2026 au 07/11/2026 », ou « Le 03/11/2026 » pour un seul jour. */
export function leavePeriod(leave: Pick<Leave, 'startDate' | 'endDate'>): string {
  return leave.startDate === leave.endDate
    ? `Le ${formatIsoDate(leave.startDate)}`
    : `Du ${formatIsoDate(leave.startDate)} au ${formatIsoDate(leave.endDate)}`
}
