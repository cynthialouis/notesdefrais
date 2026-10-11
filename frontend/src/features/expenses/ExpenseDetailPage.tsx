import { Suspense, use, useState, useTransition } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError } from '../../api/client'
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
    <section>
      <title>Note de frais – Notes de frais</title>
      <p>
        <Link to="/notes">← Mes notes</Link>
      </p>
      <Suspense fallback={<p role="status">Chargement…</p>}>
        <ExpenseDetail expense={expense} categories={categories} />
      </Suspense>
    </section>
  )
}

function ExpenseDetail({ expense: expensePromise, categories }: { expense: Promise<ExpenseResult>; categories: Promise<CategoriesResult> }) {
  const result = use(expensePromise)
  if (!result.ok) {
    return <p role="alert">{result.message}</p>
  }
  const { expense } = result

  return (
    <>
      <h1>
        {expense.label} <StatusBadge status={expense.status} />
      </h1>
      {expense.status === 'draft' ? <DraftActions expense={expense} categories={categories} /> : <ReadOnlyExpense expense={expense} />}
    </>
  )
}

function ReadOnlyExpense({ expense }: { expense: Expense }) {
  return (
    <dl className="expense-details">
      <dt>Montant</dt>
      <dd>{formatCents(expense.amountCents)}</dd>
      <dt>Date</dt>
      <dd>{formatIsoDate(expense.date)}</dd>
      <dt>Catégorie</dt>
      <dd>{expense.category.label}</dd>
    </dl>
  )
}

/** Brouillon : modifiable (C5), soumettable (C6), supprimable après confirmation (C11). */
function DraftActions({ expense, categories }: { expense: Expense & { status: 'draft' }; categories: Promise<CategoriesResult> }) {
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
    <>
      <ExpenseForm
        categories={categories}
        initialValues={{ label: expense.label, amount: centsToEurosInput(expense.amountCents), date: expense.date, categorySlug: expense.category.slug }}
        submitLabel="Enregistrer les modifications"
        onSave={save}
      />
      <div className="draft-actions">
        <button type="button" disabled={isPending} onClick={() => runAction(() => submit(expense.id))}>
          Soumettre la note
        </button>
        <button type="button" className="danger" disabled={isPending} onClick={handleDelete}>
          Supprimer le brouillon
        </button>
      </div>
      {actionError && <p role="alert">{actionError}</p>}
    </>
  )
}
