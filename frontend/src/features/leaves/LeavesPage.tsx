import { useEffect, useState, useTransition } from 'react'
import { ApiError } from '../../api/client'
import { Alert } from '../../ui/Alert'
import { Button } from '../../ui/Button'
import { Card } from '../../ui/Card'
import { EmptyState } from '../../ui/EmptyState'
import { PageHeader } from '../../ui/PageHeader'
import { LoadingState } from '../../ui/Spinner'
import { Table, Td, Th } from '../../ui/Table'
import { canAccess, useSessionStore } from '../auth/sessionStore'
import { leavePeriod } from './leavePeriod'
import { LeaveRequestForm } from './LeaveRequestForm'
import { LeaveStatusBadge } from './LeaveStatusBadge'
import { useLeavesStore, type Leave } from './leavesStore'
import { TeamLeavesCard } from './TeamLeavesCard'

/** Page des congés (spec 007), ouverte à tous les rôles. */
export function LeavesPage() {
  const session = useSessionStore((state) => state.session)

  return (
    <>
      <title>Congés – Notes de frais</title>
      <PageHeader title="Congés" description="Demandez un congé et suivez vos demandes." />
      <div className="flex flex-col gap-6">
        <Card title="Demander un congé">
          <LeaveRequestForm />
        </Card>
        {canAccess(session, 'manager') && <TeamLeavesCard />}
        <MyLeavesCard />
      </div>
    </>
  )
}

/** Motif d'un refus ou d'une annulation, montré au demandeur (C16). */
function reasonOf(leave: Leave): string | null {
  switch (leave.status) {
    case 'refused':
      return `Refusée par ${leave.decidedBy.name} : « ${leave.refusalReason} »`
    case 'cancelled':
      return `Annulée par ${leave.cancelledBy.name} : « ${leave.cancellationReason} »`
    default:
      return null
  }
}

/** Toutes ses demandes, quel que soit leur état, début le plus récent d'abord (C16) ; retrait si en attente (C11, C12). */
function MyLeavesCard() {
  const list = useLeavesStore((state) => state.mine)
  const fetchMine = useLeavesStore((state) => state.fetchMine)
  const withdraw = useLeavesStore((state) => state.withdraw)
  const [isPending, startTransition] = useTransition()
  const [actionError, setActionError] = useState<string | null>(null)

  // Synchronisation avec le serveur à l'arrivée sur la page (effet légitime : système extérieur)
  useEffect(() => {
    void fetchMine()
  }, [fetchMine])

  function handleWithdraw(id: number) {
    setActionError(null)
    startTransition(async () => {
      try {
        await withdraw(id)
      } catch (error) {
        setActionError(error instanceof ApiError ? error.message : 'Retrait impossible.') // C12
      }
    })
  }

  return (
    <Card title="Mes demandes">
      {actionError && <Alert className="mb-4">{actionError}</Alert>}
      {list.status === 'error' && <Alert>{list.message}</Alert>}
      {(list.status === 'idle' || list.status === 'loading') && <LoadingState label="Chargement des demandes…" />}
      {list.status === 'ready' && list.leaves.length === 0 && (
        <EmptyState title="Vous n'avez encore fait aucune demande." description="Choisissez une période ci-dessus pour demander un congé." />
      )}
      {list.status === 'ready' && list.leaves.length > 0 && (
        <Table label="Mes demandes">
          <thead>
            <tr>
              <Th>Période</Th>
              <Th>État</Th>
              <Th>Détail</Th>
              <Th align="right">Action</Th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {list.leaves.map((leave) => (
              <tr key={leave.id}>
                <Td className="font-medium text-gray-900">{leavePeriod(leave)}</Td>
                <Td>
                  <LeaveStatusBadge status={leave.status} />
                </Td>
                <Td wrap>{reasonOf(leave)}</Td>
                <Td align="right">
                  {leave.withdrawable && (
                    <Button disabled={isPending} onClick={() => handleWithdraw(leave.id)} aria-label={`Retirer la demande : ${leavePeriod(leave)}`}>
                      Retirer
                    </Button>
                  )}
                </Td>
              </tr>
            ))}
          </tbody>
        </Table>
      )}
    </Card>
  )
}
