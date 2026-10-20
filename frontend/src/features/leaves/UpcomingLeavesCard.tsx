import { useEffect, useState } from 'react'
import { Alert } from '../../ui/Alert'
import { Button } from '../../ui/Button'
import { Card } from '../../ui/Card'
import { EmptyState } from '../../ui/EmptyState'
import { LoadingState } from '../../ui/Spinner'
import { Table, Td, Th } from '../../ui/Table'
import { leavePeriod } from './leavePeriod'
import { ReasonForm } from './ReasonForm'
import { useLeavesStore, type Leave } from './leavesStore'

/**
 * Congés acceptés en cours ou à venir de tout le monde, date de début la plus proche d'abord (C15) ;
 * seules les demandes acceptées y figurent, sans motif (C17). L'admin peut les annuler avec un motif (C13).
 */
export function UpcomingLeavesCard() {
  const list = useLeavesStore((state) => state.accepted)
  const fetchAccepted = useLeavesStore((state) => state.fetchAccepted)

  // Synchronisation avec le serveur à l'arrivée sur la page (effet légitime : système extérieur)
  useEffect(() => {
    void fetchAccepted()
  }, [fetchAccepted])

  // La colonne Action n'existe que si le lecteur peut annuler au moins un congé (l'admin)
  const withAction = list.status === 'ready' && list.leaves.some((leave) => leave.cancellable)

  return (
    <Card title="Congés à venir">
      {list.status === 'error' && <Alert>{list.message}</Alert>}
      {(list.status === 'idle' || list.status === 'loading') && <LoadingState label="Chargement des congés…" />}
      {list.status === 'ready' && list.leaves.length === 0 && (
        <EmptyState title="Aucun congé en cours ou à venir." description="Les congés acceptés de chacun apparaîtront ici." />
      )}
      {list.status === 'ready' && list.leaves.length > 0 && (
        <Table label="Congés à venir">
          <thead>
            <tr>
              <Th>Personne</Th>
              <Th>Période</Th>
              {withAction && <Th align="right">Action</Th>}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {list.leaves.map((leave) => (
              <UpcomingLeaveRows key={leave.id} leave={leave} withAction={withAction} />
            ))}
          </tbody>
        </Table>
      )}
    </Card>
  )
}

/** Une ligne par congé, suivie pendant une annulation d'une ligne pour le motif, sur toute la largeur. */
function UpcomingLeaveRows({ leave, withAction }: { leave: Leave; withAction: boolean }) {
  const cancel = useLeavesStore((state) => state.cancel)
  const [cancelling, setCancelling] = useState(false)

  return (
    <>
      <tr>
        <Td className="font-medium text-gray-900">{leave.requester.name}</Td>
        <Td>{leavePeriod(leave)}</Td>
        {withAction && (
          <Td align="right">
            {leave.cancellable && !cancelling && (
              <Button onClick={() => setCancelling(true)} aria-label={`Annuler le congé de ${leave.requester.name} (${leavePeriod(leave)})`}>
                Annuler
              </Button>
            )}
          </Td>
        )}
      </tr>
      {cancelling && (
        <tr>
          <Td wrap colSpan={3}>
            <ReasonForm
              label={`Motif de l'annulation pour ${leave.requester.name}`}
              hint="Expliquez pourquoi (5 caractères au moins). Le demandeur verra ce motif."
              submitLabel="Annuler le congé"
              onSubmit={async (reason) => {
                await cancel(leave.id, reason)
              }}
              onClose={() => setCancelling(false)}
            />
          </Td>
        </tr>
      )}
    </>
  )
}
