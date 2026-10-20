import { Suspense, use, useState } from 'react'
import { Alert } from '../../ui/Alert'
import { Bar } from '../../ui/Bar'
import { ButtonLink } from '../../ui/Button'
import { buttonClasses } from '../../ui/buttonStyles'
import { Card } from '../../ui/Card'
import { EmptyState } from '../../ui/EmptyState'
import { PageHeader } from '../../ui/PageHeader'
import { LoadingState } from '../../ui/Spinner'
import { Table, Td, Th } from '../../ui/Table'
import { roleLabels } from '../auth/roles'
import { canAccess, useSessionStore } from '../auth/sessionStore'
import { formatCents } from '../expenses/money'
import { statusLabels } from '../expenses/statusLabels'
import { loadMyStats, loadTeamStats, teamExportUrl, type MyStats, type StatsResult, type TeamStats } from './statsApi'

const monthFormatter = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric', timeZone: 'UTC' })

/** « 2025-11 » → « novembre 2025 » (mois sans heure : formaté en UTC pour ne jamais changer de mois). */
function formatMonth(month: string): string {
  return monthFormatter.format(new Date(`${month}-01T00:00:00Z`))
}

/** « Aucune note », « 1 note », « 3 notes ». */
function noteCount(count: number): string {
  if (count === 0) {
    return 'Aucune note'
  }
  return count === 1 ? '1 note' : `${count} notes`
}

/** Tableau de bord (spec 006) : ses propres totaux pour tous, ceux de son équipe pour un manager. */
export function DashboardPage() {
  const session = useSessionStore((state) => state.session)
  const isManager = canAccess(session, 'manager')
  // Promesses créées par ce composant, qui ne se suspend pas : stables pour use()
  const [myStats] = useState(loadMyStats)
  const [teamStats] = useState(() => (isManager ? loadTeamStats() : null))

  return (
    <>
      <title>Tableau de bord – Espace salarié</title>
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
        {teamStats && (
          <Card
            title="Équipe : notes validées"
            actions={
              <a href={teamExportUrl} download className={buttonClasses('secondary')}>
                Exporter en CSV
              </a>
            }
          >
            <Suspense fallback={<LoadingState />}>
              <TeamTotals stats={teamStats} />
            </Suspense>
          </Card>
        )}
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

/** Notes validées de l'équipe directe sur les 12 derniers mois, par catégorie et par mois (C3, C4, C9). */
function TeamTotals({ stats }: { stats: Promise<StatsResult<TeamStats>> }) {
  const result = use(stats)
  if (!result.ok) {
    return <Alert>{result.message}</Alert>
  }
  const { from, to, count, totalCents, byCategory, byMonth } = result.stats
  const period = `De ${formatMonth(from.slice(0, 7))} à ${formatMonth(to.slice(0, 7))}`
  if (count === 0) {
    return <EmptyState title="Aucune note validée sur la période" description={`${period} : les notes validées de votre équipe apparaîtront ici.`} />
  }
  const maxCategory = Math.max(...byCategory.map((entry) => entry.totalCents))
  const maxMonth = Math.max(...byMonth.map((entry) => entry.totalCents))

  return (
    <div className="flex flex-col gap-6">
      <p className="text-sm text-gray-600">
        {period} : <span className="font-semibold text-gray-900 tabular-nums">{formatCents(totalCents)}</span>, {noteCount(count).toLowerCase()}.
      </p>
      {/* Écart horizontal plus large que les débords des deux tableaux (-mx-5 chacun) : pas de chevauchement */}
      <div className="grid gap-6 lg:grid-cols-2 lg:gap-x-12">
        <Table label="Par catégorie">
          <thead>
            <tr>
              <Th>Catégorie</Th>
              <Th align="right">Notes</Th>
              <Th align="right">Montant</Th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {byCategory.map(({ category, count: notes, totalCents: amount }) => (
              <tr key={category.slug}>
                <Td>{category.label}</Td>
                <Td align="right">{notes}</Td>
                <Td align="right">
                  {formatCents(amount)}
                  <Bar value={amount} max={maxCategory} className="mt-1 ml-auto w-28" />
                </Td>
              </tr>
            ))}
          </tbody>
        </Table>
        <Table label="Par mois">
          <thead>
            <tr>
              <Th>Mois</Th>
              <Th align="right">Notes</Th>
              <Th align="right">Montant</Th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {byMonth.map(({ month, count: notes, totalCents: amount }) => (
              <tr key={month}>
                <Td>{formatMonth(month)}</Td>
                <Td align="right">{notes}</Td>
                <Td align="right">
                  {formatCents(amount)}
                  <Bar value={amount} max={maxMonth} className="mt-1 ml-auto w-28" />
                </Td>
              </tr>
            ))}
          </tbody>
        </Table>
      </div>
    </div>
  )
}
