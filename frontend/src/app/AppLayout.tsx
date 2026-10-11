import { useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router'
import { roleLabels } from '../features/auth/roles'
import { canAccess, useSessionStore, type Role } from '../features/auth/sessionStore'

const links: ReadonlyArray<{ to: string; label: string; role: Role }> = [
  { to: '/', label: 'Tableau de bord', role: 'employee' },
  { to: '/notes', label: 'Mes notes', role: 'employee' },
  { to: '/equipe', label: 'Équipe', role: 'manager' },
  { to: '/politique', label: 'Politique', role: 'admin' },
]

export function AppLayout() {
  const session = useSessionStore((state) => state.session)
  const logout = useSessionStore((state) => state.logout)
  const navigate = useNavigate()
  const [logoutError, setLogoutError] = useState<string | null>(null)

  async function handleLogout() {
    try {
      await logout()
      await navigate('/login') // C4
    } catch {
      setLogoutError('Déconnexion impossible, réessayez.')
    }
  }

  return (
    <>
      <header className="app-header">
        <nav aria-label="Navigation principale">
          <ul>
            {links
              .filter((link) => canAccess(session, link.role)) // C8
              .map((link) => (
                <li key={link.to}>
                  <NavLink to={link.to} end>
                    {link.label}
                  </NavLink>
                </li>
              ))}
          </ul>
        </nav>
        {session.status === 'authenticated' && (
          <p className="app-user">
            {session.user.name} ({roleLabels[session.user.role]})
          </p>
        )}
        <button type="button" onClick={handleLogout}>
          Se déconnecter
        </button>
        {logoutError && <p role="alert">{logoutError}</p>}
      </header>
      <main className="app-main">
        <Outlet />
      </main>
    </>
  )
}
