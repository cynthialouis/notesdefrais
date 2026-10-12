import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { Alert } from '../../ui/Alert'
import { ButtonLink } from '../../ui/Button'
import { Card } from '../../ui/Card'
import { EmptyState } from '../../ui/EmptyState'
import { Select } from '../../ui/Field'
import { PageHeader } from '../../ui/PageHeader'
import { LoadingState } from '../../ui/Spinner'
import { Table, Td, Th } from '../../ui/Table'
import { useExpensesStore, type Expense, type ExpenseStatus } from './expensesStore'
import { formatIsoDate } from './format'
import { formatCents } from './money'
import { StatusBadge } from './StatusBadge'
import { allStatuses, statusLabels } from './statusLabels'

type Filter = ExpenseStatus | 'all'

function toFilter(value: string): Filter {
  return allStatuses.find((status) => status === value) ?? 'all'
}

export function NotesPage() {
  const list = useExpensesStore((state) => state.list)
  const fetchExpenses = useExpensesStore((state) => state.fetchExpenses)
  // Filtre propre à cette page : état local, pas dans le store (C9)
  const [filter, setFilter] = useState<Filter>('all')

  // Synchronisation avec le serveur à l'arrivée sur la page (effet légitime : système extérieur)
  useEffect(() => {
    void fetchExpenses()
  }, [fetchExpenses])

  const filterControl = (
    <div className="flex items-center gap-2">
      <label htmlFor="status-filter" className="text-sm text-gray-600">
        État
      </label>
      <Select id="status-filter" className="w-auto" value={filter} onChange={(event) => setFilter(toFilter(event.target.value))}>
        <option value="all">Tous</option>
        {allStatuses.map((status) => (
          <option key={status} value={status}>
            {statusLabels[status]}
          </option>
        ))}
      </Select>
    </div>
  )

  return (
    <>
      <title>Mes notes – Notes de frais</title>
      <PageHeader
        title="Mes notes"
        description="Vos notes de frais, des plus récentes aux plus anciennes."
        actions={
          <ButtonLink to="/notes/nouvelle" variant="primary">
            Nouvelle note
          </ButtonLink>
        }
      />
      <Card actions={filterControl}>
        {list.status === 'error' && <Alert>{list.message}</Alert>}
        {(list.status === 'idle' || list.status === 'loading') && <LoadingState label="Chargement des notes…" />}
        {list.status === 'ready' && <ExpenseTable expenses={list.expenses} filter={filter} />}
      </Card>
    </>
  )
}

function ExpenseTable({ expenses, filter }: { expenses: readonly Expense[]; filter: Filter }) {
  // Liste filtrée calculée pendant le rendu, sans effet ni état dérivé (C9)
  const visible = filter === 'all' ? expenses : expenses.filter((expense) => expense.status === filter)

  if (expenses.length === 0) {
    return (
      <EmptyState
        title="Vous n'avez encore aucune note."
        description="Créez un brouillon, vous pourrez le modifier avant de le soumettre."
        action={
          <ButtonLink to="/notes/nouvelle" variant="primary">
            Créer une note
          </ButtonLink>
        }
      />
    )
  }
  if (visible.length === 0) {
    return <EmptyState title="Aucune note dans cet état." description="Choisissez un autre état ou « Tous »." />
  }

  return (
    <Table>
      <thead>
        <tr>
          <Th>Libellé</Th>
          <Th>Date</Th>
          <Th>Catégorie</Th>
          <Th align="right">Montant</Th>
          <Th>État</Th>
        </tr>
      </thead>
      <tbody className="divide-y divide-gray-100">
        {visible.map((expense) => (
          <tr key={expense.id} className="hover:bg-gray-50">
            <Td className="font-medium text-gray-900">
              <Link to={`/notes/${expense.id}`} className="rounded-xs hover:text-brand-700 hover:underline focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500">
                {expense.label}
              </Link>
            </Td>
            <Td>{formatIsoDate(expense.date)}</Td>
            <Td>{expense.category.label}</Td>
            <Td align="right">{formatCents(expense.amountCents)}</Td>
            <Td>
              <StatusBadge status={expense.status} />
            </Td>
          </tr>
        ))}
      </tbody>
    </Table>
  )
}
