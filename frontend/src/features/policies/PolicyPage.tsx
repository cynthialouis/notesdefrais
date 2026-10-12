import { Card } from '../../ui/Card'
import { EmptyState } from '../../ui/EmptyState'
import { PageHeader } from '../../ui/PageHeader'

export function PolicyPage() {
  return (
    <>
      <title>Politique de dépenses – Notes de frais</title>
      <PageHeader title="Politique de dépenses" />
      <Card>
        <EmptyState title="À venir." description="Le réglage des plafonds par catégorie arrive avec une prochaine version." />
      </Card>
    </>
  )
}
