import { useEffect, useState, useTransition } from 'react'
import { ApiError } from '../../api/client'
import { Alert } from '../../ui/Alert'
import { Button } from '../../ui/Button'
import { Card } from '../../ui/Card'
import { EmptyState } from '../../ui/EmptyState'
import { LoadingState } from '../../ui/Spinner'
import { leavePeriod } from './leavePeriod'
import { ReasonForm } from './ReasonForm'
import { useLeavesStore, type Leave } from './leavesStore'

/** Demandes en attente de l'équipe directe, date de début la plus proche d'abord (C6), à accepter ou refuser (C7 à C10). */
export function TeamLeavesCard() {
  const list = useLeavesStore((state) => state.team)
  const fetchTeam = useLeavesStore((state) => state.fetchTeam)

  // Synchronisation avec le serveur à l'arrivée sur la page (effet légitime : système extérieur)
  useEffect(() => {
    void fetchTeam()
  }, [fetchTeam])

  return (
    <Card title="Demandes à traiter">
      {list.status === 'error' && <Alert>{list.message}</Alert>}
      {(list.status === 'idle' || list.status === 'loading') && <LoadingState label="Chargement des demandes à traiter…" />}
      {list.status === 'ready' && list.leaves.length === 0 && (
        <EmptyState title="Aucune demande à traiter." description="Les demandes de congé de votre équipe apparaîtront ici." />
      )}
      {list.status === 'ready' && list.leaves.length > 0 && (
        <ul aria-label="Demandes à traiter" className="-my-3 divide-y divide-gray-100">
          {list.leaves.map((leave) => (
            <TeamLeaveItem key={leave.id} leave={leave} />
          ))}
        </ul>
      )}
    </Card>
  )
}

function TeamLeaveItem({ leave }: { leave: Leave }) {
  const accept = useLeavesStore((state) => state.accept)
  const refuse = useLeavesStore((state) => state.refuse)
  const [isAccepting, startAccept] = useTransition()
  const [error, setError] = useState<string | null>(null)
  const [refusing, setRefusing] = useState(false)
  const subject = `la demande de ${leave.requester.name} (${leavePeriod(leave)})`

  function handleAccept() {
    setError(null)
    startAccept(async () => {
      try {
        await accept(leave.id)
      } catch (caught) {
        setError(caught instanceof ApiError ? caught.message : 'Acceptation impossible.') // C10
      }
    })
  }

  return (
    <li className="flex flex-col gap-3 py-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm">
          <span className="font-medium text-gray-900">{leave.requester.name}</span>
          <span className="text-gray-600"> · {leavePeriod(leave)}</span>
        </p>
        {leave.decidable && !refusing && (
          <div className="flex flex-wrap gap-2">
            <Button variant="primary" pending={isAccepting} onClick={handleAccept} aria-label={`Accepter ${subject}`}>
              Accepter
            </Button>
            <Button disabled={isAccepting} onClick={() => setRefusing(true)} aria-label={`Refuser ${subject}`}>
              Refuser
            </Button>
          </div>
        )}
      </div>
      {error && <Alert>{error}</Alert>}
      {refusing && (
        <ReasonForm
          label={`Motif du refus pour ${leave.requester.name}`}
          hint="Expliquez pourquoi (5 caractères au moins). Le demandeur pourra faire une nouvelle demande."
          submitLabel="Refuser la demande"
          onSubmit={async (reason) => {
            await refuse(leave.id, reason)
          }}
          onClose={() => setRefusing(false)}
        />
      )}
    </li>
  )
}
