import { useEffect, useState } from 'react'
import { Alert } from '../../ui/Alert'
import { Button } from '../../ui/Button'
import { Card } from '../../ui/Card'
import { EmptyState } from '../../ui/EmptyState'
import { LoadingState } from '../../ui/Spinner'
import { leavePeriod } from './leavePeriod'
import { ReasonForm } from './ReasonForm'
import { useLeavesStore, type Leave } from './leavesStore'

/** Demandes en attente de tout le monde, date de début la plus proche d'abord (C18), à annuler avec un motif (C9, C13, C14). */
export function AllPendingLeavesCard() {
  const list = useLeavesStore((state) => state.allPending)
  const fetchAllPending = useLeavesStore((state) => state.fetchAllPending)

  // Synchronisation avec le serveur à l'arrivée sur la page (effet légitime : système extérieur)
  useEffect(() => {
    void fetchAllPending()
  }, [fetchAllPending])

  return (
    <Card title="Demandes en attente (toutes équipes)">
      {list.status === 'error' && <Alert>{list.message}</Alert>}
      {(list.status === 'idle' || list.status === 'loading') && <LoadingState label="Chargement des demandes en attente…" />}
      {list.status === 'ready' && list.leaves.length === 0 && (
        <EmptyState title="Aucune demande en attente." description="Les demandes de congé en attente de toutes les équipes apparaîtront ici." />
      )}
      {list.status === 'ready' && list.leaves.length > 0 && (
        <ul aria-label="Demandes en attente (toutes équipes)" className="-my-3 divide-y divide-gray-100">
          {list.leaves.map((leave) => (
            <PendingLeaveItem key={leave.id} leave={leave} />
          ))}
        </ul>
      )}
    </Card>
  )
}

function PendingLeaveItem({ leave }: { leave: Leave }) {
  const cancel = useLeavesStore((state) => state.cancel)
  const [cancelling, setCancelling] = useState(false)

  return (
    <li className="flex flex-col gap-3 py-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm">
          <span className="font-medium text-gray-900">{leave.requester.name}</span>
          <span className="text-gray-600"> · {leavePeriod(leave)}</span>
        </p>
        {leave.cancellable && !cancelling && (
          <Button onClick={() => setCancelling(true)} aria-label={`Annuler la demande de ${leave.requester.name} (${leavePeriod(leave)})`}>
            Annuler
          </Button>
        )}
      </div>
      {cancelling && (
        <ReasonForm
          label={`Motif de l'annulation pour ${leave.requester.name}`}
          hint="Expliquez pourquoi (5 caractères au moins). Le demandeur verra ce motif."
          submitLabel="Annuler la demande"
          onSubmit={async (reason) => {
            await cancel(leave.id, reason)
          }}
          onClose={() => setCancelling(false)}
        />
      )}
    </li>
  )
}
