import { useEffect } from 'react'
import { Link } from 'react-router'
import { Alert } from '../../ui/Alert'
import { Card } from '../../ui/Card'
import { EmptyState } from '../../ui/EmptyState'
import { PageHeader } from '../../ui/PageHeader'
import { LoadingState } from '../../ui/Spinner'
import { Table, Td, Th } from '../../ui/Table'
import { useExpensesStore, type Expense } from '../expenses/expensesStore'
import { formatIsoDate, formatIsoDay } from '../expenses/format'
import { formatCents } from '../expenses/money'

/** Notes soumises de l'équipe directe, à valider ou rejeter (spec 003, C1, C2). */
export function TeamPage() {
  const teamList = useExpensesStore((state) => state.teamList)
  const fetchTeamExpenses = useExpensesStore((state) => state.fetchTeamExpenses)

  // Synchronisation avec le serveur à l'arrivée sur la page (effet légitime : système extérieur)
  useEffect(() => {
    void fetchTeamExpenses()
  }, [fetchTeamExpenses])

  return (
    <>
      <title>Équipe – Notes de frais</title>
      <PageHeader title="Équipe" description="Notes soumises par votre équipe, des plus anciennes aux plus récentes." />
      <Card>
        {teamList.status === 'error' && <Alert>{teamList.message}</Alert>}
        {(teamList.status === 'idle' || teamList.status === 'loading') && <LoadingState label="Chargement des notes à traiter…" />}
        {teamList.status === 'ready' && <TeamTable expenses={teamList.expenses} />}
      </Card>
    </>
  )
}

function TeamTable({ expenses }: { expenses: readonly Expense[] }) {
  if (expenses.length === 0) {
    return <EmptyState title="Aucune note à traiter." description="Les notes soumises par votre équipe apparaîtront ici." />
  }

  return (
    <Table>
      <thead>
        <tr>
          <Th>Auteur</Th>
          <Th>Libellé</Th>
          <Th>Date</Th>
          <Th>Catégorie</Th>
          <Th align="right">Montant</Th>
          <Th>Soumise le</Th>
        </tr>
      </thead>
      <tbody className="divide-y divide-gray-100">
        {expenses.map((expense) => (
          <tr key={expense.id} className="hover:bg-gray-50">
            <Td>{expense.owner.name}</Td>
            <Td className="font-medium text-gray-900">
              <Link to={`/equipe/${expense.id}`} className="rounded-xs hover:text-brand-700 hover:underline focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500">
                {expense.label}
              </Link>
            </Td>
            <Td>{formatIsoDate(expense.date)}</Td>
            <Td>{expense.category.label}</Td>
            <Td align="right">{formatCents(expense.amountCents)}</Td>
            <Td>{expense.submittedAt ? formatIsoDay(expense.submittedAt) : '—'}</Td>
          </tr>
        ))}
      </tbody>
    </Table>
  )
}
