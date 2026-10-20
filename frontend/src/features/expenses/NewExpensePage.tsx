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
    const created = await create(input) // C1
    await navigate(`/notes/${created.id}`) // spec 005, C13 : le justificatif se joint sur le détail
  }

  return (
    <>
      <title>Nouvelle note – Espace salarié</title>
      <PageHeader title="Nouvelle note" description="La note est enregistrée en brouillon : vous pourrez ensuite joindre le justificatif, la modifier et la soumettre." />
      <Card className="max-w-2xl">
        <Suspense fallback={<LoadingState />}>
          <ExpenseForm categories={categories} initialValues={emptyValues} submitLabel="Enregistrer le brouillon" onSave={save} />
        </Suspense>
      </Card>
    </>
  )
}
