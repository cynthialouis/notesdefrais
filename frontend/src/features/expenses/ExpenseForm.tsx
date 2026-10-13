import { use, useActionState, useState } from 'react'
import { ApiError } from '../../api/client'
import { Alert } from '../../ui/Alert'
import { Button } from '../../ui/Button'
import { Field, Input, Select } from '../../ui/Field'
import type { CategoriesResult } from './categories'
import {
  parseExpenseForm,
  serverFieldsToFormErrors,
  todayIso,
  type ExpenseFormErrors,
  type ExpenseFormField,
  type ExpenseFormValues,
} from './expenseFormValidation'
import { overLimitByCents } from '../policies/checkPolicy'
import type { ExpenseInput } from './expensesStore'
import { formatCents, parseEurosToCents } from './money'

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

  // Montant et catégorie suivis pendant la saisie pour l'alerte de politique (les champs restent non contrôlés)
  const [live, setLive] = useState({ amount: initialValues.amount, categorySlug: initialValues.categorySlug })

  if (!categoriesResult.ok) {
    return <Alert>{categoriesResult.message}</Alert>
  }

  const { values, errors } = state

  // Calculés pendant le rendu, sans effet (spec 004, C4 et C8) ; le serveur refait le contrôle (C7)
  const category = categoriesResult.categories.find((candidate) => candidate.slug === live.categorySlug)
  const amountCents = parseEurosToCents(live.amount)
  const overBy = amountCents === null || !category ? null : overLimitByCents(amountCents, category.maxAmountCents)

  // Les valeurs saisies sont reprises en defaultValue : le formulaire est réinitialisé après chaque action
  return (
    <form action={formAction} className="flex flex-col gap-5" noValidate>
      <Field label="Libellé" error={errors.label}>
        {(control) => <Input {...control} name="label" defaultValue={values.label} placeholder="Ex. Train Paris – Lyon" />}
      </Field>
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="Montant (€)" error={errors.amount} hint="Deux décimales au plus, ex. 12,50.">
          {(control) => (
            <Input
              {...control}
              name="amount"
              inputMode="decimal"
              defaultValue={values.amount}
              placeholder="12,50"
              onChange={(event) => setLive((current) => ({ ...current, amount: event.target.value }))}
            />
          )}
        </Field>
        <Field label="Date" error={errors.date}>
          {(control) => <Input {...control} name="date" type="date" max={todayIso()} defaultValue={values.date} />}
        </Field>
      </div>
      <Field
        label="Catégorie"
        error={errors.categorySlug}
        hint={category?.receiptRequired ? 'Justificatif obligatoire pour cette catégorie.' : undefined}
      >
        {(control) => (
          <Select
            {...control}
            name="categorySlug"
            defaultValue={values.categorySlug}
            onChange={(event) => setLive((current) => ({ ...current, categorySlug: event.target.value }))}
          >
            <option value="">Choisir une catégorie</option>
            {categoriesResult.categories.map((category) => (
              <option key={category.slug} value={category.slug}>
                {category.label}
              </option>
            ))}
          </Select>
        )}
      </Field>
      {overBy !== null && category?.maxAmountCents != null && (
        <Alert tone="warning">
          Hors politique : le plafond de {formatCents(category.maxAmountCents)} pour la catégorie {category.label} est dépassé de {formatCents(overBy)}. La note
          peut quand même être enregistrée et soumise.
        </Alert>
      )}
      {state.message && <Alert>{state.message}</Alert>}
      <div className="flex justify-end border-t border-gray-200 pt-5">
        <Button type="submit" variant="primary" pending={isPending}>
          {isPending ? 'Enregistrement…' : submitLabel}
        </Button>
      </div>
    </form>
  )
}
