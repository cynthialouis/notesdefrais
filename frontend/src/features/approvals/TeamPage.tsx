import { Card } from '../../ui/Card'
import { EmptyState } from '../../ui/EmptyState'
import { PageHeader } from '../../ui/PageHeader'

export function TeamPage() {
  return (
    <>
      <title>Équipe – Notes de frais</title>
      <PageHeader title="Équipe" />
      <Card>
        <EmptyState title="À venir." description="La validation des notes de votre équipe arrive avec la prochaine version." />
      </Card>
    </>
  )
}
