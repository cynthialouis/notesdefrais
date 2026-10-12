import { Suspense, use, useState, useTransition } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError } from '../../api/client'
import { Alert } from '../../ui/Alert'
import { Button } from '../../ui/Button'
import { Card } from '../../ui/Card'
import { PageHeader } from '../../ui/PageHeader'
import { LoadingState } from '../../ui/Spinner'
import { loadCategories, type CategoriesResult } from './categories'
import { ExpenseForm } from './ExpenseForm'
import { useExpensesStore, type Expense, type ExpenseInput } from './expensesStore'
import { formatIsoDate } from './format'
import { centsToEurosInput, formatCents } from './money'
import { StatusBadge } from './StatusBadge'

type ExpenseResult = { ok: true; expense: Expense } | { ok: false; message: string }

/** Une instance par note : changer d'identifiant recrée la page et ses chargements. */
export function ExpenseDetailRoute() {
  const { id = '' } = useParams()
  return <ExpenseDetailPage key={id} id={Number(id)} />
}

function ExpenseDetailPage({ id }: { id: number }) {
  const fetchExpense = useExpensesStore((state) => state.fetchExpense)
  // Promesses créées par ce composant, qui ne se suspend pas : stables pour use()
  const [expense] = useState(() =>
    fetchExpense(id)
      .then((value): ExpenseResult => ({ ok: true, expense: value }))
      .catch((error: unknown): ExpenseResult => ({
        ok: false,
        message: error instanceof ApiError && error.status === 404 ? 'Note introuvable.' : 'Impossible de charger la note.',
      })),
  )
  const [categories] = useState(loadCategories)

  return (
    <>
      <title>Note de frais – Notes de frais</title>
      <p className="mb-4">
        <Link to="/notes" className="rounded-xs text-sm font-medium text-gray-600 hover:text-gray-900 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500">
          ← Mes notes
        </Link>
      </p>
      <Suspense fallback={<LoadingState />}>
        <ExpenseDetail expense={expense} categories={categories} />
      </Suspense>
    </>
  )
}

function ExpenseDetail({ expense: expensePromise, categories }: { expense: Promise<ExpenseResult>; categories: Promise<CategoriesResult> }) {
  const result = use(expensePromise)
  if (!result.ok) {
    return <Alert>{result.message}</Alert>
  }
  const { expense } = result

  return (
    <>
      <PageHeader
        title={
          <span className="flex flex-wrap items-center gap-3">
            {expense.label} <StatusBadge status={expense.status} />
          </span>
        }
      />
      {expense.editable ? <EditableExpense expense={expense} categories={categories} /> : <ReadOnlyExpense expense={expense} />}
    </>
  )
}

function ReadOnlyExpense({ expense }: { expense: Expense }) {
  const rows = [
    ['Montant', formatCents(expense.amountCents)],
    ['Date', formatIsoDate(expense.date)],
    ['Catégorie', expense.category.label],
  ]
  return (
    <Card className="max-w-2xl">
      <dl className="divide-y divide-gray-100">
        {rows.map(([term, value]) => (
          <div key={term} className="grid grid-cols-3 gap-4 py-3 text-sm">
            <dt className="font-medium text-gray-600">{term}</dt>
            <dd className="col-span-2 text-gray-900">{value}</dd>
          </div>
        ))}
      </dl>
    </Card>
  )
}

/** Note modifiable (brouillon en 002) : modifiable (C5), soumettable (C6), supprimable après confirmation (C11). */
function EditableExpense({ expense, categories }: { expense: Expense; categories: Promise<CategoriesResult> }) {
  const update = useExpensesStore((state) => state.update)
  const submit = useExpensesStore((state) => state.submit)
  const remove = useExpensesStore((state) => state.remove)
  const navigate = useNavigate()
  const [isPending, startTransition] = useTransition()
  const [actionError, setActionError] = useState<string | null>(null)

  async function save(input: ExpenseInput) {
    await update(expense.id, input)
    await navigate('/notes')
  }

  function runAction(action: () => Promise<unknown>) {
    setActionError(null)
    startTransition(async () => {
      try {
        await action()
        await navigate('/notes')
      } catch (error) {
        setActionError(error instanceof ApiError ? error.message : 'Action impossible.')
      }
    })
  }

  function handleDelete() {
    if (window.confirm('Supprimer définitivement ce brouillon ?')) {
      runAction(() => remove(expense.id))
    }
  }

  return (
    <div className="flex max-w-2xl flex-col gap-6">
      <Card title="Modifier le brouillon">
        <ExpenseForm
          categories={categories}
          initialValues={{ label: expense.label, amount: centsToEurosInput(expense.amountCents), date: expense.date, categorySlug: expense.category.slug }}
          submitLabel="Enregistrer les modifications"
          onSave={save}
        />
      </Card>
      <Card title="Soumission">
        <p className="text-sm text-gray-600">Une fois soumise, la note est transmise pour validation et ne peut plus être modifiée.</p>
        {actionError && <Alert className="mt-4">{actionError}</Alert>}
        <div className="mt-5 flex flex-wrap justify-between gap-3">
          <Button variant="danger" disabled={isPending} onClick={handleDelete}>
            Supprimer le brouillon
          </Button>
          <Button variant="primary" pending={isPending} onClick={() => runAction(() => submit(expense.id))}>
            Soumettre la note
          </Button>
        </div>
      </Card>
    </div>
  )
}
