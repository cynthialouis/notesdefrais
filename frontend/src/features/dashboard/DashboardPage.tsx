import { Suspense, use, useState } from 'react'
import { Alert } from '../../ui/Alert'
import { ButtonLink } from '../../ui/Button'
import { Card } from '../../ui/Card'
import { PageHeader } from '../../ui/PageHeader'
import { LoadingState } from '../../ui/Spinner'
import { roleLabels } from '../auth/roles'
import { useSessionStore } from '../auth/sessionStore'
import { formatCents } from '../expenses/money'
import { statusLabels } from '../expenses/statusLabels'
import { loadMyStats, type MyStats, type StatsResult } from './statsApi'

/** « Aucune note », « 1 note », « 3 notes ». */
function noteCount(count: number): string {
  if (count === 0) {
    return 'Aucune note'
  }
  return count === 1 ? '1 note' : `${count} notes`
}

/** Tableau de bord (spec 006) : ses propres totaux pour tous. */
export function DashboardPage() {
  const session = useSessionStore((state) => state.session)
  // Promesse créée par ce composant, qui ne se suspend pas : stable pour use()
  const [myStats] = useState(loadMyStats)

  return (
    <>
      <title>Tableau de bord – Notes de frais</title>
      <PageHeader
        title="Tableau de bord"
        description={
          session.status === 'authenticated' &&
          `Connecté en tant que ${session.user.name}, ${roleLabels[session.user.role].toLowerCase()}.`
        }
      />
      <div className="flex flex-col gap-6">
        <Card
          title="Mes notes"
          actions={
            <div className="flex flex-wrap gap-2">
              <ButtonLink to="/notes">Voir mes notes</ButtonLink>
              <ButtonLink to="/notes/nouvelle" variant="primary">
                Nouvelle note
              </ButtonLink>
            </div>
          }
        >
          <Suspense fallback={<LoadingState />}>
            <MyTotals stats={myStats} />
          </Suspense>
        </Card>
      </div>
    </>
  )
}

/** Nombre et montant de ses notes pour chaque état, tels que calculés par le serveur (C1, C2, C5). */
function MyTotals({ stats }: { stats: Promise<StatsResult<MyStats>> }) {
  const result = use(stats)
  if (!result.ok) {
    return <Alert>{result.message}</Alert>
  }
  return (
    <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {result.stats.byStatus.map(({ status, count, totalCents }) => (
        <div key={status} className="rounded-md border border-gray-200 px-4 py-3">
          <dt className="text-sm font-medium text-gray-600">{statusLabels[status]}</dt>
          <dd className="mt-1 text-2xl font-semibold text-gray-900 tabular-nums">{formatCents(totalCents)}</dd>
          <dd className="text-sm text-gray-500">{noteCount(count)}</dd>
        </div>
      ))}
    </dl>
  )
}
