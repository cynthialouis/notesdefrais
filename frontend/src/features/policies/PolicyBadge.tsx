import { Badge } from '../../ui/Badge'
import type { Expense } from '../expenses/expensesStore'

/** Signalement d'une note hors politique (spec 004, C6), calculé par le serveur. */
export function PolicyBadge({ expense }: { expense: Expense }) {
  if (expense.policy.overLimitByCents == null) {
    return null
  }
  return <Badge tone="warning">Hors politique</Badge>
}
