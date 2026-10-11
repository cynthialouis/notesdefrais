import { roleLabels } from '../auth/roles'
import { useSessionStore } from '../auth/sessionStore'

export function DashboardPage() {
  const session = useSessionStore((state) => state.session)

  return (
    <section>
      <title>Tableau de bord – Notes de frais</title>
      <h1>Tableau de bord</h1>
      {session.status === 'authenticated' && (
        <p>
          Connecté en tant que {session.user.name}, {roleLabels[session.user.role].toLowerCase()}.
        </p>
      )}
    </section>
  )
}
