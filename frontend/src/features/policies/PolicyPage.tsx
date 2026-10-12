import { Suspense, use, useActionState, useState } from 'react'
import { ApiError, apiFetch } from '../../api/client'
import { Alert } from '../../ui/Alert'
import { Button } from '../../ui/Button'
import { Card } from '../../ui/Card'
import { Field, Input } from '../../ui/Field'
import { PageHeader } from '../../ui/PageHeader'
import { LoadingState } from '../../ui/Spinner'
import { loadCategories, type CategoriesResult, type Category } from '../expenses/categories'
import { centsToEurosInput } from '../expenses/money'
import { policyFormSchema, type PolicyFormValues } from './policyForm'

/** Politique de dépense par catégorie, réglée par l'admin (spec 004, C1 à C3). */
export function PolicyPage() {
  // Promesse créée par ce composant, qui ne se suspend pas : stable pour use()
  const [categories] = useState(loadCategories)

  return (
    <>
      <title>Politique de dépenses – Notes de frais</title>
      <PageHeader
        title="Politique de dépenses"
        description="Plafond par note et justificatif obligatoire, par catégorie. Un dépassement est signalé sans bloquer la note."
      />
      <Card>
        <Suspense fallback={<LoadingState />}>
          <PolicyList categories={categories} />
        </Suspense>
      </Card>
    </>
  )
}

function PolicyList({ categories }: { categories: Promise<CategoriesResult> }) {
  const result = use(categories)
  if (!result.ok) {
    return <Alert>{result.message}</Alert>
  }
  return (
    <div className="divide-y divide-gray-100">
      {result.categories.map((category) => (
        <PolicyRow key={category.slug} category={category} />
      ))}
    </div>
  )
}

type RowState = { values: PolicyFormValues; error: string | null; saved: boolean }

function PolicyRow({ category }: { category: Category }) {
  const [state, formAction, isPending] = useActionState(
    async (_previous: RowState, formData: FormData): Promise<RowState> => {
      const raw = formData.get('maxAmount')
      const values: PolicyFormValues = { maxAmount: typeof raw === 'string' ? raw : '', receiptRequired: formData.get('receiptRequired') === 'on' }
      const parsed = policyFormSchema.safeParse(values)
      if (!parsed.success) {
        return { values, error: parsed.error.issues[0]?.message ?? 'Plafond invalide.', saved: false }
      }
      try {
        await apiFetch(`/policies/${category.slug}`, {
          method: 'PUT',
          body: JSON.stringify({ maxAmountCents: parsed.data.maxAmount, receiptRequired: parsed.data.receiptRequired }),
        })
      } catch (error) {
        const message = error instanceof ApiError ? (error.fields.maxAmountCents ?? error.message) : 'Enregistrement impossible.'
        return { values, error: message, saved: false }
      }
      return { values, error: null, saved: true }
    },
    {
      values: {
        maxAmount: category.maxAmountCents != null ? centsToEurosInput(category.maxAmountCents) : '',
        receiptRequired: category.receiptRequired,
      },
      error: null,
      saved: false,
    },
  )

  return (
    <form action={formAction} noValidate className="py-4 first:pt-0 last:pb-0">
      <fieldset className="grid items-start gap-4 sm:grid-cols-[10rem_1fr_auto_auto]">
        <legend className="mb-2 text-sm font-semibold text-gray-900 sm:float-left sm:mb-0 sm:pt-2">{category.label}</legend>
        <Field label="Plafond (€)" error={state.error ?? undefined} hint="Vide : aucun plafond.">
          {(control) => <Input {...control} name="maxAmount" inputMode="decimal" defaultValue={state.values.maxAmount} placeholder="Aucun" />}
        </Field>
        <label className="flex items-center gap-2 pt-7 text-sm text-gray-700">
          <input type="checkbox" name="receiptRequired" defaultChecked={state.values.receiptRequired} className="size-4 accent-brand-600" />
          Justificatif obligatoire
        </label>
        <div className="pt-6">
          <Button type="submit" pending={isPending}>
            Enregistrer
          </Button>
        </div>
      </fieldset>
      {state.saved && (
        <Alert tone="info" className="mt-3">
          Politique enregistrée pour {category.label}.
        </Alert>
      )}
    </form>
  )
}
