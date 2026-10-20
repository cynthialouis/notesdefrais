import { useState } from 'react'
import { NavLink, Outlet, useNavigate } from 'react-router'
import { roleLabels } from '../features/auth/roles'
import { canAccess, useSessionStore, type Role } from '../features/auth/sessionStore'
import { Alert } from '../ui/Alert'
import { Badge } from '../ui/Badge'
import { Button } from '../ui/Button'
import { cx } from '../ui/cx'

const links: ReadonlyArray<{ to: string; label: string; role: Role }> = [
  { to: '/', label: 'Tableau de bord', role: 'employee' },
  { to: '/notes', label: 'Mes notes', role: 'employee' },
  { to: '/conges', label: 'Congés', role: 'employee' },
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
    <div className="min-h-svh">
      <header className="border-b border-gray-200 bg-white">
        <div className="mx-auto flex max-w-5xl flex-wrap items-center gap-x-8 gap-y-2 px-4 py-3 sm:px-6">
          <p className="text-base font-semibold text-gray-900">Notes de frais</p>
          <nav aria-label="Navigation principale" className="order-last -mx-1 w-full overflow-x-auto sm:order-none sm:w-auto">
            <ul className="flex gap-1">
              {links
                .filter((link) => canAccess(session, link.role)) // C8
                .map((link) => (
                  <li key={link.to}>
                    <NavLink
                      to={link.to}
                      end={link.to === '/'}
                      className={({ isActive }) =>
                        cx(
                          'block rounded-md px-3 py-2 text-sm font-medium whitespace-nowrap',
                          'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand-500',
                          isActive ? 'bg-brand-50 text-brand-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
                        )
                      }
                    >
                      {link.label}
                    </NavLink>
                  </li>
                ))}
            </ul>
          </nav>
          <div className="ml-auto flex items-center gap-3">
            {session.status === 'authenticated' && (
              <p className="flex min-w-0 items-center gap-2 text-sm text-gray-700">
                <span className="truncate">{session.user.name}</span> <Badge>{roleLabels[session.user.role]}</Badge>
              </p>
            )}
            <Button onClick={handleLogout}>Se déconnecter</Button>
          </div>
        </div>
      </header>
      <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        {logoutError && <Alert className="mb-6">{logoutError}</Alert>}
        <Outlet />
      </main>
    </div>
  )
}
