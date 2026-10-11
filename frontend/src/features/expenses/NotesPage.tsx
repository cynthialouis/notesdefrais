import { useEffect, useState } from 'react'
import { Link } from 'react-router'
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

  return (
    <section>
      <title>Mes notes – Notes de frais</title>
      <div className="page-header">
        <h1>Mes notes</h1>
        <Link to="/notes/nouvelle" className="button">
          Nouvelle note
        </Link>
      </div>

      <label htmlFor="status-filter">État</label>{' '}
      <select id="status-filter" value={filter} onChange={(event) => setFilter(toFilter(event.target.value))}>
        <option value="all">Tous</option>
        {allStatuses.map((status) => (
          <option key={status} value={status}>
            {statusLabels[status]}
          </option>
        ))}
      </select>

      {list.status === 'error' && <p role="alert">{list.message}</p>}
      {(list.status === 'idle' || list.status === 'loading') && <p role="status">Chargement des notes…</p>}
      {list.status === 'ready' && <ExpenseTable expenses={list.expenses} filter={filter} />}
    </section>
  )
}

function ExpenseTable({ expenses, filter }: { expenses: readonly Expense[]; filter: Filter }) {
  // Liste filtrée calculée pendant le rendu, sans effet ni état dérivé (C9)
  const visible = filter === 'all' ? expenses : expenses.filter((expense) => expense.status === filter)

  if (expenses.length === 0) {
    return <p>Vous n'avez encore aucune note.</p>
  }
  if (visible.length === 0) {
    return <p>Aucune note dans cet état.</p>
  }

  return (
    <table className="expense-table">
      <thead>
        <tr>
          <th scope="col">Libellé</th>
          <th scope="col">Date</th>
          <th scope="col">Catégorie</th>
          <th scope="col">Montant</th>
          <th scope="col">État</th>
        </tr>
      </thead>
      <tbody>
        {visible.map((expense) => (
          <tr key={expense.id}>
            <td>
              <Link to={`/notes/${expense.id}`}>{expense.label}</Link>
            </td>
            <td>{formatIsoDate(expense.date)}</td>
            <td>{expense.category.label}</td>
            <td className="amount">{formatCents(expense.amountCents)}</td>
            <td>
              <StatusBadge status={expense.status} />
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
