import { Suspense, useState } from 'react'
import { useNavigate } from 'react-router'
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
    <section>
      <title>Nouvelle note – Notes de frais</title>
      <h1>Nouvelle note</h1>
      <Suspense fallback={<p role="status">Chargement…</p>}>
        <ExpenseForm categories={categories} initialValues={emptyValues} submitLabel="Enregistrer le brouillon" onSave={save} />
      </Suspense>
    </section>
  )
}
