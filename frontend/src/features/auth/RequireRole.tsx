import { Navigate, Outlet } from 'react-router'
import { canAccess, useSessionStore, type Role } from './sessionStore'

type Props = {
  /** Rôle exigé. Sans rôle, une session ouverte suffit. */
  role?: Role
}

/**
 * Protège les routes enfants : page de connexion sans session (C6), « Accès refusé »
 * si le rôle manque (C7). Le serveur reste la source de vérité : ce n'est qu'un confort d'affichage.
 */
export function RequireRole({ role }: Props) {
  const session = useSessionStore((state) => state.session)

  if (session.status === 'loading') {
    return <p role="status">Chargement…</p>
  }
  if (session.status === 'anonymous') {
    return <Navigate to="/login" replace />
  }
  if (role !== undefined && !canAccess(session, role)) {
    return (
      <section>
        <title>Accès refusé – Notes de frais</title>
        <h1>Accès refusé</h1>
        <p>Cette page n'est pas accessible avec votre rôle.</p>
      </section>
    )
  }
  return <Outlet />
}
