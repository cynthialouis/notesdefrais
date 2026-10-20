import { useEffect } from 'react'
import { Alert } from '../../ui/Alert'
import { Card } from '../../ui/Card'
import { EmptyState } from '../../ui/EmptyState'
import { LoadingState } from '../../ui/Spinner'
import { Table, Td, Th } from '../../ui/Table'
import { leavePeriod } from './leavePeriod'
import { useLeavesStore } from './leavesStore'

/**
 * Congés acceptés en cours ou à venir de tout le monde, date de début la plus proche d'abord (C15) ;
 * seules les demandes acceptées y figurent, sans motif (C17).
 */
export function UpcomingLeavesCard() {
  const list = useLeavesStore((state) => state.accepted)
  const fetchAccepted = useLeavesStore((state) => state.fetchAccepted)

  // Synchronisation avec le serveur à l'arrivée sur la page (effet légitime : système extérieur)
  useEffect(() => {
    void fetchAccepted()
  }, [fetchAccepted])

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
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {list.leaves.map((leave) => (
              <tr key={leave.id}>
                <Td className="font-medium text-gray-900">{leave.requester.name}</Td>
                <Td>{leavePeriod(leave)}</Td>
              </tr>
            ))}
          </tbody>
        </Table>
      )}
    </Card>
  )
}
