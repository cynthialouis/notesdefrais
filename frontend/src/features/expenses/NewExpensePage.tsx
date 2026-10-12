import { Suspense, useState } from 'react'
import { useNavigate } from 'react-router'
import { Card } from '../../ui/Card'
import { PageHeader } from '../../ui/PageHeader'
import { LoadingState } from '../../ui/Spinner'
import { loadCategories } from './categories'
import { ExpenseForm } from './ExpenseForm'
import { useExpensesStore, type ExpenseInput } from './expensesStore'

const emptyValues = { label: '', amount: '', date: '', categorySlug: '' }

export function NewExpensePage() {
  // Promesse créée par ce composant, qui ne se suspend pas : stable pour use() dans le formulaire
  const [categories] = useState(loadCategories)
  const create = useExpensesStore((state) => state.create)
  const navigate = useNavigate()

  async function save(input: ExpenseInput) {
    await create(input) // C1
    await navigate('/notes')
  }

  return (
    <>
      <title>Nouvelle note – Notes de frais</title>
      <PageHeader title="Nouvelle note" description="La note est enregistrée en brouillon : vous pourrez la modifier avant de la soumettre." />
      <Card className="max-w-2xl">
        <Suspense fallback={<LoadingState />}>
          <ExpenseForm categories={categories} initialValues={emptyValues} submitLabel="Enregistrer le brouillon" onSave={save} />
        </Suspense>
      </Card>
    </>
  )
}
