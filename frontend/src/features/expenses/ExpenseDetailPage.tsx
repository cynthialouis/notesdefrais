import { Suspense, use, useActionState, useState, useTransition } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { ApiError } from '../../api/client'
import { Alert } from '../../ui/Alert'
import { Button } from '../../ui/Button'
import { Card } from '../../ui/Card'
import { Field, Textarea } from '../../ui/Field'
import { PageHeader } from '../../ui/PageHeader'
import { LoadingState } from '../../ui/Spinner'
import { useSessionStore } from '../auth/sessionStore'
import { loadCategories, type CategoriesResult } from './categories'
import { ExpenseForm } from './ExpenseForm'
import { useExpensesStore, type Expense, type ExpenseEvent, type ExpenseInput } from './expensesStore'
import { formatIsoDate, formatIsoDateTime } from './format'
import { centsToEurosInput, formatCents } from './money'
import { rejectionSchema } from './rejectionValidation'
import { StatusBadge } from './StatusBadge'
import { eventLabels } from './statusLabels'

type Result<T> = { ok: true; value: T } | { ok: false; message: string }
type BackLink = { to: string; label: string }

function settle<T>(promise: Promise<T>, notFound: string): Promise<Result<T>> {
  return promise
    .then((value): Result<T> => ({ ok: true, value }))
    .catch((error: unknown): Result<T> => ({
      ok: false,
      message: error instanceof ApiError && error.status === 404 ? notFound : 'Chargement impossible.',
    }))
}

/** Une instance par note : changer d'identifiant recrée la page et ses chargements. */
export function ExpenseDetailRoute({ back }: { back: BackLink }) {
  const { id = '' } = useParams()
  return <ExpenseDetailPage key={id} id={Number(id)} back={back} />
}

function ExpenseDetailPage({ id, back }: { id: number; back: BackLink }) {
  const fetchExpense = useExpensesStore((state) => state.fetchExpense)
  const fetchEvents = useExpensesStore((state) => state.fetchEvents)
  // Promesses créées par ce composant, qui ne se suspend pas : stables pour use()
  const [expense] = useState(() => settle(fetchExpense(id), 'Note introuvable.'))
  const [events] = useState(() => settle(fetchEvents(id), 'Historique introuvable.'))
  const [categories] = useState(loadCategories)

  return (
    <>
      <title>Note de frais – Notes de frais</title>
      <p className="mb-4">
        <Link to={back.to} className="rounded-xs text-sm font-medium text-gray-600 hover:text-gray-900 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500">
          ← {back.label}
        </Link>
      </p>
      <Suspense fallback={<LoadingState />}>
        <ExpenseDetail expense={expense} events={events} categories={categories} back={back} />
      </Suspense>
    </>
  )
}

function ExpenseDetail({
  expense: expensePromise,
  events,
  categories,
  back,
}: {
  expense: Promise<Result<Expense>>
  events: Promise<Result<ExpenseEvent[]>>
  categories: Promise<CategoriesResult>
  back: BackLink
}) {
  const result = use(expensePromise)
  const session = useSessionStore((state) => state.session)
  if (!result.ok) {
    return <Alert>{result.message}</Alert>
  }
  const expense = result.value
  const isOwn = session.status === 'authenticated' && session.user.id === expense.owner.id

  return (
    <>
      <PageHeader
        title={
          <span className="flex flex-wrap items-center gap-3">
            {expense.label} <StatusBadge status={expense.status} />
          </span>
        }
        description={isOwn ? undefined : `Note de ${expense.owner.name}`}
      />
      <div className="flex max-w-2xl flex-col gap-6">
        <DecisionSummary expense={expense} />
        {expense.editable ? <EditableExpense expense={expense} categories={categories} /> : <ReadOnlyExpense expense={expense} />}
        {expense.decidable && <DecisionCard expense={expense} back={back} />}
        <Suspense fallback={<LoadingState label="Chargement de l'historique…" />}>
          <HistoryCard events={events} />
        </Suspense>
      </div>
    </>
  )
}

/** Résultat d'une décision : le motif d'un rejet est montré au propriétaire (C8). */
function DecisionSummary({ expense }: { expense: Expense }) {
  if (expense.status === 'rejected') {
    return (
      <Alert tone="info">
        Rejetée par {expense.decidedBy.name} le {formatIsoDateTime(expense.decidedAt)} : « {expense.rejectionReason} »
      </Alert>
    )
  }
  if (expense.status === 'approved') {
    return (
      <Alert tone="info">
        Validée par {expense.decidedBy.name} le {formatIsoDateTime(expense.decidedAt)}.
      </Alert>
    )
  }
  return null
}

function ReadOnlyExpense({ expense }: { expense: Expense }) {
  const rows = [
    ['Montant', formatCents(expense.amountCents)],
    ['Date', formatIsoDate(expense.date)],
    ['Catégorie', expense.category.label],
  ]
  return (
    <Card>
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

/** Note modifiable (brouillon ou rejetée) : modifiable, soumettable, supprimable après confirmation. */
function EditableExpense({ expense, categories }: { expense: Expense; categories: Promise<CategoriesResult> }) {
  const update = useExpensesStore((state) => state.update)
  const submit = useExpensesStore((state) => state.submit)
  const remove = useExpensesStore((state) => state.remove)
  const navigate = useNavigate()
  const [isPending, startTransition] = useTransition()
  const [actionError, setActionError] = useState<string | null>(null)
  const isRejected = expense.status === 'rejected'

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
    if (window.confirm(isRejected ? 'Supprimer définitivement cette note ?' : 'Supprimer définitivement ce brouillon ?')) {
      runAction(() => remove(expense.id))
    }
  }

  return (
    <>
      <Card title={isRejected ? 'Corriger la note' : 'Modifier le brouillon'}>
        <ExpenseForm
          categories={categories}
          initialValues={{ label: expense.label, amount: centsToEurosInput(expense.amountCents), date: expense.date, categorySlug: expense.category.slug }}
          submitLabel="Enregistrer les modifications"
          onSave={save}
        />
      </Card>
      <Card title="Soumission">
        <p className="text-sm text-gray-600">
          {isRejected
            ? 'Après correction, soumettez la note à nouveau : elle repart pour validation.'
            : 'Une fois soumise, la note est transmise pour validation et ne peut plus être modifiée.'}
        </p>
        {actionError && <Alert className="mt-4">{actionError}</Alert>}
        <div className="mt-5 flex flex-wrap justify-between gap-3">
          <Button variant="danger" disabled={isPending} onClick={handleDelete}>
            {isRejected ? 'Supprimer la note' : 'Supprimer le brouillon'}
          </Button>
          <Button variant="primary" pending={isPending} onClick={() => runAction(() => submit(expense.id))}>
            Soumettre la note
          </Button>
        </div>
      </Card>
    </>
  )
}

type RejectState = { reason: string; error: string | null }

/** Décision du responsable : valider, ou rejeter avec un motif (C3, C4, C5). */
function DecisionCard({ expense, back }: { expense: Expense; back: BackLink }) {
  const approve = useExpensesStore((state) => state.approve)
  const reject = useExpensesStore((state) => state.reject)
  const navigate = useNavigate()
  const [isApproving, startApprove] = useTransition()
  const [approveError, setApproveError] = useState<string | null>(null)

  function handleApprove() {
    setApproveError(null)
    startApprove(async () => {
      try {
        await approve(expense.id)
        await navigate(back.to)
      } catch (error) {
        setApproveError(error instanceof ApiError ? error.message : 'Validation impossible.')
      }
    })
  }

  const [state, rejectAction, isRejecting] = useActionState(
    async (_previous: RejectState, formData: FormData): Promise<RejectState> => {
      const raw = formData.get('reason')
      const reason = typeof raw === 'string' ? raw : ''
      const parsed = rejectionSchema.safeParse({ reason })
      if (!parsed.success) {
        return { reason, error: parsed.error.issues[0]?.message ?? 'Motif invalide.' } // C5 : rien n'est envoyé
      }
      try {
        await reject(expense.id, parsed.data.reason)
      } catch (error) {
        const message = error instanceof ApiError ? (error.fields.reason ?? error.message) : 'Rejet impossible.'
        return { reason, error: message }
      }
      await navigate(back.to)
      return { reason, error: null }
    },
    { reason: '', error: null },
  )

  const busy = isApproving || isRejecting

  return (
    <Card title="Décision">
      <div className="flex flex-col gap-6">
        <div>
          <p className="text-sm text-gray-600">La note est conforme : validez-la pour qu'elle soit remboursée.</p>
          {approveError && <Alert className="mt-4">{approveError}</Alert>}
          <Button variant="primary" className="mt-4" pending={isApproving} disabled={busy} onClick={handleApprove}>
            Valider la note
          </Button>
        </div>
        <form action={rejectAction} noValidate className="flex flex-col gap-4 border-t border-gray-200 pt-6">
          <Field label="Motif du rejet" error={state.error ?? undefined} hint="Expliquez ce qu'il faut corriger (5 caractères au moins).">
            {(control) => <Textarea {...control} name="reason" defaultValue={state.reason} />}
          </Field>
          <div>
            <Button type="submit" variant="danger" pending={isRejecting} disabled={busy}>
              Rejeter la note
            </Button>
          </div>
        </form>
      </div>
    </Card>
  )
}

/** Historique de la note, du plus ancien au plus récent (C10, C11). */
function HistoryCard({ events: eventsPromise }: { events: Promise<Result<ExpenseEvent[]>> }) {
  const result = use(eventsPromise)

  return (
    <Card title="Historique">
      {!result.ok ? (
        <p className="text-sm text-gray-600">{result.message}</p>
      ) : result.value.length === 0 ? (
        <p className="text-sm text-gray-600">Aucune action enregistrée.</p>
      ) : (
        <ol className="flex flex-col gap-4">
          {result.value.map((event) => (
            <li key={`${event.createdAt}-${event.type}-${event.actor.id}`} className="border-l-2 border-gray-200 pl-4">
              <p className="text-sm font-medium text-gray-900">{eventLabels[event.type]}</p>
              <p className="text-xs text-gray-500">
                {event.actor.name}, le {formatIsoDateTime(event.createdAt)}
              </p>
              {event.comment && <p className="mt-1 text-sm text-gray-700">« {event.comment} »</p>}
            </li>
          ))}
        </ol>
      )}
    </Card>
  )
}
