import { Badge } from '../../ui/Badge'
import { formatCents } from '../expenses/money'
import type { Expense } from '../expenses/expensesStore'

/** Signalement d'une note hors politique avec son dépassement (spec 004, C6), calculé par le serveur. */
export function PolicyBadge({ expense }: { expense: Expense }) {
  const { limitCents, overLimitByCents } = expense.policy
  if (overLimitByCents == null || limitCents == null) {
    return null
  }
  return (
    <Badge tone="warning" title={`Plafond de ${formatCents(limitCents)} dépassé de ${formatCents(overLimitByCents)}`}>
      Hors politique · +{formatCents(overLimitByCents)}
    </Badge>
  )
}
