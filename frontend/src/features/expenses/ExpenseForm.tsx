import { use, useActionState } from 'react'
import { ApiError } from '../../api/client'
import type { CategoriesResult } from './categories'
import {
  parseExpenseForm,
  serverFieldsToFormErrors,
  todayIso,
  type ExpenseFormErrors,
  type ExpenseFormField,
  type ExpenseFormValues,
} from './expenseFormValidation'
import type { ExpenseInput } from './expensesStore'

type Props = {
  categories: Promise<CategoriesResult>
  initialValues: ExpenseFormValues
  submitLabel: string
  /** Enregistre la note ; lève une ApiError si le serveur refuse. */
  onSave: (input: ExpenseInput) => Promise<void>
}

type FormState = { values: ExpenseFormValues; errors: ExpenseFormErrors; message: string | null }

function readValues(formData: FormData): ExpenseFormValues {
  const read = (name: ExpenseFormField) => {
    const value = formData.get(name)
    return typeof value === 'string' ? value : ''
  }
  return { label: read('label'), amount: read('amount'), date: read('date'), categorySlug: read('categorySlug') }
}

/** Formulaire commun à la création et à la modification d'une note (C3, C4, C5, C10). */
export function ExpenseForm({ categories, initialValues, submitLabel, onSave }: Props) {
  const categoriesResult = use(categories)

  const [state, formAction, isPending] = useActionState(
    async (_previous: FormState, formData: FormData): Promise<FormState> => {
      const values = readValues(formData)
      const parsed = parseExpenseForm(values)
      if (!parsed.ok) {
        return { values, errors: parsed.errors, message: null } // C4 : rien n'est envoyé
      }
      try {
        await onSave(parsed.input)
        return { values, errors: {}, message: null }
      } catch (error) {
        if (error instanceof ApiError && Object.keys(error.fields).length > 0) {
          return { values, errors: serverFieldsToFormErrors(error.fields), message: null }
        }
        return { values, errors: {}, message: error instanceof ApiError ? error.message : 'Enregistrement impossible.' }
      }
    },
    { values: initialValues, errors: {}, message: null },
  )

  if (!categoriesResult.ok) {
    return <p role="alert">{categoriesResult.message}</p>
  }

  const { values, errors } = state
  const describedBy = (field: ExpenseFormField) => (errors[field] ? `${field}-error` : undefined)
  const fieldError = (field: ExpenseFormField) =>
    errors[field] && (
      <p id={`${field}-error`} className="field-error">
        {errors[field]}
      </p>
    )

  // Les valeurs saisies sont reprises en defaultValue : le formulaire est réinitialisé après chaque action
  return (
    <form action={formAction} className="expense-form" noValidate>
      <div className="field">
        <label htmlFor="label">Libellé</label>
        <input id="label" name="label" defaultValue={values.label} aria-invalid={errors.label ? true : undefined} aria-describedby={describedBy('label')} />
        {fieldError('label')}
      </div>
      <div className="field">
        <label htmlFor="amount">Montant (€)</label>
        <input id="amount" name="amount" inputMode="decimal" placeholder="12,50" defaultValue={values.amount} aria-invalid={errors.amount ? true : undefined} aria-describedby={describedBy('amount')} />
        {fieldError('amount')}
      </div>
      <div className="field">
        <label htmlFor="date">Date</label>
        <input id="date" name="date" type="date" max={todayIso()} defaultValue={values.date} aria-invalid={errors.date ? true : undefined} aria-describedby={describedBy('date')} />
        {fieldError('date')}
      </div>
      <div className="field">
        <label htmlFor="categorySlug">Catégorie</label>
        <select id="categorySlug" name="categorySlug" defaultValue={values.categorySlug} aria-invalid={errors.categorySlug ? true : undefined} aria-describedby={describedBy('categorySlug')}>
          <option value="">Choisir une catégorie</option>
          {categoriesResult.categories.map((category) => (
            <option key={category.slug} value={category.slug}>
              {category.label}
            </option>
          ))}
        </select>
        {fieldError('categorySlug')}
      </div>
      {state.message && <p role="alert">{state.message}</p>}
      <button type="submit" disabled={isPending}>
        {isPending ? 'Enregistrement…' : submitLabel}
      </button>
    </form>
  )
}
